<?php

namespace Kreatif\StatamicForms\Actions;

use Illuminate\Support\Facades\Mail;
use Kreatif\StatamicForms\Contracts\ActionResult;
use Kreatif\StatamicForms\Mail\AdminNotificationMailable;
use Kreatif\StatamicForms\Mail\AutoresponderMailable;
use Statamic\Forms\Submission;

class SendAutoresponderAction extends BaseAction
{

    public function getMailableClass(): ?string
    {
        return AutoresponderMailable::class;
    }


    protected function handle(Submission $submission, array $config): ActionResult
    {
        $emailField = $config['email_field'] ?? 'email';
        $userEmail = $submission->get($emailField);

        if (!$userEmail) {
            return ActionResult::failure(
                errors: ['No email address found in submission'],
                message: 'Autoresponder failed: no email field'
            );
        }

        $mailableClass = $this->getMailableClass();
        Mail::to($userEmail)->send(
            new $mailableClass($submission, $config)
        );

        return ActionResult::success(
            message: 'Autoresponder email sent successfully'
        );
    }

    public function validate(array $config): bool
    {
        // Autoresponder can work without explicit config,
        // as it extracts email from submission
        return true;
    }

    public function getPriority(): int
    {
        return 60; // Medium priority - send after admin notifications
    }
}