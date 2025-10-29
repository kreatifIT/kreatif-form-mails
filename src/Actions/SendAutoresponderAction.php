<?php

namespace Kreatif\StatamicForms\Actions;

use Illuminate\Support\Facades\Mail;
use Kreatif\StatamicForms\Contracts\ActionResult;
use Kreatif\StatamicForms\Mail\AutoresponderMailable;
use Statamic\Forms\Submission;

class SendAutoresponderAction extends BaseAction
{

    public function getMailableClass(): ?string
    {
        return AutoresponderMailable::class;
    }

    public static function name(): string {
        return __("kreatif-forms::forms.actions.send_autoresponder");
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

        // Validate email address (check for ASCII-only and valid format)
        if (!$this->isValidEmail($userEmail)) {
            return ActionResult::failure(
                errors: ["Invalid email address format: {$userEmail}"],
                message: 'Autoresponder failed: invalid email address'
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

    public static function configFields(): array
    {
        return [
            [
                "handle" => "subject",
                "display" => __("kreatif-forms::forms.action_config.subject"),
                "type" => "text",
                "instructions" => __("kreatif-forms::forms.action_config.subject_instructions"),
                "placeholder" => "translate:kreatif-forms::forms.new_submission_subject",
                "width" => 33,
            ],
            [
                "handle" => "html",
                "display" => __("kreatif-forms::forms.action_config.html_template"),
                "type" => "text",
                "instructions" => __("kreatif-forms::forms.action_config.html_template_instructions"),
                "placeholder" => "kreatif-forms::html.emails.autoresponder",
                "default" => "kreatif-forms::html.emails.autoresponder",
                "width" => 33,
            ],
            [
                "handle" => "exclude_fields",
                "display" => __("kreatif-forms::forms.action_config.exclude_fields"),
                "type" => "text",
                "instructions" => __("kreatif-forms::forms.action_config.exclude_fields_instructions"),
                "placeholder" => "privacy, consent",
                "default" => "privacy, consent",
                "width" => 33,
            ],
            // [
            //     "handle" => "exclude_attachments",
            //     "display" => __("kreatif-forms::forms.action_config.exclude_attachments"),
            //     "type" => "text",
            //     "instructions" => __("kreatif-forms::forms.action_config.exclude_attachments_instructions"),
            //     "placeholder" => "privacy, consent",
            //     "default" => "privacy, consent",
            //     "width" => 100,
            // ],

        ];
    }
}