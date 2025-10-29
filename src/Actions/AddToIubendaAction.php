<?php

namespace Kreatif\StatamicForms\Actions;

use Kreatif\StatamicForms\Contracts\ActionResult;
use Kreatif\StatamicForms\Services\IubendaDataConsentService;
use Statamic\Forms\Submission;

class AddToIubendaAction extends BaseAction
{
    public array $defaultFieldMapping = [
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'email' => 'email',
    ];

    public function getMailableClass(): ?string
    {
        return null;
    }

    protected function handle(Submission $submission, array $config): ActionResult
    {
        $mapping = array_merge($this->defaultFieldMapping, $config['field_mapping'] ?? []);

        $firstNameHandle = $mapping['first_name'] ?? 'first_name';
        $lastNameHandle = $mapping['last_name'] ?? 'last_name';
        $emailHandle = $mapping['email'] ?? 'email';
        $legalNotices = $config['legal_notices'] ?? [
            'privacy_policy' => $config['privacy_policy'] ?? true,
            'cookie_policy' => $config['cookie_policy'] ?? true,
        ];


        $firstName = $submission->get($firstNameHandle);
        $lastName = $submission->get($lastNameHandle);
        // Handle the case where only a single 'name' field is provided.
        if ($firstName && is_null($lastNameHandle)) {
            $parts = explode(' ', $firstName, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';
        }

        $email = $submission->get($emailHandle);

        if (!$email) {
            return ActionResult::failure(
                errors: ['Email is required for Iubenda consent'],
                message: 'Iubenda consent failed: no email'
            );
        }

        $response = (new IubendaDataConsentService)
            ->setFirstname($firstName)
            ->setLastname($lastName)
            ->setEmail($email)
            ->setPreferences($config['preferences'] ?? [])
            ->setLegalNotices($legalNotices)
            ->createConsent();

        return ActionResult::success(
            data: $response,
            message: 'Iubenda consent created successfully'
        );
    }

    public function validate(array $config): bool
    {
        if (empty(config('kreatif-statamic-forms.iubenda.public_key'))) {
            return false;
        }

        return true;
    }

    public function getPriority(): int
    {
        return 90; // Lower priority - run after emails
    }


    public static function isPreviewable(): bool
    {
        return false;
    }

    public static function configFields(): array
    {
        return [
            // [
            //     "handle" => "preferences",
            //     "display" => __("kreatif-forms::forms.action_config.iubenda_preferences"),
            //     "type" => "array",
            //     "instructions" => __("kreatif-forms::forms.action_config.iubenda_preferences_instructions"),
            //     "placeholder" => "newsletter: false",
            //     "mode" => "keyed",
            //     "width" => 100,
            // ],
        ];
    }

}