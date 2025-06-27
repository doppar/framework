<?php

namespace Phaseolies\Session\Handlers;

use Phaseolies\Session\Contracts\SessionHandlerInterface;
use Phaseolies\Config\Config;

class SessionManager
{
    /**
     * A static property to hold the session handler instance
     *
     * @var SessionHandlerInterface|null|null
     */
    private static ?SessionHandlerInterface $handler = null;

    /**
     * Initializes the session system.
     *
     * - Skips initialization if running in CLI mode.
     * - Loads session configuration.
     * - Creates the appropriate session handler using the factory.
     * - Initializes and starts the session.
     */
    public static function initialize(): void
    {
        if (php_sapi_name() === 'cli' || defined('STDIN')) {
            return;
        }

        $sessionConfig = (array) Config::get('session');

        self::$handler = SessionHandlerFactory::create($sessionConfig['driver'], $sessionConfig);
        self::$handler->initialize();
        self::$handler->start();
    }

    /**
     * Returns the currently initialized session handler.
     *
     * @return SessionHandlerInterface
     * @throws \RuntimeException if the handler hasn't been initialized yet.
     */
    public static function getHandler(): SessionHandlerInterface
    {
        if (self::$handler === null) {
            throw new \RuntimeException("Session handler not initialized");
        }
        return self::$handler;
    }
}
