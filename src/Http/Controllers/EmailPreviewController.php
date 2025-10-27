<?php

namespace Kreatif\StatamicForms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Kreatif\StatamicForms\Actions\BaseAction;
use Kreatif\StatamicForms\Actions\SendAdminNotificationAction;
use Kreatif\StatamicForms\Actions\SendAutoresponderAction;
use Kreatif\StatamicForms\Contracts\FormActionInterface;
use Kreatif\StatamicForms\Mail\AdminNotificationMailable;
use Kreatif\StatamicForms\Mail\AutoresponderMailable;
use Kreatif\StatamicForms\Mail\BaseMailable;
use Statamic\Facades\Form;
use Statamic\Forms\Submission;
use Statamic\Http\Controllers\CP\CpController;

use function PHPUnit\Framework\isInstanceOf;

class EmailPreviewController extends CpController
{
    /**
     * Show preview index - list all forms with email actions
     */
    public function index()
    {
        $this->authorize('view kreatif-forms email previews');
        $forms = Form::all()->filter(function ($form) {
            $handle = $form->handle();
            $config = config("kreatif-statamic-forms.handlers.{$handle}", []);
            if (empty($config['actions'])) {
                return false;
            }
            $hasActions = isset($config['actions']) && count($config['actions']) > 0;
            if (!$hasActions) {
                return false;
            }
            $actions = array_filter($config['actions'], function ($action, $actionClass) {
                return $action['enabled'] && is_a($actionClass, FormActionInterface::class, true) && $actionClass::isPreviewable();
            }, ARRAY_FILTER_USE_BOTH);
            return count($actions) > 0;
        })->map(function ($form) {
            $handle = $form->handle();
            $config = config("kreatif-statamic-forms.handlers.{$handle}", []);
            $previewableEnabledActions = array_filter($config['actions'] ?? [], function ($action, $actionClass) {
                return is_a($actionClass, FormActionInterface::class, true)
                    && $actionClass::isPreviewable();
            }, ARRAY_FILTER_USE_BOTH);
            return [
                'handle' => $handle,
                'title' => $form->title(),
                'actions_list' => $config['actions'],

            ];
        })->values();

        return view('kreatif-forms::cp.preview.index', [
            'forms' => $forms,
        ]);
    }


    public function previewActionTemplate(Request $request, string $formHandle, string $actionClass)
    {
        $this->authorize('view kreatif-forms email previews');
        $form = Form::find($formHandle);

        if (!$form) {
            abort(404, "Form '{$formHandle}' not found");
        }

        $config = config("kreatif-statamic-forms.handlers.{$formHandle}", []);
        $actionConfig = $config['actions'][$actionClass] ?? null;

        if (!$actionConfig || !($actionConfig['enabled'] ?? true)) {
            abort(404, "Action not configured for form '{$formHandle}'");
        }

        $submissionId = $request->get('submissionId', null);

        if ($submissionId) {
            $submission = $form->submission($submissionId);
            if (!$submission) {
                abort(404, "Submission '{$submissionId}' not found for form '{$formHandle}'. Remove from url the submissionId parameter to use sample data.");
            }
        } else {
            // Generate sample submission data
            $sampleData = $this->generateSampleData($form);
                $submission = $this->createMockSubmission($form, $sampleData);
        }


        // Merge configuration
        $globalConfig = config('kreatif-statamic-forms.email', []);
        $handlerBaseConfig = array_merge($globalConfig, collect($config)->except('actions')->toArray());
        $mergedConfig = array_merge($handlerBaseConfig, $actionConfig);

        /** @var BaseAction $action */
        $action = app($actionClass);
        /** @var class-string<BaseMailable> $action */
        $mailableClass = $action->getMailableClass();
        $mailable = new $mailableClass($submission, $mergedConfig);
        $format = $request->get('format', 'html');
        if ($format === 'text') {
            return response($mailable->render())
                ->header('Content-Type', 'text/plain');
        }

        return $mailable->render();
    }


    public function templatePreview(Request $request, string $formHandle, ?string $submissionId = null)
    {
        $this->authorize('view kreatif-forms email previews');

        /** @var \Statamic\Forms\Form $form */
        $form = Form::find($formHandle);

        if (!$form) {
            abort(404, "Form '{$formHandle}' not found");
        }

        $config = config("kreatif-statamic-forms.handlers.{$formHandle}", []);

        // Get sample or custom data
        if ($request->has('submission_data')) {
            $sampleData = $request->input('submission_data');
        } else {
            if ($submissionId) {
                $submission = $form->submission($submissionId);
                if ($submission) {
                    $sampleData = $submission->data()->all();
                } else {
                    abort(404, "Submission '{$submissionId}' not found for form '{$formHandle}'");
                }
            } else {
                $sampleData = $this->generateSampleData($form);
            }
        }

        $submission = $submission ?? $this->createMockSubmission($form, $sampleData);
        // $submission = $form->makeSubmission();

        return view('kreatif-forms::cp.preview.template', [
            'form' => $form,
            'submission' => $submission,
            'config' => $config,
            'sampleData' => $sampleData,
            'submissionId' => $submissionId,
        ]);
    }

    /**
     * Generate sample data based on form blueprint
     */
    protected function generateSampleData($form): array
    {
        $blueprint = $form->blueprint();
        $sampleData = [];

        foreach ($blueprint->fields()->all() as $field) {
            $handle = $field->handle();
            $type = $field->type();

            $sampleData[$handle] = match ($type) {
                'text' => $this->getSampleText($handle),
                'textarea' => $this->getSampleTextarea($handle),
                'email' => 'john.doe@example.com',
                'tel' => '+1 234 567 8900',
                'date' => now()->format('Y-m-d'),
                'time' => now()->format('H:i'),
                'select' => $field->get('options')[0] ?? 'Option 1',
                'radio' => $field->get('options')[0] ?? 'Option 1',
                'checkboxes' => array_slice($field->get('options', []), 0, 2),
                'toggle' => true,
                'integer' => 42,
                'float' => 3.14,
                default => 'Sample ' . $handle,
            };
        }

        return $sampleData;
    }

    /**
     * Get sample text based on field handle
     */
    protected function getSampleText(string $handle): string
    {
        return match (true) {
            str_contains(strtolower($handle), 'name') => 'John Doe',
            str_contains(strtolower($handle), 'first') => 'John',
            str_contains(strtolower($handle), 'last') => 'Doe',
            str_contains(strtolower($handle), 'company') => 'Acme Corporation',
            str_contains(strtolower($handle), 'subject') => 'Inquiry about your services',
            str_contains(strtolower($handle), 'phone') => '+1 234 567 8900',
            str_contains(strtolower($handle), 'city') => 'New York',
            str_contains(strtolower($handle), 'country') => 'United States',
            default => 'Sample ' . ucfirst($handle),
        };
    }

    /**
     * Get sample textarea content
     */
    protected function getSampleTextarea(string $handle): string
    {
        return match (true) {
            str_contains(strtolower($handle), 'message') => 'Hello, I am interested in learning more about your services. Could you please provide more information about your offerings and pricing?',
            str_contains(strtolower($handle), 'comment') => 'This is a sample comment with some detailed information.',
            str_contains(strtolower($handle), 'description') => 'A detailed description of the request or inquiry.',
            default => 'Sample textarea content for ' . $handle,
        };
    }

    /**
     * Create a mock submission object
     */
    protected function createMockSubmission(\Statamic\Forms\Form $form, array $data): Submission
    {
        // Create a mock submission using Statamic's Submission class
        $submission = $form->makeSubmission();
        $submission->data($data);

        return $submission;
    }
}
