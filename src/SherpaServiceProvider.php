<?php

namespace CompanyHike\Sherpa;

use Illuminate\Support\ServiceProvider;

class SherpaServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'companyhike');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'companyhike');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sherpa.php', 'sherpa');

        // Register the service the package provides.
        $this->app->singleton('sherpa', function ($app) {
            return new Sherpa;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['sherpa'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/sherpa.php' => config_path('sherpa.php'),
        ], 'sherpa.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/companyhike'),
        ], 'sherpa.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/companyhike'),
        ], 'sherpa.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/companyhike'),
        ], 'sherpa.views');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
