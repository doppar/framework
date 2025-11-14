<?php

namespace Phaseolies\Logger;

use Phaseolies\Logger\Contracts\LogHandlerInterface;
use Monolog\ResettableInterface;
use Monolog\Logger;

class LogService implements ResettableInterface
{
    /**
     * The Monolog logger instance.
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Array of log handlers.
     *
     * @var LogHandlerInterface[]
     */
    protected array $handlers = [];

    /**
     * Adds a log handler.
     *
     * @param LogHandlerInterface $handler
     * @return void
     */
    public function addHandler(LogHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Configures and returns a Monolog logger instance with specific handlers.
     *
     * @param string|null $channel
     * @return Logger
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
     * @return void
     */
    public function reset(): void
    {
        $this->handlers = [];
    }
}
