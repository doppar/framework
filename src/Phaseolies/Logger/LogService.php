<?php

namespace Phaseolies\Logger;

use Phaseolies\Logger\Contracts\LogHandlerInterface;
use Monolog\ResettableInterface;
use Monolog\Logger;

/**
 * Log class provides a mechanism to configure and handle logging using Monolog.
 * It implements the ResettableInterface to allow resetting the logger state.
 */
class LogService implements ResettableInterface
{
    /**
     * @var Logger The Monolog logger instance.
     */
    protected Logger $logger;

    /**
     * @var LogHandlerInterface[] Array of log handlers.
     */
    protected array $handlers = [];

    /**
     * Adds a log handler.
     *
     * @param LogHandlerInterface $handler The log handler to add.
     * @return void
     */
    public function addHandler(LogHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Configures and returns a Monolog logger instance with specific handlers.
     *
     * This method sets up a logger with configured handlers.
     *
     * @param string|null $channel The logging channel.
     * @return Logger The configured Monolog logger instance.
     */
    public function getLogger(?string $channel = null): Logger
    {
        $channel = $channel ?? env('LOG_CHANNEL', 'stack');

        $this->logger = new Logger($channel);

        foreach ($this->handlers as $handler) {
            $handler->configureHandler($this->logger, $channel);
        }

        return $this->logger;
    }

    /**
     * Resets the logger state by clearing all handlers.
     *
     * This method is required by the ResettableInterface and allows for
     * resetting the logger to its initial state.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->handlers = [];
    }
}
