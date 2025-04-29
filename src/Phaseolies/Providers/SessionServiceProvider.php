<?php

namespace Phaseolies\Providers;

use Phaseolies\Session\ConfigSession;

class SessionServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
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
     * @return void
     */
    public function boot()
    {
        //
    }
}
