<?php

namespace Phaseolies\Logger\Drivers;

use Phaseolies\Logger\Contracts\LogHandlerInterface;
use Phaseolies\Logger\Contracts\AbstractHandler;
use Monolog\Logger;

/**
 * Default log handler.
 */
class DefaultLogHandler extends AbstractHandler implements LogHandlerInterface
{
    /**
     * Configures the Monolog handler for default logging.
     *
     * @param Logger $logger The Monolog logger instance.
     * @param string $channel The logging channel.
     * @return void
     */
    public function configureHandler(Logger $logger, string $channel): void
    {
        $path = base_path() . '/storage/logs';

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $logFile = $path . '/doppar.log';

        if (!is_file($logFile)) {
            touch($logFile);
        }

        $this->handleConfiguration($logger, $logFile);
    }
}
