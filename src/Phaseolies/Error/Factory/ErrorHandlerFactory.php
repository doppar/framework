<?php

namespace Phaseolies\Error\Factory;

use Phaseolies\Error\Handlers\WebErrorHandler;
use Phaseolies\Error\Handlers\JsonErrorHandler;
use Phaseolies\Error\Handlers\CliErrorHandler;
use Phaseolies\Error\Contracts\ErrorHandlerInterface;

class ErrorHandlerFactory
{
    /**
     * Create and return all available error handlers.
     *
     * @return ErrorHandlerInterface[]
     */
    public static function createHandlers(): array
    {
        $handlers = [new CliErrorHandler()];

        if (PHP_SAPI !== 'cli' && !defined('STDIN')) {
            $handlers[] = new JsonErrorHandler();
            $handlers[] = new WebErrorHandler();
        }

        return $handlers;
    }

    /**
     * Return the first handler that supports the current context.
     *
     * @return ErrorHandlerInterface|null
     */
    public static function getSupportedHandler(): ?ErrorHandlerInterface
    {
        foreach (self::createHandlers() as $handler) {
            if ($handler->supports()) {
                return $handler;
            }
        }

        return null;
    }
}
