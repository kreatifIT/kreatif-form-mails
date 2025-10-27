@extends('statamic::layout')

@section('title', 'Email Preview')

@section('content')
    <header class="mb-6">
        <h1>Email Preview</h1>
        <p class="text-gray-700">Preview email templates for forms with configured email actions.</p>
    </header>

    @if($forms->isEmpty())
        <div class="card p-6 text-center">
            <p class="text-gray-600">No forms with email actions configured.</p>
            <p class="text-sm text-gray-500 mt-2">Configure email actions in
                <code>config/kreatif-statamic-forms.php</code></p>
        </div>
    @else
        <div class="card">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Form</th>
                    <th>Handle</th>
                    <th>Actions Status</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($forms as $form)
                    <tr>
                        <td>
                            <a href="{{ cp_route('forms.show', $form['handle']) }}"
                               class="font-medium text-blue-600 hover:text-blue-800">
                                {{ $form['title'] }}
                            </a>
                        </td>
                        <td class="font-mono text-sm text-gray-600">{{ $form['handle'] }}</td>
                        <td class="text-center">
                            @php
                                $totalActions = count($form['actions_list']);
                            @endphp
                            @foreach($form['actions_list'] as $actionName => $actionData)
                                @php
                                    $isEnabled = isset($actionData['enabled']) && $actionData['enabled'] ? 'Enabled' : 'Disabled';
                                    $isFormAction = is_a($actionName, \Kreatif\StatamicForms\Contracts\FormActionInterface::class, true);
                                    $isPreviewable = $isFormAction && $actionName::isPreviewable() ? 'Previewable' : 'Not Previewable';
                                    $name = $isFormAction ? $actionName::name() : class_basename($actionName);
                                @endphp
                                <strong>{{ $name }}</strong>:
                                <span class="badge badge-sm {{ $isEnabled === 'Enabled' ? 'badge-success' : 'badge-flat' }}">{{ $isEnabled }} </span> <span class="mx-1"> </span>
                                <span class="badge badge-sm {{ $isPreviewable === 'Previewable' ? 'badge-success' : 'badge-flat' }}">{{ $isPreviewable }}</span>
                                    @if(--$totalActions > 0)
                                       <hr class="my-2">
                                    @endif
                            @endforeach
                        </td>
                        <td class="text-right">
                            <div class="flex items-end justify-end gap-2 flex-wrap flex-col ">
                                @foreach($form['actions_list'] as $actionName => $actionData)
                                    @if(isset($actionData['enabled']) && $actionData['enabled'] && is_a($actionName, \Kreatif\StatamicForms\Contracts\FormActionInterface::class, true) && $actionName::isPreviewable())
                                        <a href="{{ cp_route('kreatif-forms.preview.action', ['formHandle' => $form['handle'], 'actionClass' => $actionName]) }}"
                                           target="_blank"
                                           class="btn btn-sm">
                                            Preview {{ $actionName::name() }} Email
                                        </a>
                                    @endif
                                @endforeach
                                <a href="{{ cp_route('kreatif-forms.preview.template', $form['handle']) }}"
                                   class="btn btn-sm btn-primary">
                                    Template Editor
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded">
        <h3 class="font-semibold text-blue-900 mb-2">How to use Email Preview</h3>
        <ul class="text-sm text-blue-800 space-y-1 ml-6 list-disc list-inside">
            <li><strong>Preview Admin Email:</strong> Opens the admin notification email in a new tab with sample data
            </li>
            <li><strong>Preview Autoresponder:</strong> Opens the autoresponder email in a new tab with sample data</li>
            <li><strong>Template Editor:</strong> Opens an interactive editor where you can customize sample data and
                preview both email types
            </li>
            <li>Add <code>?format=text</code> to any preview URL to see the plain text version</li>
        </ul>
    </div>
@endsection
