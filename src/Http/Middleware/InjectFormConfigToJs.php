<?php

namespace Kreatif\StatamicForms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Statamic\Statamic;

class InjectFormConfigToJs
{
    public function handle(Request $request, Closure $next)
    {
        if (Statamic::isCpRoute()) {
            // Inject form configuration into JavaScript
            $handlers = collect(config('kreatif-statamic-forms.handlers', []))
                ->map(function ($config, $handle) {
                    return [
                        'handle' => $handle,
                        'has_actions' => !empty($config['actions']),
                        'actions' => array_keys($config['actions'] ?? []),
                    ];
                });

            Statamic::provideToScript([
                'kreatif-forms-config' => [
                    'handlers' => $handlers,
                ],
            ]);
        }

        return $next($request);
    }
}
