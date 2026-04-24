<?php

namespace Tests\Unit\Logger;

use Phaseolies\Logger\LogService;
use Phaseolies\Logger\Contracts\LogHandlerInterface;
use Phaseolies\Logger\Exceptions\UnsupportedLogDriverException;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class LogServiceTest extends TestCase
{
    private LogService $logService;

    protected function setUp(): void
    {
        putenv('LOG_CHANNEL');
        $this->logService = new LogService();
    }

    protected function tearDown(): void
    {
        putenv('LOG_CHANNEL');
    }

    public function testGetLoggerReturnsMonologInstance()
    {
        $logger = $this->logService->getLogger('test_channel');

        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testGetLoggerUsesProvidedChannelName()
    {
        $logger = $this->logService->getLogger('my_channel');

        $this->assertEquals('my_channel', $logger->getName());
    }

    public function testGetLoggerDefaultsToStackChannel()
    {
        $logger = $this->logService->getLogger();

        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testAddHandlerAcceptsHandlerObject()
    {
        $handler = $this->createMock(LogHandlerInterface::class);
        $handler->expects($this->once())
            ->method('configureHandler')
            ->with($this->isInstanceOf(Logger::class), 'channel');

        $this->logService->addHandler($handler);
        $this->logService->getLogger('channel');
    }

    public function testMultipleHandlersAreEachConfigured()
    {
        $handler1 = $this->createMock(LogHandlerInterface::class);
        $handler1->expects($this->once())->method('configureHandler');

        $handler2 = $this->createMock(LogHandlerInterface::class);
        $handler2->expects($this->once())->method('configureHandler');

        $this->logService->addHandler($handler1);
        $this->logService->addHandler($handler2);
        $this->logService->getLogger('ch');
    }

    public function testResetClearsAllHandlers()
    {
        $handler = $this->createMock(LogHandlerInterface::class);
        $handler->expects($this->never())->method('configureHandler');

        $this->logService->addHandler($handler);
        $this->logService->reset();
        $this->logService->getLogger('ch');
    }

    public function testGetLoggerWithNoHandlersReturnsEmptyHandlerList()
    {
        $logger = $this->logService->getLogger('empty');

        $this->assertEmpty($logger->getHandlers());
    }

    public function testGetLoggerCalledTwiceReturnsFreshInstance()
    {
        $logger1 = $this->logService->getLogger('ch');
        $logger2 = $this->logService->getLogger('ch');

        $this->assertNotSame($logger1, $logger2);
    }

    public function testGetLoggerUsesEnvChannelWhenChannelNotProvided()
    {
        putenv('LOG_CHANNEL=stderr');

        $logger = $this->logService->getLogger();

        $this->assertSame('stderr', $logger->getName());
    }

    public function testHandlersCanBeAddedAgainAfterReset()
    {
        $handler = $this->createMock(LogHandlerInterface::class);
        $handler->expects($this->once())
            ->method('configureHandler')
            ->with($this->isInstanceOf(Logger::class), 'reset-channel');

        $this->logService->addHandler($handler);
        $this->logService->reset();
        $this->logService->addHandler($handler);

        $this->logService->getLogger('reset-channel');
    }
}
