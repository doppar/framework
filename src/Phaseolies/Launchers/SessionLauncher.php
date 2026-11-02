<?php

namespace Phaseolies\Launchers;

use Phaseolies\Session\SessionManager;

class SessionLauncher extends ServiceLauncher
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        SessionManager::initialize();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function launch()
    {
        //
    }
}
