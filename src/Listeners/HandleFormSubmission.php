<?php

namespace Kreatif\StatamicForms\Listeners;

use Illuminate\Support\Facades\Log;
use Kreatif\StatamicForms\Services\FormProcessorService;
use Statamic\Events\FormSubmitted;
use Statamic\Events\SubmissionCreated;

class HandleFormSubmission
{
    protected FormProcessorService $processor;

    public function __construct(FormProcessorService $processor)
    {
        $this->processor = $processor;
    }

    /**
     * @param  FormSubmitted  $event
     * @return ?bool Whether to disable Statamic's default email handling
     * @throws \Exception
     */
    public function handle(FormSubmitted $event): ?bool
    {
        $formHandle = $event->submission->form()->handle();

        // Check if this addon should handle emails for this form
        $handlerConfig = config("kreatif-statamic-forms.handlers.{$formHandle}", []);
        $disableStatamicEmail = $handlerConfig['disable_statamic_email']
            ?? config('kreatif-statamic-forms.disable_statamic_email', false);

        $form = $event->submission->form();
        $originalEmailConfig = $form->email() ?? [];
        /** @var \Statamic\Contracts\Forms\Submission|mixed $submission */
        $submission = $event->submission;

        try {
            $this->processor->process($submission, $originalEmailConfig);
        } catch (\Exception $e) {
            Log::error('Error processing form submission: ' . $e->getMessage(), [
                'form_handle' => $formHandle,
                'submission_id' => $submission->id(),
            ]);
            throw $e;
        }
        if ($form->store()) {
            try {
                $submission->save();
            } catch (\Throwable $th) {
                Log::error('Error saving form submission: ' . $th->getMessage(), [
                    'form_handle' => $formHandle,
                    'submission_id' => $submission->id(),
                ]);
                throw $th;
            }
        } else {
            // When the submission is saved, this same created event will be dispatched.
            // We'll also fire it here if submissions are not configured to be stored
            // so that developers may continue to listen and modify it as needed.
            SubmissionCreated::dispatch($submission);
        }

        if ($disableStatamicEmail === true) {
            return false; // Disable Statamic's default email handling
        }
        return true;
    }
}