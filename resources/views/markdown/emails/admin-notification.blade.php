@component('mail::message')

{{ __('kreatif-forms::forms.admin_notification_title') }}
{{ __('kreatif-forms::forms.admin_notification_intro') }}

@component('mail::table')
| Key | Value |
|:----|:------|
@foreach ($fields as $key => $value)
@if(is_array($value) && isset($value['value']) && $value['value'] instanceof \Statamic\Fields\Value)
@php
    $key = $value['handle'] ?? $key;
    $value = $value['value']->value();
@endphp
@endif
@if(!in_array($key, config('kreatif-statamic-forms.email.exclude_fields', [])))
@php
$label = __($key);
if ($label === $key) {
$label = __('labels.mail.fields.' . $key);
}
if ($label === 'labels.mail.fields.' . $key) {
$label = __('labels.' . $key);
}
if ($label === 'labels.' . $key) {
$label = Str::title(str_replace('_', ' ', $key));
}
@endphp
@if(is_array($value))
| {{ $label }} | {{ implode(', ', ($value)) }} |
@else
| {{ $label }} | {{ $value ?? 'N/A' }} |
@endif
@endif
@endforeach

@endcomponent

@endcomponent
---

© {{ date('Y') }} {{ $organizationName ?? config('app.name') }}. {{ __('kreatif-forms::forms.rights_reserved') }}
