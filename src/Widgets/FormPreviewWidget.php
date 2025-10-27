<?php

namespace Kreatif\StatamicForms\Widgets;

use Statamic\Facades\Form;
use Statamic\Widgets\Widget;

class FormPreviewWidget extends Widget
{
    public function html()
    {
        // TODO: yet. Already out of budget
        // This widget will be shown on form pages
        $formHandle = request()->segment(3); // Get form handle from URL /cp/forms/{handle}

        if (!$formHandle) {
            return '';
        }

        $form = Form::find($formHandle);
        if (!$form) {
            return '';
        }

        $config = config("kreatif-statamic-forms.handlers.{$formHandle}", []);

        if (empty($config['actions'])) {
            return '';
        }

        $hasAdminNotification = isset($config['actions'][\Kreatif\StatamicForms\Actions\SendAdminNotificationAction::class])
            && ($config['actions'][\Kreatif\StatamicForms\Actions\SendAdminNotificationAction::class]['enabled'] ?? true);

        $hasAutoresponder = isset($config['actions'][\Kreatif\StatamicForms\Actions\SendAutoresponderAction::class])
            && ($config['actions'][\Kreatif\StatamicForms\Actions\SendAutoresponderAction::class]['enabled'] ?? true);

        if (!$hasAdminNotification && !$hasAutoresponder) {
            return '';
        }

        return view('kreatif-forms::widgets.form-preview', [
            'formHandle' => $formHandle,
            'hasAdminNotification' => $hasAdminNotification,
            'hasAutoresponder' => $hasAutoresponder,
        ])->render();
    }
}
