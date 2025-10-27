<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Notification')</title>
    <style>
        /* Basic Theme Styles - can be overridden by publishing */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #3a3a3a; background-color: #f4f4f7; margin: 0; padding: 0; }
        .email-wrapper { width: 100%; background-color: #f4f4f7; padding: 20px 0; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; }
        .email-header { padding: 20px;  border-bottom: 1px solid #e0e0e0; }
        .email-body { padding: 20px 30px; }
        .email-footer { padding: 20px; border-top: 1px solid #e0e0e0; text-align: center; font-size: 12px; color: #888; }
        .email-field-name {background-color: #f9f9f9}
    </style>
    @php
        $css = config('kreatif-statamic-forms.email.css', '');
        if (str_starts_with($css, 'css:')) {
            $cssStyles = trim(substr($css, 4));
            echo '<style>'.$cssStyles.'</style>';
        }else if (str_starts_with($css, 'url:')) {
            $cssUrl = trim(substr($css, 4));
            echo '<link rel="stylesheet" href="'.$cssUrl.'">';
        }
        $viteFile = null;
        if (str_starts_with($css, 'vite:')) {
            $viteFile = trim(substr($css, 5));
        }
    @endphp
    @if($viteFile)
        @vite([$viteFile])
    @endif
</head>
<body>
<div class="email-wrapper">
    <div class="email-container">
        @include('kreatif-forms::html.partials.header')

        <div class="email-body">
            @yield('content')
        </div>

        @include('kreatif-forms::html.partials.footer')
    </div>
</div>
</body>
</html>
