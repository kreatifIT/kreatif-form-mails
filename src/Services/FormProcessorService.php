<?php

namespace Kreatif\StatamicForms\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Kreatif\StatamicForms\Contracts\ActionResult;
use Kreatif\StatamicForms\Contracts\FormActionInterface;
use Kreatif\StatamicForms\Events\ActionExecuted;
use Kreatif\StatamicForms\Events\ActionFailed;
use Kreatif\StatamicForms\Events\FormProcessingCompleted;
use Kreatif\StatamicForms\Events\FormProcessingStarted;
use Statamic\Forms\Submission;

class FormProcessorService
{
    public function process(Submission $submission, array $formEmailConfigs = []): array
    {
        $form = $submission->form();
        $formHandle = $form->handle();

        // Check rate limiting
        if (!$this->checkRateLimit($submission, $formHandle)) {
            Log::warning("Rate limit exceeded for form: {$formHandle}", [
                'ip' => request()->ip(),
                'submission_id' => $submission->id(),
            ]);
            return [
                'success' => false,
                'message' => 'Too many submissions. Please try again later.',
            ];
        }

        $addonConfig = config("kreatif-statamic-forms.handlers.{$formHandle}", []);
        $globalConfig = config('kreatif-statamic-forms.email', []);

        if (empty($addonConfig['actions'])) {
            Log::info("No addon actions configured for form: {$formHandle}");
            return ['success' => true, 'message' => 'No actions to process'];
        }

        if (empty($formEmailConfigs) && empty($globalConfig)) {
            Log::info("No email configuration found for form: {$formHandle}, running non-email actions only");
        }

        $handlerBaseConfig = array_merge($globalConfig, Arr::except($addonConfig, ['actions']));

        $results = [];
        if (!empty($formEmailConfigs)) {
            foreach ($formEmailConfigs as $formEmailConfig) {
                $results[$formEmailConfig['id']] = $this->executeActions($submission, $addonConfig['actions'], $handlerBaseConfig, $formEmailConfig);
            }
        } else {
            $results = $this->executeActions($submission, $addonConfig['actions'], $handlerBaseConfig, []);
        }

        $areAllSuccessful = true;
        foreach ($results as $result) {
            if (is_array($result) && isset($result['results'])) {
                foreach ($result['results'] as $actionResult) {
                    if (!$actionResult->isSuccess()) {
                        $areAllSuccessful = false;
                        break 2;
                    }
                }
            } elseif ($result instanceof ActionResult) {
                if (!$result->isSuccess()) {
                    $areAllSuccessful = false;
                    break;
                }
            }
        }
        return [
            'success' => $areAllSuccessful,
            'results' => $results,
        ];
    }

    protected function executeActions(
        Submission $submission,
        array $actions,
        array $handlerBaseConfig,
        array $formEmailConfig
    ): array {
        $results = [];

        // Sort actions by priority (lower numbers run first)
        $sortedActions = $this->sortActionsByPriority($actions);

        // Dispatch event
        FormProcessingStarted::dispatch($submission, $sortedActions);

        foreach ($sortedActions as $actionClass => $actionConfig) {
            if (!class_exists($actionClass)) {
                Log::error("Action class {$actionClass} does not exist.");
                continue;
            }

            // Check if action is disabled
            if (isset($actionConfig['enabled']) && !$actionConfig['enabled']) {
                $formHandle = $submission->form()->handle();
                Log::info("Action {$actionClass} is disabled for form: {$formHandle}");
                continue;
            }

            try {
                $mergedConfig = array_merge($handlerBaseConfig, $formEmailConfig, $actionConfig);
                $action = app($actionClass);

                // Ensure action implements the interface
                if (!$action instanceof FormActionInterface) {
                    Log::error("Action {$actionClass} does not implement FormActionInterface");
                    continue;
                }

                $result = $action->execute($submission, $mergedConfig);
                $results[$actionClass] = $result;

                // Dispatch event based on result
                if ($result->isSuccess()) {
                    ActionExecuted::dispatch($actionClass, $submission, $result);

                    if (config('kreatif-statamic-forms.logging.enabled', true)) {
                        Log::info("Action {$actionClass} executed successfully", [
                            'form' => $submission->form()->handle(),
                            'message' => $result->getMessage(),
                        ]);
                    }
                } else {
                    ActionFailed::dispatch($actionClass, $submission, $result);

                    Log::error("Action {$actionClass} failed", [
                        'form' => $submission->form()->handle(),
                        'errors' => $result->getErrors(),
                        'message' => $result->getMessage(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error("Exception executing action {$actionClass}", [
                    'form' => $submission->form()->handle(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $result = ActionResult::failure(
                    errors: [$e->getMessage()],
                    message: 'Action failed with exception'
                );
                $results[$actionClass] = $result;

                ActionFailed::dispatch($actionClass, $submission, $result);
            }
        }

        // Dispatch completion event
        FormProcessingCompleted::dispatch($submission, $results);

        return $results;
    }

    protected function sortActionsByPriority(array $actions): array
    {
        $actionsWithPriority = [];

        foreach ($actions as $actionClass => $actionConfig) {
            if (!class_exists($actionClass)) {
                continue;
            }

            try {
                $action = app($actionClass);
                $priority = $actionConfig['priority'] ?? $action->getPriority();
            } catch (\Throwable $e) {
                $priority = 100; // Default priority if instantiation fails
            }

            $actionsWithPriority[$actionClass] = [
                'config' => $actionConfig,
                'priority' => $priority,
            ];
        }

        // Sort by priority (lower numbers first)
        uasort($actionsWithPriority, fn($a, $b) => $a['priority'] <=> $b['priority']);

        // Return just the configs
        return array_map(fn($item) => $item['config'], $actionsWithPriority);
    }

    protected function checkRateLimit(Submission $submission, string $formHandle): bool
    {
        $rateLimitConfig = config("kreatif-statamic-forms.handlers.{$formHandle}.rate_limit", []);

        if (!($rateLimitConfig['enabled'] ?? false)) {
            return true; // Rate limiting disabled
        }

        $maxAttempts = $rateLimitConfig['max_attempts'] ?? 5;
        $decayMinutes = $rateLimitConfig['decay_minutes'] ?? 60;
        $by = $rateLimitConfig['by'] ?? 'ip';

        // Generate rate limit key
        $key = match ($by) {
            'email' => 'form:' . $formHandle . ':email:' . $submission->get('email'),
            'session' => 'form:' . $formHandle . ':session:' . session()->getId(),
            default => 'form:' . $formHandle . ':ip:' . request()->ip(),
        };

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return true;
    }
}