<?php

namespace Tests\Unit\Logger;

use Phaseolies\DI\Container;
use Phaseolies\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoggerHelperTest extends TestCase
{
    private FakeLogger $logger;

    protected function setUp(): void
    {
        $container = new Container();
        $this->logger = new FakeLogger();

        $container->instance('log', $this->logger);
        Log::setFacadeApplication(null);
    }

    #[DataProvider('helperProvider')]
    public function testLogHelpersForwardPayloadAndContext(string $helper, string $expectedLevel): void
    {
        $payload = ['message' => 'hello'];
        $context = ['request_id' => 'abc-123'];

        $helper($payload, $context);

        $this->assertSame([
            'level' => $expectedLevel,
            'message' => $payload,
            'context' => $context,
        ], $this->logger->lastCall);
    }

    #[DataProvider('helperProvider')]
    public function testLogHelpersDefaultContextToEmptyArray(string $helper, string $expectedLevel): void
    {
        $helper('plain message');

        $this->assertSame([
            'level' => $expectedLevel,
            'message' => 'plain message',
            'context' => [],
        ], $this->logger->lastCall);
    }

    public static function helperProvider(): array
    {
        return [
            'info' => ['\\info', 'info'],
            'warning' => ['\\warning', 'warning'],
            'error' => ['\\error', 'error'],
            'alert' => ['\\alert', 'alert'],
            'notice' => ['\\notice', 'notice'],
            'emergency' => ['\\emergency', 'emergency'],
            'critical' => ['\\critical', 'critical'],
            'debug' => ['\\debug', 'debug'],
        ];
    }
}

class FakeLogger
{
    public array $lastCall = [];

    public function info(mixed $message, array $context = []): void
    {
        $this->record('info', $message, $context);
    }

    public function warning(mixed $message, array $context = []): void
    {
        $this->record('warning', $message, $context);
    }

    public function error(mixed $message, array $context = []): void
    {
        $this->record('error', $message, $context);
    }

    public function alert(mixed $message, array $context = []): void
    {
        $this->record('alert', $message, $context);
    }

    public function notice(mixed $message, array $context = []): void
    {
        $this->record('notice', $message, $context);
    }

    public function emergency(mixed $message, array $context = []): void
    {
        $this->record('emergency', $message, $context);
    }

    public function critical(mixed $message, array $context = []): void
    {
        $this->record('critical', $message, $context);
    }

    public function debug(mixed $message, array $context = []): void
    {
        $this->record('debug', $message, $context);
    }

    private function record(string $level, mixed $message, array $context): void
    {
        $this->lastCall = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
