<?php

namespace Phaseolies\Session\Handlers;

use Phaseolies\Session\Contracts\SessionHandlerInterface;

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

        $config = (array) config('session');

        // Only set name for non-cookie drivers
        if ($config['driver'] !== 'cookie') {
            session_name($config['cookie']);
        }

        session_set_cookie_params([
            'lifetime' => $config['expire_on_close'] ? 0 : $config['lifetime'] * 60,
            'path' => $config['path'],
            'domain' => $config['domain'],
            'secure' => $config['secure'],
            'httponly' => $config['http_only'],
            'samesite' => $config['same_site']
        ]);

        self::$handler = SessionHandlerFactory::create($config['driver'], $config);
        self::$handler->initialize();
        self::$handler->start();
    }

    /**
     * Returns the currently initialized session handler.
     *
     * @return SessionHandlerInterface
     * @throws \RuntimeException
     */
    public static function getHandler(): SessionHandlerInterface
    {
        if (self::$handler === null) {
            throw new \RuntimeException("Session handler not initialized");
        }

        return self::$handler;
    }
}
