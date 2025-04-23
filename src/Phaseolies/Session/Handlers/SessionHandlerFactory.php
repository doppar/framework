<?php

namespace Phaseolies\Session\Handlers;

use Phaseolies\Session\Handlers\FileSessionHandler;
use Phaseolies\Session\Handlers\CookieSessionHandler;
use Phaseolies\Session\Contracts\SessionHandlerInterface;

class SessionHandlerFactory
{
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
