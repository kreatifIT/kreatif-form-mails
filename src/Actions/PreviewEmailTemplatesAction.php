<?php

namespace Kreatif\StatamicForms\Actions;

use Statamic\Actions\Action;

class PreviewEmailTemplatesAction extends Action
{

    protected static $title = 'Preview Email Templates';
    public function visibleTo($item)
    {
        return $item instanceof \Statamic\Forms\Submission;
    }

    public function visibleToBulk($items)
    {
        // Don't show for bulk actions
        return false;
    }

    public function authorize($user, $item)
    {
        return $user->can('view kreatif-forms email previews');
    }

    public function buttonText()
    {
        return 'Preview Email Templates';
    }

    public function confirmationText()
    {
        return null; // No confirmation needed
    }

    public function run($items, $values)
    {
        // This action doesn't actually run, it just redirects
        // The redirect is handled in the `redirect` method
    }

    public function redirect($items, $values)
    {
        $submission = $items->first();
        $formHandle = $submission->form()->handle();
        return cp_route('kreatif-forms.preview.template', ['formHandle' => $formHandle, 'submissionId' => $submission->id()]);
    }

}
