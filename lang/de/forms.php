<?php

return [
    "email_configuration" => [
        "field_title" => "E-Mail Action",
        "field_instructions" => "Wählen Sie aus, welche Kreatif-Aktionen für diese E-Mail ausgeführt werden sollen. Keine E-Mails werden gesendet, wenn keine Aktionen ausgewählt sind.",
    ],

    "actions" => [
        // 'send_admin_notification' => 'Invia notifica ad amministrazione',
        'send_admin_notification' => 'Admin-Benachrichtigung senden',
        'send_autoresponder' => 'Autoresponder an Benutzer senden',
    ],
    "action_config" => [
        "configuration" => "Konfiguration",
        "section_instructions" => "Konfigurieren Sie die Einstellungen für diese Aktion",
        "subject" => "E-Mail-Betreff",
        "subject_instructions" => "E-Mail-Betreffzeile. Verwenden Sie 'translate:key' für Übersetzungen.",
        "html_template" => "HTML Template",
        "html_template_instructions" => "Pfad zur HTML-E-Mail-Vorlagenansicht (z. B. kreatif-forms::html.emails.autoresponder)",
        "exclude_fields" => "Felder ausschließen",
        "exclude_fields_instructions" => "Feld-Handles, die vom E-Mail-Inhalt ausgeschlossen werden sollen (z. B. privacy, consent, password)",
        "iubenda_preferences" => "Einwilligungspräferenzen",
        "iubenda_preferences_instructions" => "Key-Value-Paare für Iubenda-Einwilligungspräferenzen (z. B. newsletter: false)",
    ],
    "hello" => "Hallo!",
    "rights_reserved" => "Alle Rechte vorbehalten.",
    "new_submission_subject" => "Neue Formularübermittlung",
    "admin_notification_title" => "Details zur neuen Formularübermittlung",
    "admin_notification_intro" => "Sie haben eine neue Formularübermittlung erhalten. Hier sind die Details:",
    "autoresponder_data_intro" => "Hier ist eine Kopie Ihrer Übermittlungsdaten:",
    "autoresponder_title" => "Vielen Dank für Ihre Übermittlung!",
    "autoresponder_intro" => "Wir haben Ihre Übermittlung erhalten und werden uns in Kürze bei Ihnen melden.",
    "regards" => "Mit freundlichen Grüßen",
    "the_team" => "Das Team",
];