<?php

namespace Kreatif\StatamicForms\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Kreatif\StatamicForms\Services\FormProcessorService;
use Statamic\Events\FormSubmitted;
use Statamic\Events\SubmissionCreated;
use Statamic\Facades\Site;

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
        $site = Site::findByUrl(URL::previous()) ?? Site::default();
        $locale = $site->handle();
        app()->setLocale($locale);
        $formHandle = $event->submission->form()->handle();

        // Check if this addon should handle emails for this form
        $handlerConfig = config("kreatif-statamic-forms.handlers.{$formHandle}", []);
        $disableStatamicEmail = $handlerConfig['disable_statamic_email']
            ?? config('kreatif-statamic-forms.disable_statamic_email', false);

        $form = $event->submission->form();
        $originalEmailConfig = $form->email() ?? [];
        /** @var \Statamic\Contracts\Forms\Submission|mixed $submission */
        $submission = $event->submission;

        $processingResult = null;
        try {
            $processingResult = $this->processor->process($submission, $originalEmailConfig);
            // Check if processing was successful
            if (isset($processingResult['success']) && !$processingResult['success']) {
                $errorMessages = [];

                // Collect error messages from failed actions
                if (isset($processingResult['results'])) {
                    foreach ($processingResult['results'] as $resultGroup) {
                        if (is_array($resultGroup)) {
                            // When multiple email configs exist, iterate through action results
                            foreach ($resultGroup as $actionResult) {
                                if ($actionResult instanceof \Kreatif\StatamicForms\Contracts\ActionResult && !$actionResult->isSuccess(
                                    )) {
                                    $errorMessages[] = $actionResult->getMessage();
                                    if ($errors = $actionResult->getErrors()) {
                                        $errorMessages = array_merge($errorMessages, $errors);
                                    }
                                }
                            }
                        } elseif ($resultGroup instanceof \Kreatif\StatamicForms\Contracts\ActionResult && !$resultGroup->isSuccess(
                            )) {
                            // Single action result
                            $errorMessages[] = $resultGroup->getMessage();
                            if ($errors = $resultGroup->getErrors()) {
                                $errorMessages = array_merge($errorMessages, $errors);
                            }
                        }
                    }
                }

                $errorMessage = implode('; ', array_filter($errorMessages)) ?: 'Form processing failed';

                Log::error('Form processing failed: '.$errorMessage, [
                    'form_handle' => $formHandle,
                    'submission_id' => $submission->id(),
                    'results' => $processingResult,
                ]);
                throw ValidationException::withMessages([
                    'email' => $errorMessages,
                ]);
            }
        } catch (ValidationException $e) {
            throw $e; // Re-throw validation exceptions so Statamic can handle them
        } catch (\Exception $e) {
            Log::error('Error processing form submission: '.$e->getMessage(), [
                'form_handle' => $formHandle,
                'submission_id' => $submission->id(),
            ]);
            throw $e;
        }
        if ($form->store()) {
            try {
                $submission->save();
            } catch (\Throwable $th) {
                Log::error('Error saving form submission: '.$th->getMessage(), [
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