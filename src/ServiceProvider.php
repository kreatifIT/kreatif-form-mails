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

        // Extend form edit blueprint
        $this->extendFormEditBlueprint();

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

    /**
     * Extend the form edit blueprint to add kreatif_actions field to each email configuration
     */
    protected function extendFormEditBlueprint()
    {
        // Use a view composer to extend the form edit blueprint
        $this->app['view']->composer('statamic::forms.edit', function ($view) {
            $blueprint = $view->getData()['blueprint'] ?? null;

            if (!$blueprint) {
                return;
            }
            $filterEmailTabSectionIdx = array_filter($blueprint['tabs'], function ($tab) {
                return $tab['handle'] == 'email';
            });
            if (count($filterEmailTabSectionIdx) === 0) {
                return;
            }
            $filterEmailTabSectionIdx = array_keys($filterEmailTabSectionIdx)[0];
            $filterEmailTabSection = ($blueprint['tabs'][$filterEmailTabSectionIdx]) ?? ($blueprint['tabs'][''.$filterEmailTabSectionIdx]) ?? null;
            // Find and extend the email grid field
            if (isset($filterEmailTabSection['sections'])) {
                $sections = $filterEmailTabSection['sections'];
                foreach ($sections as $sectionIndex => &$section) {
                    foreach ($section['fields'] as &$field) {
                        if (isset($field['fields'])) {
                            // Define all available mailableActins
                            $mailableActins = array_keys(config('kreatif-statamic-forms.actions.mailables', []));
                            // Add kreatif_send_mail_actions checkboxes field

                            // Add configuration fields for each action
                            foreach ($mailableActins as $actionClass) {
                                $actionConfigFields = $actionClass::configFields();
                                if (!empty($actionConfigFields)) {
                                    array_unshift($field['fields'], ...$this->getMailableActionFieldsConfigs($actionClass));
                                }
                            }

                            array_unshift($field['fields'], $this->getEmailsActionsConfigField($mailableActins));
                            // $field['fields'][] = $this->getEmailsActionsConfigField($mailableActins);

                            $sections[$sectionIndex] = $section;
                        }
                    }
                    $blueprint['tabs'][$filterEmailTabSectionIdx]['sections'] = $sections;
                }

                $view->with('blueprint', $blueprint);
            }
        });
    }

    private function getEmailsActionsConfigField(array $MailableActionsClasses): array
    {
        return [
            "display" => __("kreatif-forms::forms.email_configuration.field_title"),
            "hide_display" => false,
            "handle" => "kreatif_send_mail_actions",
            "instructions" => __("kreatif-forms::forms.email_configuration.field_instructions"),
            "instructions_position" => "above",
            "listable" => "hidden",
            "sortable" => true,
            "visibility" => "visible",
            "replicator_preview" => true,
            "duplicate" => true,
            "type" => "checkboxes",
            "options" => array_combine(
                $MailableActionsClasses,
                array_map(fn($action) => $action::name(), $MailableActionsClasses)
            ),
            "inline" => false,
            "default" => [],
            "component" => "checkboxes",
            "prefix" => null,
            "required" => false,
            "read_only" => false,
            "always_save" => false,
        ];
    }

    private function getMailableActionFieldsConfigs(string $actionClass): array
    {
        $actionConfigFields = $actionClass::configFields();
        $actionHandle = str_replace('\\', '_', $actionClass);
        $fields = [];
        $fields[] = [
            "display" => $actionClass::name(),
            "handle" => $actionHandle."_divider",
            "type" => "section",
            "instructions" => __("kreatif-forms::forms.action_config.section_instructions"),
            "if" => [
                "kreatif_send_mail_actions" => "contains {$actionClass}",
            ],
        ];

        // Add each configuration field for this action
        foreach ($actionConfigFields as $configField) {
            $configField["handle"] = $actionHandle."_".$configField["handle"];
            $configField["if"] = [
                "kreatif_send_mail_actions" => "contains {$actionClass}",
            ];
            $configField["instructions_position"] = $configField["instructions_position"] ?? "above";
            $configField["listable"] = $configField["listable"] ?? "hidden";
            $configField["sortable"] = $configField["sortable"] ?? true;
            $configField["visibility"] = $configField["visibility"] ?? "visible";
            $configField["replicator_preview"] = $configField["replicator_preview"] ?? true;
            $configField["duplicate"] = $configField["duplicate"] ?? true;
            $configField["component"] = $configField["component"] ?? $configField["type"];
            $configField["prefix"] = $configField["prefix"] ?? null;
            $configField["required"] = $configField["required"] ?? false;
            $configField["read_only"] = $configField["read_only"] ?? false;
            $configField["always_save"] = $configField["always_save"] ?? false;

            $fields[] = $configField;
        }

        return $fields;
    }

}
