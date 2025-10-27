<?php

namespace Kreatif\StatamicForms\Mail;

class AdminNotificationMailable extends  BaseMailable
{

    protected function getDefaultHtmlView(): string
    {
        return 'kreatif-forms::html.emails.admin-notification';
    }

    protected function getDefaultTextView(): string
    {
        return 'kreatif-forms::text.emails.admin-notification';
    }

    protected function addMailTo(): self
    {
        $this->to = [];
        if (empty($this->to)) {
            $mailAddress = $this->mailConfig['to'] ?? null;
            $mailName = $this->mailConfig['to_name'] ?? null;

            $mailFromAddress = !empty($mailAddress) ? $mailAddress :  config('mail.from.address', 'dev@kreatif.it');
            $name = !empty($mailName) ? $mailName : config('mail.from.name', config('app.name', 'Kreatif'));

            $mailFromAddress = $this->parseRecipients($mailFromAddress);
            $this->to($mailFromAddress, $name);
        }
        return $this;
    }
}
