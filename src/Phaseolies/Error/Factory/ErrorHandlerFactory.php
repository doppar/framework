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
        return [
            new JsonErrorHandler(),
            new CliErrorHandler(),
            new WebErrorHandler(),
        ];
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
