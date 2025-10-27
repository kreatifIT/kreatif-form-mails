<?php

namespace Kreatif\StatamicForms;

use Kreatif\StatamicForms\Actions\PreviewEmailTemplatesAction;
use Kreatif\StatamicForms\Http\Controllers\CP\PreviewNavigation;
use Kreatif\StatamicForms\Listeners\HandleFormSubmission;
use Kreatif\StatamicForms\Services\FormProcessorService;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $listen = [
        \Statamic\Events\FormSubmitted::class => [
            HandleFormSubmission::class,
        ],
    ];

    protected $scripts = [
        // __DIR__.'/../resources/js/cp.js',
    ];

    public function register()
    {
        parent::register();
        $this->mergeConfigFrom(__DIR__.'/../config/kreatif-statamic-forms.php', 'kreatif-statamic-forms');
        $this->app->singleton(FormProcessorService::class);
    }

    public function bootAddon()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'kreatif-forms');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'kreatif-forms');

        // Register Permissions
        $this->registerPermissions();

        // Register CP Navigation
        PreviewNavigation::register();

        // Register Actions
        $this->registerActions();

        $this->publishes([
            __DIR__.'/../config/kreatif-statamic-forms.php' => config_path('kreatif-statamic-forms.php'),
        ], 'kreatif-forms-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/kreatif-forms'),
        ], 'kreatif-forms-views');
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/kreatif-forms'),
        ], 'kreatif-forms-lang');
    }

    protected function registerPermissions()
    {
        Permission::group('kreatif-forms', 'Kreatif Forms', function () {
            Permission::register('view kreatif-forms email previews', function ($permission) {
                $permission
                    ->label('View Email Previews')
                    ->description('View email template previews for forms')
                    ->children([
                        Permission::make('edit kreatif-forms settings')
                            ->label('Edit Form Settings')
                            ->description('Edit form configuration and settings'),
                    ]);
            });
        });
    }

    protected function registerActions()
    {
        PreviewEmailTemplatesAction::register();
    }

}
