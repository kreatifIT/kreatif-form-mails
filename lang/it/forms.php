<?php

return [
    "email_configuration" => [
        "field_title" => "E-Mail Action",
        "field_instructions" => "Seleziona quali azioni Kreatif eseguire per questa configurazione e-mail. Nessun mail viene mandato se nessuna azione è selezionata.",
    ],
    "actions" => [
        'send_admin_notification' => 'Invia notifica ad amministrazione',
        'send_autoresponder' => 'Invia risposta automatica all\'utente',
    ],
    'action_config' => [
        'configuration' => 'Configurazione',
        'section_instructions' => 'Configura le impostazioni per questa azione e-mail amministrativa.',
        'subject' => 'Oggetto',
        'subject_instructions' => 'Oggetto dell\'e-mail di notifica amministrativa. i.e labels.mails.formx.subject',
        'exclude_fields' => 'Escludi campi (handles)',
        'exclude_fields_instructions' => 'Elenco separato da virgole di handle dei campi del modulo da escludere dall\'e-mail di notifica amministrativa.',
        'html_template' => 'Modello HTML personalizzato',
        'html_template_instructions' => '(i.e kreatif-forms::html.emails.autoresponder o mails/x/c/x.blade.php)',
    ],
    "hello" => "Ciao!",
    "rights_reserved" => "Tutti i diritti riservati.",
    "new_submission_subject" => "Nuovo invio del modulo",
    "admin_notification_title" => "Dettagli della nuova invio del modulo",
    "admin_notification_intro" => "Hai ricevuto una nuova invio del modulo. Ecco i dettagli:",
    "autoresponder_data_intro" => "Ecco una copia dei dati del tuo invio:",
    "autoresponder_title" => "Grazie per il tuo invio!",
    "autoresponder_intro" => "Abbiamo ricevuto il tuo invio e ti risponderemo a breve.",
    "regards" => "Saluti",
    "the_team" => "Il team",
];