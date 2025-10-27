@extends('statamic::layout')

@section('title', 'Template Preview - ' . $form->title())

@section('content')
    <header class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1>Template Preview: {{ $form->title() }}</h1>
                <p class="text-gray-700">Edit sample data and preview email templates in real-time.</p>
            </div>
            <a href="{{ cp_route('kreatif-forms.preview.index') }}" class="btn">
                &larr; Back to List
            </a>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column: Sample Data Editor -->
        <div class="card p-6">
            <h2 class="text-lg font-semibold mb-4">Sample Submission Data</h2>

            <form id="preview-form" method="GET" action="{{ cp_route('kreatif-forms.preview.template', $form->handle()) }}">
                <div class="space-y-4">
                    @foreach($sampleData as $key => $value)
                        <div>
                            <label for="field_{{ $key }}" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ ucfirst(str_replace('_', ' ', $key)) }}
                            </label>
                            @if(is_array($value))
                                <input type="text"
                                       id="field_{{ $key }}"
                                       name="submission_data[{{ $key }}]"
                                       value="{{ json_encode($value) }}"
                                       class="input input-text w-full">
                                <p class="text-xs text-gray-500 mt-1">Array values (comma-separated)</p>
                            @elseif(is_bool($value))
                                <select id="field_{{ $key }}"
                                        name="submission_data[{{ $key }}]"
                                        class="input input-select w-full">
                                    <option value="1" {{ $value ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ !$value ? 'selected' : '' }}>No</option>
                                </select>
                            @elseif(strlen($value) > 100)
                                <textarea id="field_{{ $key }}"
                                          name="submission_data[{{ $key }}]"
                                          rows="3"
                                          class="input input-text w-full">{{ $value }}</textarea>
                            @else
                                <input type="text"
                                       id="field_{{ $key }}"
                                       name="submission_data[{{ $key }}]"
                                       value="{{ $value }}"
                                       class="input input-text w-full">
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex justify-between items-center">
                    <button type="button"
                            onclick="resetForm()"
                            class="btn btn-flat">
                        Reset to Default
                    </button>
                    <button type="submit"
                            class="btn btn-primary">
                        Update Preview
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Column: Preview Actions -->
        <div class="space-y-6">
            <!-- Admin Notification Preview -->
            @if(isset($config['actions']) && count($config['actions']) > 0)
                @foreach($config['actions'] as $actionClass => $actionSettings)
                    @php
                        $enabled = $actionSettings['enabled'] ?? false;
                        $isFormAction = is_a($actionClass, \Kreatif\StatamicForms\Contracts\FormActionInterface::class, true);
                        $isPreviewable = $isFormAction && $actionClass::isPreviewable();
                        $name = $isFormAction ? $actionClass::name() : class_basename($actionClass);
                    @endphp

                    <div class="card p-6">
                        <h2 class="text-lg font-semibold mb-4">{{$name}} Email</h2>
                        @if(is_array($actionSettings))
                            @foreach($actionSettings as $actionSettingKey => $actionSetting)
                                @if(in_array($actionSettingKey, ['enabled'])) @endif
                                <div class="mb-2">
                                    <span class="font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $actionSettingKey)) }}:</span>
                                    <span class="text-gray-600">{{ is_array($actionSetting) ? json_encode($actionSetting) : ($actionSetting ?: 'Not set') }}</span>
                                </div>
                            @endforeach
                        @endif
                        <div class="mt-4 pt-4 border-t flex space-x-2">
                            <a href="{{ cp_route('kreatif-forms.preview.action', ['formHandle'=> $form->handle(), 'actionClass' => $actionClass]) }}?{{ http_build_query(array_merge([request()->all()??[], 'submissionId' => $submissionId])) }}"
                               target="_blank"
                               class="btn btn-primary flex-1 text-center">
                                Preview HTML
                            </a>
                            <a href="{{ cp_route('kreatif-forms.preview.action', ['formHandle'=> $form->handle(), 'actionClass' => $actionClass]) }}?format=text&{{ http_build_query(array_merge([request()->all()??[], 'submissionId' => $submissionId]))   }}"
                               target="_blank"
                               class="btn flex-1 text-center">
                                Preview Text
                            </a>
                        </div>
                    </div>
                @endforeach
            @endif
            <!-- Configuration Info -->
            <div class="card p-6 bg-gray-50">
                <h3 class="font-semibold mb-3">Configuration Details</h3>
                <div class="text-sm text-gray-700 space-y-2">
                    <div>
                        <span class="font-medium">Form Handle:</span>
                        <code class="ml-2 px-2 py-1 bg-white rounded">{{ $form->handle() }}</code>
                    </div>
                    @foreach($config as $key => $value)
                        @if($key === 'actions') @continue @endif
                        <div>
                            <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                            <span class="ml-2">{{ is_array($value) ? json_encode($value) : ($value ?: 'Not set') }}</span>
                        </div>

                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        function resetForm() {
            if (confirm('Are you sure you want to reset all fields to default values?')) {
                window.location.href = '{{ cp_route('kreatif-forms.preview.template', $form->handle()) }}';
            }
        }
    </script>
@endsection
