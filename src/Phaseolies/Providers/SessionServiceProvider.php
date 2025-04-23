<?php

namespace Phaseolies\Providers;

use Phaseolies\Session\ConfigSession;

/**
 * SessionServiceProvider is responsible for registering and bootstrapping
 * the application's session functionality.
 */
class SessionServiceProvider extends ServiceProvider
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
        ConfigSession::configAppSession();
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
