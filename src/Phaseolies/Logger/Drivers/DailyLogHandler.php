<?php

namespace Phaseolies\Logger\Drivers;

use Phaseolies\Logger\Contracts\LogHandlerInterface;
use Phaseolies\Logger\Contracts\AbstractHandler;
use Monolog\Logger;

class DailyLogHandler extends AbstractHandler implements LogHandlerInterface
{
    /**
     * Configures the Monolog handler for daily logging.
     *
     * @param Logger $logger
     * @param string $channel
     * @return void
     */
    public function configureHandler(Logger $logger, string $channel): void
    {
        $path = base_path('storage/logs');

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $logFile = $path . '/' . date('Y_m_d') . '_doppar.log';

        if (!is_file($logFile)) {
            touch($logFile);
        }

        $this->handleConfiguration($logger, $logFile);
    }
}
