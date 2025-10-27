<?php

namespace Kreatif\StatamicForms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
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
            dd($recipients);
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


        /** @var \Statamic\Fields\Field[] $fields */
        $fields = $this->statamicMailSubmission->form()->blueprint()->fields()->all();
        $requestInFiles = request()->allFiles();

        $actionFieldsToExclude = $this->mailConfig['exclude_fields'] ?? [];
        foreach ($fields as $field) {
            $handle = (string) $field->handle();
            if (in_array($handle, $actionFieldsToExclude, true)) {
                continue;
            }

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
