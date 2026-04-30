<?php

namespace Tests\Unit\Logger;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Phaseolies\Logger\Contracts\AbstractHandler;

class AbstractHandlerFormattingTest extends TestCase
{
    public function testFormattedLogOutputIncludesContextData(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'doppar-log-');
        $logger = new Logger('testing');
        $handler = new class extends AbstractHandler {
        };

        $handler->handleConfiguration($logger, $logFile);

        $logger->info('Application is terminating.', [
            'response_status' => 200,
            'exception' => null,
        ]);

        $contents = file_get_contents($logFile);

        $this->assertStringContainsString('Application is terminating.', $contents);
        $this->assertStringContainsString('response_status', $contents);
        $this->assertStringContainsString('200', $contents);

        unlink($logFile);
    }
}
