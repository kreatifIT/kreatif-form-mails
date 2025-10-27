<?php

use Kreatif\StatamicForms\Actions\AddToIubendaAction;
use Kreatif\StatamicForms\Actions\SendAdminNotificationAction;
use Kreatif\StatamicForms\Actions\SendAutoresponderAction;

return [

    /*
    |--------------------------------------------------------------------------
    | Disable Statamic's Default Email Sending
    |--------------------------------------------------------------------------
    | When set to true, this addon will take over email sending and prevent
    | Statamic's default email handler from running. You can override this
    | per form in the handler configuration.
    */
    'disable_statamic_email' => true,

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    | Control logging behavior for form processing and actions.
    */
    'logging' => [
        'enabled' => true,
        'channel' => null, // null uses default log channel
        'level' => 'info', // debug, info, warning, error
    ],

    /*
    |--------------------------------------------------------------------------
    | General Email Settings
    |--------------------------------------------------------------------------
    | These are the global fallback settings for all emails.
    */
    'email' => [
        'logo_url' => env('FORM_EMAIL_LOGO_URL', null),
        'organization_name' => env('FORM_EMAIL_ORG_NAME', config('app.name')),
        'sanitize_content' => true, // Sanitize email content to prevent XSS
        'exclude_fields' => ['form', 'site', 'privacy', 'consent'],
        'css' => 'vite:resources/css/site.css', // 'css:.cid{}, .cid2{}', or 'url:https://exampe.com/assets/sites.css', 'vite:resources/css/site.css'
    ],

    /*
    |--------------------------------------------------------------------------
    | Iubenda API Configuration
    |--------------------------------------------------------------------------
    | All addon-specific configuration now lives inside the addon's own
    | config file. This makes it truly portable.
    */
    'iubenda' => [
        'public_key' => env('IUBENDA_CONSENT_DB_API_KEY'),
        'base_uri'   => 'https://consent.iubenda.com/',
    ],


    /*
    |--------------------------------------------------------------------------
    | Form Handlers
    |--------------------------------------------------------------------------
    | Define custom actions for each form handle.
    | The key is the form's handle (e.g., 'contact', 'newsletter_signup').
    |
    | Available action configuration options:
    | - enabled: (bool) Enable or disable the action
    | - priority: (int) Execution priority (lower numbers run first)
    | - queue: (bool) Queue the action for async execution
    | - queue_connection: (string) Queue connection to use
    | - queue_name: (string) Queue name
    | - delay: (int) Delay in seconds before queuing
    | - when: (array) Conditional execution based on submission data
    |   Example: ['field' => 'notify_admin', 'operator' => '=', 'value' => true]
    */
    'handlers' => [
        'contact_form' => [
            // Override global settings for this form
            'logo_url' => env('FORM_EMAIL_LOGO_URL', ''),
            'organization_name' => env('FORM_EMAIL_ORG_NAME', 'Brimi'),
            // Disable Statamic's default email for this form only
            'disable_statamic_email' => true,
            // Rate limiting configuration (optional)
            'rate_limit' => [
                'enabled' => true,
                'max_attempts' => 5,
                'decay_minutes' => 60,
                'by' => 'ip', // 'ip', 'email', or 'session'
            ],

            // Actions to execute for this form
            'actions' => [
                SendAdminNotificationAction::class => [
                    'enabled' => true,
                    'to' => 'client@example.com', // multiple emails: 'client@example.com,client2@example.com'
                    'cc' => 'project-manager@myagency.com',
                    // 'bcc' => 'archive@example.com',
                    // 'reply_to' => 'custom@example.com',
                    'subject' => 'New Contact Form Submission',
                    // For translation: 'translate:kreatif-forms::forms.new_submission_subject'

                    // Optional: Queue this action
                    // 'queue' => true,
                    // 'queue_connection' => 'redis',
                    // 'queue_name' => 'emails',
                    // 'delay' => 0,

                    // Optional: Conditional execution
                    // 'when' => [
                    //     'field' => 'notify_admin',
                    //     'operator' => '=',
                    //     'value' => true,
                    // ],
                    'exclude_fields' => [], // Fields to exclude from email
                ],

                SendAutoresponderAction::class => [
                    'enabled' => true,
                    'subject' => 'Thanks for your message!',
                    // 'email_field' => 'email', // Field name containing user's email
                    // 'template_type' => 'html', // 'html', 'markdown', or 'text'
                    // 'html' => 'kreatif-forms::html.emails.autoresponder',
                    // 'text' => 'kreatif-forms::text.emails.autoresponder',
                ],

                AddToIubendaAction::class => [
                    'enabled' => env('IUBENDA_CONSENT_DB_ENABLED', false),
                    'preferences' => ['newsletter' => false],
                    'field_mapping' => [
                        'first_name' => 'firstname', // Iubenda Key => Form Field Handle
                        'last_name'  => 'lastname',
                        'email'      => 'email',
                    ],
                    // If you only have a single name field:
                    // 'field_mapping' => [
                    //     'first_name' => 'name', // Will be split automatically
                    //     'last_name'  => null,
                    //     'email'      => 'email',
                    // ],
                    // if custom legal notices are used, map the fields accordingly:
                    // 'legal_notices' => ['privacy' => true, 'tos' => false, 'marketing' => false, 'privacy_link' => 'www.example.com/privacy'],
                ],
            ],
        ],

        'an_other_form' => [
            // 'logo_url' => env('FORM_EMAIL_LOGO_URL', ''),
            // ...
            'actions' => [
                AddToIubendaAction::class => [
                    'enabled' => env('IUBENDA_CONSENT_DB_ENABLED', false),
                    'legal_notices' => [
                        // Submitting a form, add consents that user has agreed to. Must be saved in Consent DB according to a Law
                        'privacy_policy' => true,
                        'cookie_policy' => true,
                        'custom_terms_of_service' => true,
                    ],
                ],
                //
            ],
        ],
    ],
];