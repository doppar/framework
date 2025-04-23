<?php

namespace Phaseolies\Session;

use Phaseolies\Session\Handlers\SessionManager;

class ConfigSession
{
    /**
     * Configures the application session by initializing the SessionManager.
     *
     * This static method provides a single entry point to set up session handling
     * for the application. It delegates the actual initialization work to the
     * SessionManager class.
     *
     * @return void
     */
    public static function configAppSession(): void
    {
        SessionManager::initialize();
    }
}
