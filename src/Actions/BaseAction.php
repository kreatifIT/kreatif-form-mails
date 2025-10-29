<?php

namespace Kreatif\StatamicForms\Actions;

use Illuminate\Support\Facades\Log;
use Kreatif\StatamicForms\Contracts\ActionResult;
use Kreatif\StatamicForms\Contracts\FormActionInterface;
use Statamic\Forms\Submission;

abstract class BaseAction implements FormActionInterface
{
    public function execute(Submission $submission, array $config): ActionResult
    {
        try {
            // Check if action should execute based on conditions
            if (!$this->shouldExecute($submission, $config)) {
                return ActionResult::success(
                    message: static::class . ' skipped due to conditions not being met'
                );
            }

            // Validate configuration
            if (!$this->validate($config)) {
                $errors = ['Invalid configuration for ' . static::class, $config, ];
                Log::error('Action validation failed', [
                    'action' => static::class,
                    'form' => $submission->form()->handle(),
                    'config' => $config,
                    'message' => 'Invalid configuration',
                ]);
                return ActionResult::failure($errors);
            }

            // Check if queued
            if ($config['queue'] ?? false) {
                return $this->executeQueued($submission, $config);
            }

            // Execute the action
            return $this->handle($submission, $config);

        } catch (\Throwable $e) {
            Log::error('Action execution failed', [
                'action' => static::class,
                'form' => $submission->form()->handle(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ActionResult::failure(
                errors: [$e->getMessage()],
                message: 'Action ' . static::class . ' failed: ' . $e->getMessage(). ' line ' . $e->getLine()
            );
        }
    }


    abstract protected function handle(Submission $submission, array $config): ActionResult;

    protected function executeQueued(Submission $submission, array $config): ActionResult
    {
        $queueConnection = $config['queue_connection'] ?? config('queue.default');
        $queueName = $config['queue_name'] ?? 'default';
        $delay = $config['delay'] ?? 0;

        dispatch(function () use ($submission, $config) {
            $this->handle($submission, $config);
        })
            ->onConnection($queueConnection)
            ->onQueue($queueName)
            ->delay($delay);

        return ActionResult::success(message: static::class . ' queued successfully');
    }

    public function validate(array $config): bool
    {
        return true;
    }

    /**
     * Get the default priority (lower runs first).
     */
    public function getPriority(): int
    {
        return 100;
    }

    public function shouldExecute(Submission $submission, array $config): bool
    {
        // Check if action is enabled
        if (isset($config['enabled']) && !$config['enabled']) {
            return false;
        }

        // Check conditional execution
        if (isset($config['when'])) {
            return $this->evaluateCondition($submission, $config['when']);
        }

        return true;
    }

    protected function evaluateCondition(Submission $submission, array $condition): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return true;
        }

        $fieldValue = $submission->get($field);

        return match ($operator) {
            '=', '==', 'equals' => $fieldValue == $value,
            '!=', 'not_equals' => $fieldValue != $value,
            '===', 'strict_equals' => $fieldValue === $value,
            '!==', 'strict_not_equals' => $fieldValue !== $value,
            '>', 'greater_than' => $fieldValue > $value,
            '>=', 'greater_than_or_equal' => $fieldValue >= $value,
            '<', 'less_than' => $fieldValue < $value,
            '<=', 'less_than_or_equal' => $fieldValue <= $value,
            'contains' => str_contains($fieldValue, $value),
            'not_contains' => !str_contains($fieldValue, $value),
            'in' => in_array($fieldValue, (array) $value),
            'not_in' => !in_array($fieldValue, (array) $value),
            'empty' => empty($fieldValue),
            'not_empty' => !empty($fieldValue),
            default => true,
        };
    }

    public static function isPreviewable(): bool
    {
        return true;
    }

    public static function name(): string
    {
        $str = class_basename(static::class);
        if (str_ends_with($str, 'Action')) {
            $str = substr($str, 0, -6);
        }
        if (str_starts_with($str, 'Send')) {
            $str = substr($str, 4);
        }
        return ucwords(str_replace(['-', '_'], ' ', $str));
    }


    public static function configFields(): array
    {
        return [];
    }

    public static function parseConfigFieldValue(string $fieldName, string $value, ?array $emailConfig): mixed
    {
        $fields = static::configFields();
        if (!$fields || empty($fields)) {
            return $value;
        }

        // exclude_fields
        if($fieldName === 'exclude_fields' && is_string($value)) {
            $findExclude = array_search('exclude_fields', array_column($fields, 'handle'));
            if ($findExclude !== false) {
                $items = array_map('trim', explode(',', $value));
                return $items;
            }
        }
        return $value;
    }



    protected function isValidEmail(string $email): bool
    {
        if (!mb_check_encoding($email, 'ASCII')) {
            return false;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (preg_match('/[^\x00-\x7F]/', $email)) {
            return false;
        }
        return true;
    }
}
