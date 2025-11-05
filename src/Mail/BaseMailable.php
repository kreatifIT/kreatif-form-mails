<?php

namespace Kreatif\StatamicForms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Statamic\Facades\Antlers;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Forms\Submission;

abstract class BaseMailable extends Mailable
{
    use Queueable, SerializesModels;

    public Submission $statamicMailSubmission;
    public array $mailConfig;

    public function __construct(Submission $submission, array $config)
    {
        $this->statamicMailSubmission = $submission;
        $this->mailConfig = $config;
    }

    public function build(): self
    {
        $this->buildMailSubject()
             ->buildMailRecipients()
             ->buildMailViews()
             ->resolveAndQueueAttachments()
             ->addMailTo()
        ;


        return $this;
    }

    protected function getMailSubject(): string
    {
        return $this->mailConfig['subject'] ?? 'New Submission';
    }

    protected function buildMailSubject(): self
    {
        $subject = $this->getMailSubject();
        if (Str::startsWith($subject, 'translate:')) {
            $subject = __(Str::after($subject, 'translate:'));
        }
        return $this->subject($subject);
    }

    protected function buildMailRecipients(): self
    {
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
        $this->from = [];
        $this->replyTo = [];

        if ($from = $this->mailConfig['from'] ?? null) {
            $this->from($this->parseRecipients($from));
        }

        if ($replyTo = $this->mailConfig['replyTo'] ?? $this->mailConfig['reply_to'] ?? $this->statamicMailSubmission->get('email')) {
            $this->addReplyTo();
        }

        if ($to = $this->mailConfig['to'] ?? null) {
            $this->addMailTo();
        }

        if ($cc = $this->mailConfig['cc'] ?? null) {
            $this->cc($this->parseRecipients($cc));
        }

        if ($bcc = $this->mailConfig['bcc'] ?? null) {
            $this->bcc($this->parseRecipients($bcc));
        }

        return $this;
    }

    protected function getMailData(): array
    {
        // $data = $this->submission->data()->all();
        $data = $this->mailConfig;

        // Sanitize data if configured
        if ($this->mailConfig['sanitize_content'] ?? true) {
            $data = $this->sanitizeData($data);
        }

        $fields = $this->statamicMailSubmission->data()->all();
        $fields = $this->removeKeysFromArray($fields, config('kreatif-statamic-forms.email.exclude_fields', []));;
        $fields = $this->removeKeysFromArray($fields, $this->mailConfig['exclude_fields'] ?? []);;

        // Map field values to their labels from blueprint options
        $fields = $this->mapFieldValuesToLabels($fields);
        if ($data && is_array($data)) {
            $data = array_merge($data, ["fields" => $fields]);
        } else {
            $data = $fields;
        }

        return [
            'data' => $data,
            'submission' => $this->statamicMailSubmission,
            'form' => $this->statamicMailSubmission->form(),
            'fields' => $fields,
            'logoUrl' => $this->mailConfig['logo_url'] ?? null,
            'organizationName' => $this->mailConfig['organization_name'] ?? config('app.name'),
        ];
    }

    private function removeKeysFromArray(array $array, array $keys): array
    {
        foreach ($keys as $key) {
            unset($array[$key]);
        }
        return $array;
    }

    /**
     * Map field values to their labels from blueprint options.
     * Handles checkboxes, radio buttons, select, and multiselect fields.
     */
    protected function mapFieldValuesToLabels(array $fields): array
    {
        $blueprintFields = $this->statamicMailSubmission->form()->blueprint()->fields()->all();
        $mapped = [];

        foreach ($fields as $fieldHandle => $fieldValue) { // Find the corresponding blueprint field
            $blueprintField = $blueprintFields->get($fieldHandle);

            if (!$blueprintField) {
                $mapped[$fieldHandle] = $fieldValue;
                continue;
            }

            // Get field type and options
            $fieldType = (string) $blueprintField->type();
            $fieldOptions = $blueprintField->config()['options'] ?? null;

            // Only process fields with options (checkboxes, radio, select, multiselect)
            if (!$fieldOptions || !in_array($fieldType, ['checkboxes', 'radio', 'select', 'button_group'])) {
                $mapped[$fieldHandle] = $fieldValue;
                continue;
            }

            $optionsMap = $this->buildOptionsMap($fieldOptions);
            if (is_array($fieldValue)) {
                // Handle multiple values (checkboxes, multiselect)
                $mapped[$fieldHandle] = array_map(function ($value) use ($optionsMap) {
                    return $this->translateValue($value, $optionsMap[$value] ?? $value);
                }, $fieldValue);
            } else { // Handle single value (radio, select, button_group)
                $mapped[$fieldHandle] = $this->translateValue($fieldHandle, $optionsMap[$fieldValue] ?? $fieldValue);
            }
        }

        return $mapped;
    }

    /**
     * Build a map of option keys to their labels/values.
     */
    protected function buildOptionsMap($options): array
    {
        $map = [];

        foreach ($options as $option) {
            if (is_array($option)) {
                // Options defined as ['key' => 'foo', 'value' => 'Foo Label']
                $key = $option['key'] ?? null;
                $value = $option['value'] ?? $option['label'] ?? $key;
                if ($key !== null) {
                    $map[$key] = $value;
                }
            } elseif (is_string($option)) {
                // Simple string options
                $map[$option] = $option;
            }
        }

        return $map;
    }

    /**
     * Translate a value if it uses the ###key### pattern or is a translation key.
     */
    protected function translateValue($key, $value): string
    {
        if (!is_string($key)) {
            return (string) $keygit ;
        }

        $prefixes = config('kreatif-statamic-forms.email.translations_prefix_key', []);

        // Try common prefixes
        foreach ($prefixes as $prefix) {
            $translated = __($prefix . $key);
            if ($translated !== $prefix . $key) {
                return $translated;
            }
            if (is_array($value)) {
                foreach ($value as $subValue) {
                    if (is_string($subValue)) {
                        $translated = __($prefix . $subValue);
                        if ($translated !== $prefix . $subValue) {
                            return $translated;
                        }
                    }
                }
            } elseif(is_string($value)) {
                $translated = __($prefix . $value);
                if ($translated !== $prefix . $value) {
                    return $translated;
                }
            }
        }

        $fallbackHandler = config('kreatif-statamic-forms.email.translation_fallback_handle', null);
        if ($fallbackHandler && class_exists($fallbackHandler)) {
            /** @var \Kreatif\StatamicForms\Actions\TranslationValueFallback::class $fallbackHandler */
            $translationKey = $fallbackHandler::handle($key, $value, $this->statamicMailSubmission);
            $translated = __($translationKey);
            if ($translated !== $translationKey) {
                return $translated;
            }
        }
        return $value;
    }

    /**
     * Sanitize submission data to prevent XSS in emails.
     */
    protected function sanitizeData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } elseif (is_string($value)) {
                $value = $this->handleAntlersParsing($value, $data);
                $sanitized[$key] = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    protected function getPrioritizedTemplate(): string
    {
        $templateType = $this->mailConfig['template_type'] ?? 'html';
        $templateTypeLower = strtolower($templateType);

        $template = $this->mailConfig['template'] ?? 'kreatif-forms::html.emails.admin-notification';

        $template = (
        in_array($templateTypeLower, ['markdown', 'html'], true)
            ? $this->mailConfig['html'] ?? $this->getDefaultHtmlView() ?? "kreatif-forms::$templateTypeLower.emails.admin-notification"
            : $this->mailConfig['text'] ?? $this->getDefaultTextView() ?? 'kreatif-forms::text.emails.admin-notification'
        ) ?? $template;
        return $template;
    }

    protected function buildMailViews(): self
    {
        $this->with($this->getMailData());

        $templateType = $this->mailConfig['template_type'] ?? 'html';
        $template = $this->getPrioritizedTemplate();

        if ($templateType === 'markdown') {
            $this->markdown($template);
        } elseif ($templateType === 'html') {
            $this->view($template);
        } elseif ($templateType === 'text') {
            $this->text($template);
        } else {
            $this->view($template);
        }

        return $this;
    }

    protected function parseRecipients($recipients): array
    {
        if (is_string($recipients)) {
            $recipients = trim($recipients);
            $recipientsToArr = explode(',', $recipients);
            return array_filter(array_map(function($value) {
                return trim($this->handleAntlersParsing($value) ?? '');
            }, $recipientsToArr));
        } else {
            Log::warning("Invalid recipients: {$recipients} or unhandle case.");
        }
        return (array) $recipients;
    }

    protected function handleAntlersParsing(string $value, array $data = []): string
    {
        if (Str::startsWith($value, '{{') && Str::endsWith($value, '}}')) {
            $configs = config()->all();
            $contextualData = array_merge(
                ['config' => $configs],
                $configs,
                $data
            );
            return (string) Antlers::parse($value, $contextualData);
        }
        return $value;
    }

    protected function resolveAndQueueAttachments(): self
    {
        $attachments = false;
        if (!isset($this->mailConfig['attachments'])) {
            $fields = $this->statamicMailSubmission->form()->blueprint()->fields()->all();
            foreach ($fields as $field) {;
                if ((string) $field->type() === 'assets') {
                    $attachments = true;
                }
            }
        } else if (isset($this->mailConfig['attachments'])) {
            $attachments = filter_var($this->mailConfig['attachments']??false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }
        if (!$attachments ) {
            return $this;
        }

        $allowed = $this->mailConfig['attach_fields'] ?? null;
        $allowed = is_null($allowed) ? null : array_map('strval', Arr::wrap($allowed));

        // Separate exclude list for attachments (different from exclude_fields which is for email body content)
        $excludeAttachments = $this->mailConfig['exclude_attachments'] ?? [];

        /** @var \Statamic\Fields\Field[] $fields */
        $fields = $this->statamicMailSubmission->form()->blueprint()->fields()->all();
        $requestInFiles = request()->allFiles();

        foreach ($fields as $field) {
            $handle = (string) $field->handle();

            // Check if this field is in the exclude_attachments list
            if (in_array($handle, $excludeAttachments, true)) {
                continue;
            }

            // If attach_fields is specified, only attach those fields
            if ($allowed && !in_array($handle, $allowed, true)) {
                continue;
            }

            $type  = (string) $field->type();
            $value = $this->statamicMailSubmission->get($handle);

            if ($value === null || $value === '') {
                continue;
            }

            // Statamic Assets fieldtype: array of IDs or Asset models
            if ($type === 'assets') {
                foreach (Arr::wrap($value) as $idOrAsset) {
                    $asset = is_string($idOrAsset) ? AssetFacade::find($idOrAsset) : $idOrAsset;
                    if (!$asset) {
                        if (isset($requestInFiles[$handle])) {
                            // maybe it's a direct upload via the form [RISKY]!
                            foreach (Arr::wrap($requestInFiles[$handle]) as $file) {
                                if ($file instanceof \Illuminate\Http\UploadedFile) {
                                    $this->attach($file->getRealPath(), [
                                        'as'   => $file->getClientOriginalName(),
                                        'mime' => $file->getClientMimeType(),
                                    ]);
                                }
                            }
                        }
                        continue;
                    }
                    $disk = $asset->container()->diskHandle();
                    $path = $asset->path();

                    $this->attachFromStorageDisk($disk, $path, [
                        'as'   => $asset->basename(),
                        'mime' => $asset->mimeType(),
                    ]);
                }
                continue;
            }

            // Laravel file/files fieldtypes (UploadedFile|string path)
            // actually the file fieldtype doesn't exist in Statamic, but just in case...
            if ($type === 'file' || $type === 'files') {
                foreach (Arr::wrap($value) as $item) {
                    if ($item instanceof \Illuminate\Http\UploadedFile) {
                        $this->attach($item->getRealPath(), [
                            'as'   => $item->getClientOriginalName(),
                            'mime' => $item->getClientMimeType(),
                        ]);
                        continue;
                    }

                    if (is_string($item)) {
                        // absolute path
                        if (is_file($item)) {
                            $this->attach($item, ['as' => basename($item)]);
                            continue;
                        }

                        // search in all configured disks
                        foreach (array_keys(config('filesystems.disks', [])) as $disk) {
                            if (Storage::disk($disk)->exists($item)) {
                                $this->attachFromStorageDisk($disk, $item, ['as' => basename($item)]);
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $this;
    }

    abstract protected function getDefaultHtmlView(): string;

    abstract protected function getDefaultTextView(): string;

    protected function addMailTo(): self {
        $to = $this->mailConfig['to'] ?? null;
        if (!empty($to)) {
            $this->to($this->parseRecipients($to));
        }
        return $this;
    }

    protected function addReplyTo(): self
    {
        $replyTo = $this->mailConfig['replyTo'] ?? $this->mailConfig['reply_to'] ?? $this->statamicMailSubmission->get('email');
        if (!empty($replyTo)) {
            $this->replyTo($this->parseRecipients($replyTo));
        }
        return $this;
    }
}
