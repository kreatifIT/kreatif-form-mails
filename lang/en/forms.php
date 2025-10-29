<?php

return [
    "email_configuration" => [
        "field_title" => "E-Mail Actions",
        "field_instructions" => "Select which Kreatif actions to perform for this email config. No emails will be sent if no actions are selected.",
    ],
    "actions" => [
        // 'send_admin_notification' => 'Invia notifica ad amministrazione',
        'send_admin_notification' => 'Send admin notification',
        'send_autoresponder' => 'Send autoresponder to user',
    ],
    "action_config" => [
        "configuration" => "Configuration",
        "section_instructions" => "Configure the settings for this action",
        "subject" => "Email Subject",
        "subject_instructions" => "Email subject line. Use 'translate:key' for translations or {{ variables }} for dynamic values.",
        "html_template" => "HTML Template",
        "html_template_instructions" => "Path to the HTML email template view (e.g., kreatif-forms::html.emails.autoresponder)",
        "exclude_fields" => "Exclude Fields",
        "exclude_fields_instructions" => "Field handles to exclude from the email content (e.g., privacy, consent, password)",
        "iubenda_preferences" => "Consent Preferences",
        "iubenda_preferences_instructions" => "Key-value pairs for Iubenda consent preferences (e.g., newsletter: false)",
    ],
    "hello" => "Hello!",
    "rights_reserved" => "All rights reserved.",
    "new_submission_subject" => "New Form Submission",
    "admin_notification_title" => "New Form Submission Details",
    "admin_notification_intro" => "You have received a new form submission. Here are the details:",
    "autoresponder_data_intro" => "Here is a copy of your submission data:",
    "autoresponder_title" => "Thank you for your submission!",
    "autoresponder_intro" => "We have received your submission and will get back to you shortly.",
    "regards" => "Regards",
    "the_team" => "Team",
];