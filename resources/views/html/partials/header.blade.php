@php
    $logoUrl = $logoUrl ?? config('kreatif-statamic-forms.email.logo_url', null);
@endphp

<div class="email-header bg-primary">
    @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $organizationName ?? config('app.name') }} Logo"
             style="max-width: 150px; height: auto; margin: 0 auto; display: block;">
    @else
        <h1 style="margin: 0; font-size: 24px; color: #333; text-align: center;">
            {{ $organizationName ?? config('app.name') }}
        </h1>
    @endif
</div>
