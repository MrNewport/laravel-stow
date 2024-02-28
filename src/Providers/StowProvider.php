<?php

namespace MrNewport\LaravelStow\Providers;

use Illuminate\Support\ServiceProvider;

class StowProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Publish the config file to the installer's config directory
        $this->publishes([
            __DIR__.'/../config/stow.php' => config_path('stow.php'),
        ], 'stow-config');

        // Register the migrations for next `php artisan migrate` execution
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register the Event Listeners
        $this->app->register(EventServiceProvider::class);
    }
}
