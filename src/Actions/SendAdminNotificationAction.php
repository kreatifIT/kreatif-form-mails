<?php

namespace Kreatif\StatamicForms\Actions;

use Illuminate\Support\Facades\Mail;
use Kreatif\StatamicForms\Contracts\ActionResult;
use Kreatif\StatamicForms\Mail\AdminNotificationMailable;
use Statamic\Forms\Submission;

class SendAdminNotificationAction extends BaseAction
{

    public function getMailableClass(): ?string
    {
        return AdminNotificationMailable::class;
    }

    public static function name(): string {
        return __("kreatif-forms::forms.actions.send_admin_notification");
    }

    protected function handle(Submission $submission, array $config): ActionResult
    {
        // Validate recipient email addresses
        $recipients = $config['to'] ?? null;
        if ($recipients) {
            $recipientList = is_string($recipients)
                ? array_map('trim', explode(',', $recipients))
                : (array) $recipients;

            foreach ($recipientList as $email) {
                // Skip template variables like {{config.mail.from.address}}
                if (preg_match('/\{\{.*\}\}/', $email)) {
                    continue;
                }

                if (!$this->isValidEmail($email)) {
                    return ActionResult::failure(
                        errors: ["Invalid recipient email address: {$email}"],
                        message: 'Admin notification failed: invalid email address'
                    );
                }
            }
        }

        $mailableClass = $this->getMailableClass();
        Mail::to($config['to'])->send(
            new $mailableClass($submission, $config)
        );

        return ActionResult::success(
            message: 'Admin notification email sent successfully'
        );
    }

    public function validate(array $config): bool
    {
        // if (empty($config['to'])) {
        //     return false;
        // }
        //
        // $recipients = is_string($config['to'])
        //     ? array_map('trim', explode(',', $config['to']))
        //     : (array) $config['to'];
        //
        // foreach ($recipients as $email) {
        //      // TODO: fix what if image is {{config.mail.from.address}}
        //     if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        //         return false;
        //     }
        // }

        return true;
    }

    public function getPriority(): int
    {
        return 30; // High priority - send notifications early
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
                "default" => "translate:kreatif-forms::forms.new_submission_subject",
                "width" => 50,
            ],
            [
                "handle" => "exclude_fields",
                "display" => __("kreatif-forms::forms.action_config.exclude_fields"),
                "type" => "text",
                "instructions" => __("kreatif-forms::forms.action_config.exclude_fields_instructions"),
                "placeholder" => "privacy, consent",
                "default" => "privacy, consent",
                "width" => 50,
            ],
        ];
    }
}