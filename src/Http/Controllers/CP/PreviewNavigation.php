<?php

namespace Kreatif\StatamicForms\Http\Controllers\CP;

use Statamic\Facades\CP\Nav;

class PreviewNavigation
{
    public static function register(): void
    {
        Nav::extend(function ($nav) {
            $nav->create('Form Email Preview')
                ->section('Tools')
                ->route('kreatif-forms.preview.index')
                ->icon('form')
                ->can('view kreatif-forms email previews');
        });
    }
}
