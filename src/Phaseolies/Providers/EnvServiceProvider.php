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
     * @return void
     */
    public function boot()
    {
        //
    }
}
