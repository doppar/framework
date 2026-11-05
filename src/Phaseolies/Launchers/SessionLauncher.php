<?php

namespace Phaseolies\Launchers;

use Phaseolies\Session\ConfigSession as Session;

class SessionLauncher extends ServiceLauncher
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Session::configAppSession();
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
