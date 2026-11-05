<?php

namespace Tests\Logger;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Phaseolies\DI\Container;
use Phaseolies\Support\LoggerService;
use PHPUnit\Framework\TestCase;

final class ExecutionContextLoggingTest extends TestCase
{
    private Container $container;

    private TestHandler $handler;

    private LoggerService $log;

    protected function setUp(): void
    {
        $this->container = new Container();
        Container::setInstance($this->container);

        $this->handler = new TestHandler();
        $handler = $this->handler;

        $this->log = new class($handler) extends LoggerService {
            public function __construct(private TestHandler $handler) {}

            protected function reader(): Logger
            {
                return new Logger('test', [$this->handler]);
            }
        };
    }

    protected function tearDown(): void
    {
        Container::forgetInstance();
    }

    public function testRecordsOutsideAContextAreUnchanged(): void
    {
        $this->log->info('plain', ['a' => 1]);

        $this->assertSame(['a' => 1], $this->handler->getRecords()[0]->context);
    }

    public function testRecordsCarryTheActiveExecutionContext(): void
    {
        $this->container->runInContext('request', ['path' => '/pay', 'tenant' => 5], function () {
            $id = $this->container->executionContext()->id();

            $this->log->error('failed', ['order' => 10]);

            $context = $this->handler->getRecords()[0]->context;

            $this->assertSame(10, $context['order']);
            $this->assertSame($id, $context['execution']['id']);
            $this->assertSame('request', $context['execution']['type']);
            $this->assertSame(5, $context['execution']['metadata']['tenant']);
        });
    }

    public function testMetadataAddedMidRequestShowsUpInLaterRecords(): void
    {
        $this->container->runInContext('request', [], function () {
            $this->log->info('before');
            $this->container->stampContext(['tenant' => 'acme']);
            $this->log->info('after');
        });

        [$before, $after] = $this->handler->getRecords();

        $this->assertArrayNotHasKey('tenant', $before->context['execution']['metadata']);
        $this->assertSame('acme', $after->context['execution']['metadata']['tenant']);
    }

    public function testNextExecutionDoesNotInheritThePreviousOnesMetadata(): void
    {
        $this->container->runInContext('request', ['tenant' => 'one'], fn() => $this->log->info('first'));
        $this->container->runInContext('request', [], fn() => $this->log->info('second'));

        [$first, $second] = $this->handler->getRecords();

        $this->assertSame('one', $first->context['execution']['metadata']['tenant']);
        $this->assertArrayNotHasKey('tenant', $second->context['execution']['metadata']);
        $this->assertNotSame($first->context['execution']['id'], $second->context['execution']['id']);
    }

    public function testCallerSuppliedExecutionKeyWins(): void
    {
        $this->container->runInContext('request', [], function () {
            $this->log->info('custom', ['execution' => 'mine']);
        });

        $this->assertSame('mine', $this->handler->getRecords()[0]->context['execution']);
    }
}
