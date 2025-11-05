<?php

namespace Kreatif\StatamicForms\Actions;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Kreatif\StatamicForms\Contracts\ActionResult;
use Kreatif\StatamicForms\Mail\AutoresponderMailable;
use Statamic\Forms\Submission;

class TranslationValueFallback
{

    public static function handle(string $key, mixed $value, Submission $submission): string {
        return Str::title(str_replace('_', ' ', $key));
    }
}