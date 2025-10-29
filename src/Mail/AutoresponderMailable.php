<?php

namespace Kreatif\StatamicForms\Mail;

class AutoresponderMailable extends BaseMailable
{

    protected function getDefaultHtmlView(): string {
        return 'kreatif-forms::html.emails.autoresponder';
    }

    protected function getDefaultTextView(): string {
        return 'kreatif-forms::text.emails.autoresponder';
    }

    protected function addMailTo(): self {
        $emailField = $this->mailConfig['email_field'] ?? 'email';
        $email = $this->statamicMailSubmission->get($emailField);
        $nameField = $this->mailConfig['name_field'] ?? null;
        if (!empty($nameField)) {
            if (is_string($nameField)) {
                $name = $this->statamicMailSubmission->get($nameField) ?? null;
            } else if (is_array($nameField)) {
                $mapped = array_map(function($field) {
                    return $this->statamicMailSubmission->get($field) ?? null;
                }, $nameField);
                $name = trim(implode(' ', array_filter($mapped))) ?: null;
            } else {
                $name = null;
            }
        } else {
            $firstname = $this->statamicMailSubmission->get('first_name') ?? $this->statamicMailSubmission->get('firstname') ?? null;
            $lastname = $this->statamicMailSubmission->get('last_name') ?? $this->statamicMailSubmission->get('lastname') ?? null;
            if ($firstname && $lastname) {
                $name = trim("{$firstname} {$lastname}") ?: null;
            } elseif ($this->statamicMailSubmission->has('name')) {
                $name = $this->statamicMailSubmission->get('name');
            } else {
                $name = null;
            }
        }
        $email = $this->parseRecipients($email);
        $this->to($email, $name??null);
        return $this;
    }


    protected function addReplyTo(): self
    {
        // Autoresponder typically does not need a reply-to address
        return $this;
    }

    protected function buildMailRecipients(): self
    {
        // Initialize all recipient arrays
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
        $this->from = [];
        $this->replyTo = [];

        // Only set from address for autoresponder
        if ($from = $this->mailConfig['from'] ?? null) {
            $recipients = $this->parseRecipients($from);
            $this->from($recipients);
        }

        // Autoresponder should only go to the submitter
        // Do NOT add CC or BCC etc to autoresponder emails
        $this->addMailTo();

        return $this;
    }

}
