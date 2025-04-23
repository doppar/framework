<?php

namespace Phaseolies\Providers;

use Phaseolies\Support\Router;
use Dotenv\Dotenv;

/**
 * EnvServiceProvider is responsible for registering and bootstrapping
 * the application's configuration functionality.
 */
class EnvServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method is called when the service provider is registered.
     * It binds the Router instance as a singleton into the service container,
     * ensuring that the same Router instance is reused throughout the application.
     *
     * @return void
     */
    public function register()
    {
        $dotenv = Dotenv::createImmutable($this->app->basePath());
        $dotenv->load();
    }

    /**
     * Bootstrap any application services.
     *
     * This method is called after all service providers have been registered.
     * It is used to load the application's route definitions from the `routes/web.php` file.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
