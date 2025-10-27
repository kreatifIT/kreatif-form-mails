@extends('kreatif-forms::html.layouts.default')

@section('title', __('kreatif-forms::forms.admin_notification_title'))

@section('content')
    <h2>{{ __('kreatif-forms::forms.admin_notification_title') }}</h2>
    <p>{{ __('kreatif-forms::forms.admin_notification_intro') }}</p>

    <table class="email-table" style="width: 100%; border-collapse: collapse;">
        @foreach ($fields as $key => $value)
            @if(is_array($value) && isset($value['value']) && $value['value'] instanceof \Statamic\Fields\Value)
                @php
                    $key = $value['handle'] ?? $key;
                    $value = $value['value']->value();
                @endphp
            @endif
            @if(!in_array($key, config('kreatif-statamic-forms.email.exclude_fields', [])))
                <tr>
                    <th class='email-field-name' style="padding: 10px; border: 1px solid #ddd; text-align: left;">
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
                        {{ $label }}
                    </th>
                    <td class='email-field-value' style="padding: 10px; border: 1px solid #ddd; text-align: left;">
                        @if(is_array($value))
                            {{ implode(', ', ($value)) }}
                        @else
                            {{ $value ?? 'N/A' }}
                        @endif
                    </td>
                </tr>
            @endif
        @endforeach
    </table>
@endsection
