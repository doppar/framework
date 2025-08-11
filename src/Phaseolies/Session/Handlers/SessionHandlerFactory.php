<?php

namespace Phaseolies\Session\Handlers;

use Phaseolies\Session\Handlers\FileSessionHandler;
use Phaseolies\Session\Handlers\CookieSessionHandler;
use Phaseolies\Session\Contracts\SessionHandlerInterface;

class SessionHandlerFactory
{
    /**
     * Create and return an instance of a session handler based on the specified driver.
     *
     * @param string $driver The name of the session driver (e.g., 'file', 'cookie').
     * @param array $config Configuration options to pass to the session handler.
     * @return SessionHandlerInterface The appropriate session handler instance.
     * @throws \RuntimeException if an unsupported driver is provided.
     */
    public static function create(string $driver, array $config): SessionHandlerInterface
    {
        switch ($driver) {
            case 'cookie':
                return new CookieSessionHandler($config);
            case 'file':
                return new FileSessionHandler($config);
            default:
                throw new \RuntimeException("Unsupported session driver: {$driver}");
        }
    }
}
