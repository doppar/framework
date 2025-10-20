<?php

namespace Phaseolies\Logger\Contracts;

use Monolog\Logger;

interface LogHandlerInterface
{
    /**
     * Configures the Monolog handler.
     *
     * @param Logger $logger
     * @param string $channel
     * @return void
     */
    public function configureHandler(Logger $logger, string $channel): void;
}
