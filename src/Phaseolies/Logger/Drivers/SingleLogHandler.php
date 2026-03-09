<?php

namespace Phaseolies\Logger\Drivers;

use Phaseolies\Logger\Contracts\LogHandlerInterface;
use Phaseolies\Logger\Contracts\AbstractHandler;
use Monolog\Logger;

class SingleLogHandler extends AbstractHandler implements LogHandlerInterface
{
    /**
     * Configures the Monolog handler for default logging.
     *
     * @param Logger $logger
     * @param string $channel
     * @return void
     */
    public function configureHandler(Logger $logger, string $channel): void
    {
        $path = base_path('storage' . DIRECTORY_SEPARATOR . 'logs');

        if (!is_dir($path)) {
            if (!mkdir($path, 0775, true) && !is_dir($path)) {
                throw new \RuntimeException('Unable to create log directory: ' . $path);
            }
        }

        if (!is_writable($path)) {
            throw new \RuntimeException('Log directory is not writable: ' . $path);
        }

        $logFile = $path . DIRECTORY_SEPARATOR . 'doppar.log';

        if (!is_file($logFile)) {
            if (touch($logFile) === false) {
                throw new \RuntimeException('Unable to create log file: ' . $logFile);
            }

            chmod($logFile, 0664);
        }

        $this->handleConfiguration($logger, $logFile);
    }
}
