## Kreatif Forms for Statamic

An configuration-driven addon for Statamic v5+ form submissions. Orchestrate complex post-submission workflows including emails, Iubenda consent tracking, and custom integrations—all from a central configuration file with priority-based execution, conditional logic, queue support, and comprehensive error handling.

### Key Features

#### Core Architecture
- **Action-Based System**: Trigger multiple actions for each form submission with priority-based execution
- **Centralized Configuration**: Manage all form logic from a single configuration file
- **Config Merging**: Seamlessly combines global, handler-specific, and form-specific (YAML) configurations
- **Interface-Driven**: All actions implement `FormActionInterface` for consistency and extensibility
- **Error Handling**: Comprehensive try-catch blocks with detailed logging and graceful degradation
- **Event System**: Extensible events for `FormProcessingStarted`, `FormProcessingCompleted`, `ActionExecuted`, and `ActionFailed`

#### Email Management
- **Statamic Email Control**: Optionally disable Statamic's default email sending (configurable globally or per-form)
- **Admin Notifications**: Send detailed submission notifications to administrators
- **Autoresponders**: Send confirmation emails to form submitters
- **Multiple Template Formats**: Support for HTML, Markdown, and plain text templates
- **Multiple Recipients**: Configure `to`, `cc`, `bcc`, and `replyTo` addresses
- **Dynamic Branding**: Configurable logos and organization names per form
- **Content Sanitization**: Built-in XSS protection for email content
- **Multilingual Support**: Full translation support for subjects and content

#### Advanced Features
- **Queue Support**: Queue any action for asynchronous execution with configurable connections, queues, and delays
- **Conditional Execution**: Execute actions based on submission data with powerful operators (equals, contains, in, empty, etc.)
- **Priority Control**: Define execution order with priority values (lower numbers run first)
- **Rate Limiting**: Prevent spam with configurable rate limits by IP, email, or session
- **Validation**: Built-in configuration validation for all actions
- **Action Results**: Structured result objects with success/failure states, data, and error messages

#### Built-in Actions
- **SendAdminNotificationAction**: Email notifications to administrators (Priority: 50)
- **SendAutoresponderAction**: Confirmation emails to users (Priority: 60)
- **AddToIubendaAction**: Send consent to Iubenda Consent Database (Priority: 90)


# Installation & Setup
1. Copy Addon: Place the entire kreatif/statamic-forms directory into your project's addons/ folder. 
2. Autoload: Run composer dump-autoload from your project's root directory to make Statamic aware of the addon's classes. 
3. Publish Assets: Publish the configuration file. This is the only mandatory step. Publishing views and language files is optional for customization.
4. ```bash
    # Publish the configuration file (required)
    php please vendor:publish --tag=kreatif-forms-config
    
    # Publish email templates (optional)
    php please vendor:publish --tag=kreatif-forms-views
    
    # Publish language files (optional)
    php please vendor:publish --tag=kreatif-forms-lang
    ```
5. Configure Environment: Add the necessary API keys and URLs to your project's .env file.
    ```bash 
    # Required for Iubenda Action
    IUBENDA_CONSENT_DB_API_KEY="your_iubenda_public_key"
    
    # Optional: Set a global logo URL for emails
    FORM_EMAIL_LOGO_URL="https://your-cdn.com/logo.png"
    FORM_EMAIL_ORG_NAME="Your Company Name"
    ```

### Configuration

All addon logic is controlled via the `config/kreatif-statamic-forms.php` file.

#### Global Settings

##### Disable Statamic's Default Email

By default, Statamic will send its own emails based on the form's YAML configuration. You can disable this globally or per-form:

```php
// Disable globally for all forms (can be overridden per form)
'disable_statamic_email' => false,

// Or per-form in handlers:
'handlers' => [
    'contact_form' => [
        'disable_statamic_email' => true, // This form's emails are handled by the addon
        'actions' => [...]
    ]
]
```

##### Logging Configuration

Control how the addon logs form processing:

```php
'logging' => [
    'enabled' => true,
    'channel' => null, // null uses default log channel
    'level' => 'info', // debug, info, warning, error
],
```

##### Email Settings

These settings act as the default for all form handlers:

```php
'email' => [
    'logo_url' => env('FORM_EMAIL_LOGO_URL', null),
    'organization_name' => env('FORM_EMAIL_ORG_NAME', config('app.name')),
    'sanitize_content' => true, // Prevent XSS in emails
],
```

#### Form Handlers

This is the core of the addon. Each key in the handlers array corresponds to a Statamic form handle.

```php
'handlers' => [
    'contact_form' => [
        // Override global settings for this form
        'logo_url' => env('CONTACT_FORM_LOGO_URL'),
        'organization_name' => 'Your Company',
        'disable_statamic_email' => true,

        // Rate limiting (optional)
        'rate_limit' => [
            'enabled' => true,
            'max_attempts' => 5,
            'decay_minutes' => 60,
            'by' => 'ip', // 'ip', 'email', or 'session'
        ],

        // Define the actions to run for this form
        'actions' => [
            // ... actions are defined here ...
        ],
    ],
],
```

#### Action Configuration Options

All actions support these common configuration options:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `true` | Enable or disable the action |
| `priority` | int | Action-specific | Execution priority (lower runs first) |
| `queue` | bool | `false` | Queue the action for async execution |
| `queue_connection` | string | `default` | Laravel queue connection to use |
| `queue_name` | string | `default` | Queue name |
| `delay` | int | `0` | Delay in seconds before queueing |
| `when` | array | `null` | Conditional execution based on submission data |

##### Queue Example

Queue an action for asynchronous processing:

```php
SendAdminNotificationAction::class => [
    'enabled' => true,
    'to' => 'admin@example.com',
    'queue' => true,
    'queue_connection' => 'redis',
    'queue_name' => 'emails',
    'delay' => 5, // Wait 5 seconds before processing
],
```

##### Conditional Execution

Execute actions only when certain conditions are met:

```php
SendAdminNotificationAction::class => [
    'enabled' => true,
    'to' => 'admin@example.com',
    'when' => [
        'field' => 'notify_admin',
        'operator' => '=',
        'value' => true,
    ],
],
```

**Supported Operators:**
- `=`, `==`, `equals` - Equal to
- `!=`, `not_equals` - Not equal to
- `===`, `strict_equals` - Strictly equal to
- `!==`, `strict_not_equals` - Strictly not equal to
- `>`, `greater_than` - Greater than
- `>=`, `greater_than_or_equal` - Greater than or equal to
- `<`, `less_than` - Less than
- `<=`, `less_than_or_equal` - Less than or equal to
- `contains` - String contains value
- `not_contains` - String does not contain value
- `in` - Value is in array
- `not_in` - Value is not in array
- `empty` - Field is empty
- `not_empty` - Field is not empty

##### Priority Control

Actions execute in priority order (lower numbers first). Default priorities:

- SendAdminNotificationAction: 50
- SendAutoresponderAction: 60
- AddToIubendaAction: 90

Override the priority:

```php
AddToIubendaAction::class => [
    'enabled' => true,
    'priority' => 10, // Run this first
],
```

#### Rate Limiting

Prevent spam submissions with rate limiting:

```php
'handlers' => [
    'contact_form' => [
        'rate_limit' => [
            'enabled' => true,
            'max_attempts' => 5,      // Max 5 submissions
            'decay_minutes' => 60,    // Per 60 minutes
            'by' => 'ip',            // Rate limit by: 'ip', 'email', or 'session'
        ],
    ],
],
```

#### Available Actions

`SendAdminNotificationAction` Sends a detailed notification email to administrators. 

##### Configuration:

```php
use Kreatif\StatamicForms\Actions\SendAdminNotificationAction;

SendAdminNotificationAction::class => [
'enabled' => true,
'to'      => 'admin@example.com, support@example.com', // Comma-separated string
'cc'      => 'manager@example.com',
'bcc'     => 'archive@example.com',
'from'    => 'noreply@yourdomain.com',
'reply_to'=> 'custom-reply@yourdomain.com', // If not set, defaults to the user's email
'subject' => 'translate:kreatif-forms::forms.new_submission_subject', // Translatable subject
],
```

`SendAutoresponderAction` sends a confirmation email to the user who submitted the form.
```php
use Kreatif\StatamicForms\Actions\SendAutoresponderAction;

SendAutoresponderAction::class => [
'enabled' => true,
'from'    => 'support@yourdomain.com',
'subject' => 'Thanks for your message!',
// Optionally specify a custom template
'html'    => 'kreatif-forms::html.emails.special-autoresponder',
'text'    => 'kreatif-forms::text.emails.special-autoresponder',
],
```

`AddToIubendaAction` Sends consent data to the Iubenda Consent Database.

```php
use Kreatif\StatamicForms\Actions\AddToIubendaAction;

AddToIubendaAction::class => [
    'enabled' => true,
    'preferences' => ['newsletter' => true],
    'field_mapping' => [
        // Iubenda API Key => Your Form Field Handle
        'first_name' => 'vorname',
        'last_name'  => 'nachname',
        'email'      => 'email_address',
    ],
],
```

Advanced Mapping Tip: If your form only has a single `name` field, you can map it like this. The addon will automatically split it into a first and last name.

```php
'field_mapping' => [
    'first_name' => 'full_name', // Map the full name field
    'last_name'  => null,      // Set last_name to null
    'email'      => 'email',
],
```

### Usage Examples

##### Example 1: Standard Contact Form

Your addon config controls everything. The form's YAML is minimal.

`config/kreatif-statamic-forms.php`:
```php
'handlers' => [
    'contact' => [
        'actions' => [
            SendAdminNotificationAction::class => ['enabled' => true, 'to' => 'admin@site.com'],
            SendAutoresponderAction::class => ['enabled' => true],
        ],
    ],
],
```

`resources/forms/a_form.yaml`:
```yml
title: Contact
# No email section needed!
```


##### Example 2: Overriding with Form YAML

The form's YAML configuration takes priority over the addon's action config. This is useful for client-managed forms.
`config/kreatif-statamic-forms.php`:
```php
'handlers' => [
    'inquiries' => [
        'actions' => [
            // This action will run, but its 'to' and 'subject' will be overridden
            SendAdminNotificationAction::class => ['enabled' => true, 'to' => 'fallback@site.com'],
        ],
    ],
],
```

`resources/forms/inquiries.yaml`
```yml
:title: Inquiries
email:
  -
    to: 'client-managed-email@site.com' # This email will be used
    subject: 'A New Inquiry has Arrived!' # This subject will be used

```

#### Example 3: API-Only Form (Iubenda)
This setup uses the addon to send data to Iubenda but lets Statamic's default mailer handle the emails. This is achieved by not including the email actions in the handler.

`config/kreatif-statamic-forms.php`:
```php
 'handlers' => [
    'newsletter_signup' => [
        'actions' => [
            // Only the Iubenda action is defined
            AddToIubendaAction::class => ['enabled' => true],
        ],
    ],
],
```

`resources/forms/newsletter_signup.yaml`:

```yml
title: Newsletter Signup
# Statamic's mailer will run because the addon doesn't stop it
email:
-
    to: 'marketing@site.com'
    subject: 'New Newsletter Subscriber'
```

### Events System

The addon dispatches events throughout the form processing lifecycle. You can listen to these events to add custom logic:

#### Available Events

- **`FormProcessingStarted`** - Dispatched when form processing begins
  - Properties: `Submission $submission`, `array $actions`

- **`FormProcessingCompleted`** - Dispatched when all actions have been executed
  - Properties: `Submission $submission`, `array $results`

- **`ActionExecuted`** - Dispatched when an action succeeds
  - Properties: `string $actionClass`, `Submission $submission`, `ActionResult $result`

- **`ActionFailed`** - Dispatched when an action fails
  - Properties: `string $actionClass`, `Submission $submission`, `ActionResult $result`

#### Example: Listen to Events

Create a listener in your application:

```php
namespace App\Listeners;

use Kreatif\StatamicForms\Events\ActionFailed;
use Illuminate\Support\Facades\Log;

class LogFailedFormActions
{
    public function handle(ActionFailed $event): void
    {
        Log::critical('Form action failed', [
            'action' => $event->actionClass,
            'form' => $event->submission->form()->handle(),
            'errors' => $event->result->getErrors(),
        ]);

        // Maybe send alert to monitoring service
        // Sentry::captureMessage('Form action failed');
    }
}
```

Register in `EventServiceProvider`:

```php
protected $listen = [
    \Kreatif\StatamicForms\Events\ActionFailed::class => [
        \App\Listeners\LogFailedFormActions::class,
    ],
];
```

### Creating Custom Actions

You can easily create custom actions by implementing `FormActionInterface` or extending `BaseAction`:

```php
namespace App\FormActions;

use Kreatif\StatamicForms\Actions\BaseAction;
use Kreatif\StatamicForms\Contracts\ActionResult;
use Statamic\Forms\Submission;

class SendToCustomCrmAction extends BaseAction
{
    protected function handle(Submission $submission, array $config): ActionResult
    {
        $apiKey = $config['api_key'] ?? env('CRM_API_KEY');
        $endpoint = $config['endpoint'] ?? 'https://crm.example.com/api/contacts';

        // Your custom logic here
        $response = Http::post($endpoint, [
            'api_key' => $apiKey,
            'name' => $submission->get('name'),
            'email' => $submission->get('email'),
        ]);

        if ($response->failed()) {
            return ActionResult::failure(
                errors: [$response->body()],
                message: 'Failed to send to CRM'
            );
        }

        return ActionResult::success(
            data: $response->json(),
            message: 'Successfully sent to CRM'
        );
    }

    public function validate(array $config): bool
    {
        return !empty($config['api_key'] ?? env('CRM_API_KEY'));
    }

    public function getPriority(): int
    {
        return 95; // Run after most other actions
    }
}
```

Then use it in your config:

```php
use App\FormActions\SendToCustomCrmAction;

'handlers' => [
    'contact_form' => [
        'actions' => [
            SendToCustomCrmAction::class => [
                'enabled' => true,
                'api_key' => env('CRM_API_KEY'),
                'endpoint' => 'https://crm.example.com/api/contacts',
            ],
        ],
    ],
],
```

### Email Preview Feature

Preview and test email templates before sending them to actual recipients.

#### Access Preview
Navigate to **Tools → Form Email Preview** in the Statamic Control Panel, or click "Preview Email Templates" from any form's submissions listing.

**Permissions**: Requires `view kreatif-forms email previews` permission.

#### Features
- **Preview Index**: View all forms with email actions and their status
- **Direct Preview**: Preview admin notifications and autoresponders with sample data
- **Template Editor**: Interactive editor to customize sample data and preview both email types
- **Multiple Formats**: View HTML and plain text versions

#### Quick Preview URLs
```
/cp/kreatif-forms/preview                          # List all forms
/cp/kreatif-forms/preview/{formHandle}/admin       # Admin notification preview
/cp/kreatif-forms/preview/{formHandle}/autoresponder  # Autoresponder preview
/cp/kreatif-forms/preview/{formHandle}/template    # Template editor
```

Add `?format=text` to any preview URL to see the plain text version.

#### How It Works
The preview controller automatically generates intelligent sample data based on your form's blueprint:
- Field types are detected and appropriate sample values are generated
- Smart pattern matching for common fields (name, email, company, etc.)
- Respects all configuration settings (logo, branding, excluded fields)
- Merges configs the same way live submissions do


## How to Install

You can install this addon via Composer:

``` bash
composer require kreatif/forms
```


