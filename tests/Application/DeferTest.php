<?php

namespace Tests\Unit\Application;

use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Phaseolies\Application;
use Phaseolies\Console\Console;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Phaseolies\Http\DispatchResult;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Support\DeferScope;
use Phaseolies\Support\Router;
use ReflectionClass;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
final class DeferTest extends TestCase
{
    private Application $app;

    /** @var array<int, array{0: \Throwable, 1: ?\Throwable}> */
    private array $reported = [];

    protected function setUp(): void
    {
        $this->app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $this->reported = [];
        $this->app->reportDeferredExceptionsUsing(function (\Throwable $failure, ?\Throwable $original): void {
            $this->reported[] = [$failure, $original];
        });
    }

    public function testCallbacksRunInLastInFirstOutOrder(): void
    {
        $order = [];

        $this->app->runInDeferScope(function () use (&$order) {
            $this->app->defer(function () use (&$order) { $order[] = 'first'; });
            $this->app->defer(function () use (&$order) { $order[] = 'second'; });
            $this->app->defer(function () use (&$order) { $order[] = 'third'; });
        });

        $this->assertSame(['third', 'second', 'first'], $order);
    }

    public function testCallbacksRunAfterSuccessAndReturnTheResult(): void
    {
        $ran = false;

        $result = $this->app->runInDeferScope(function () use (&$ran) {
            $this->app->defer(function () use (&$ran) { $ran = true; });

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertTrue($ran);
        $this->assertFalse($this->app->hasDeferScope());
    }

    public function testCallbacksRunAfterAnExceptionAndTheOriginalIsRethrown(): void
    {
        $ran = false;
        $original = new RuntimeException('original');

        try {
            $this->app->runInDeferScope(function () use (&$ran, $original) {
                $this->app->defer(function () use (&$ran) { $ran = true; });

                throw $original;
            });
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame($original, $e);
        }

        $this->assertTrue($ran);
        $this->assertFalse($this->app->hasDeferScope());
    }

    public function testOneFailingCallbackDoesNotPreventLaterCleanup(): void
    {
        $order = [];

        $this->app->runInDeferScope(function () use (&$order) {
            $this->app->defer(function () use (&$order) { $order[] = 'first'; });
            $this->app->defer(function () { throw new RuntimeException('cleanup failed'); });
            $this->app->defer(function () use (&$order) { $order[] = 'third'; });
        });

        $this->assertSame(['third', 'first'], $order);
        $this->assertCount(1, $this->reported);
        $this->assertSame('cleanup failed', $this->reported[0][0]->getMessage());
        $this->assertNull($this->reported[0][1]);
    }

    public function testCleanupFailureDoesNotReplaceTheOriginalException(): void
    {
        $original = new RuntimeException('original');

        try {
            $this->app->runInDeferScope(function () use ($original) {
                $this->app->defer(function () { throw new LogicException('cleanup failed'); });

                throw $original;
            });
            $this->fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            $this->assertSame($original, $e);
        }

        $this->assertCount(1, $this->reported);
        $this->assertSame($original, $this->reported[0][1]);
    }

    public function testDeferOutsideAScopeFailsClearly(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('outside an active execution scope');

        $this->app->defer(fn() => null);
    }

    public function testNestedScopesHaveIndependentStacks(): void
    {
        $order = [];

        $this->app->runInDeferScope(function () use (&$order) {
            $this->app->defer(function () use (&$order) { $order[] = 'outer'; });

            $this->app->runInDeferScope(function () use (&$order) {
                $this->app->defer(function () use (&$order) { $order[] = 'inner'; });
            });

            $order[] = 'after-inner';
        });

        $this->assertSame(['inner', 'after-inner', 'outer'], $order);
    }

    public function testEndingAScopeEndsAbandonedInnerScopesFirst(): void
    {
        $order = [];

        $outer = $this->app->beginDeferScope();
        $this->app->defer(function () use (&$order) { $order[] = 'outer'; });
        $this->app->beginDeferScope();
        $this->app->defer(function () use (&$order) { $order[] = 'abandoned'; });

        $this->app->endDeferScope($outer);

        $this->assertSame(['abandoned', 'outer'], $order);
        $this->assertFalse($this->app->hasDeferScope());
    }

    public function testCallbacksRegisteredDuringCleanupAreRun(): void
    {
        $order = [];

        $this->app->runInDeferScope(function () use (&$order) {
            $this->app->defer(function () use (&$order) {
                $order[] = 'outer';
                $this->app->defer(function () use (&$order) { $order[] = 'late'; });
            });
        });

        $this->assertSame(['outer', 'late'], $order);
    }

    public function testCallbackEndingItsOwnScopeDoesNotCorruptTheStack(): void
    {
        $order = [];

        $outer = $this->app->beginDeferScope();
        $inner = $this->app->beginDeferScope();
        $this->app->defer(function () use (&$order, $inner) {
            $order[] = 'first';
            $this->app->endDeferScope($inner);
        });
        $this->app->defer(function () use (&$order) { $order[] = 'second'; });

        $this->app->endDeferScope($outer);

        $this->assertSame(['second', 'first'], $order);
        $this->assertFalse($this->app->hasDeferScope());
    }

    public function testScopeRunsEachCallbackOnlyOnce(): void
    {
        $count = 0;
        $scope = new DeferScope();
        $scope->push(function () use (&$count) { $count++; });

        $scope->run();
        $scope->run();

        $this->assertSame(1, $count);
        $this->assertTrue($scope->isClosed());
    }

    public function testPersistentWorkerDoesNotLeakCallbacksBetweenExecutions(): void
    {
        $runs = [];

        foreach ([1, 2, 3] as $job) {
            $captured = "payload-{$job}";

            $this->app->runInDeferScope(function () use ($job, $captured, &$runs) {
                $this->app->defer(function () use ($job, $captured, &$runs) {
                    $runs[] = [$job, $captured];
                });
            });

            $this->assertFalse($this->app->hasDeferScope());
        }

        $this->assertSame([[1, 'payload-1'], [2, 'payload-2'], [3, 'payload-3']], $runs);
    }

    public function testFailedExecutionDoesNotLeakIntoTheNextOne(): void
    {
        $runs = [];

        try {
            $this->app->runInDeferScope(function () use (&$runs) {
                $this->app->defer(function () use (&$runs) { $runs[] = 'failed-job'; });

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        $this->app->runInDeferScope(function () use (&$runs) {
            $this->app->defer(function () use (&$runs) { $runs[] = 'next-job'; });
        });

        $this->assertSame(['failed-job', 'next-job'], $runs);
    }

    public function testHttpDispatchRunsDeferredCallbacksOnTerminate(): void
    {
        $request = new Request();

        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'send'])
            ->getMock();
        $response->method('prepare')->willReturnSelf();
        $response->method('send')->willReturnSelf();

        $ran = [];

        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturnCallback(function () use ($response, &$ran) {
            $this->app->defer(function () use (&$ran) { $ran[] = 'first'; });
            $this->app->defer(function () use (&$ran) { $ran[] = 'second'; });

            return $response;
        });
        $this->app->router = $router;

        $result = $this->app->dispatch($request);

        $this->assertInstanceOf(DispatchResult::class, $result);
        $this->assertSame([], $ran);
        // Suspended until termination, so nothing else can leak into it.
        $this->assertFalse($this->app->hasDeferScope());

        $result->terminate();

        $this->assertSame(['second', 'first'], $ran);
        $this->assertFalse($this->app->hasDeferScope());
    }

    public function testHttpDispatchRunsDeferredCallbacksWhenHandlingThrows(): void
    {
        $ran = false;

        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturnCallback(function () use (&$ran) {
            $this->app->defer(function () use (&$ran) { $ran = true; });

            throw new RuntimeException('boom');
        });
        $this->app->router = $router;

        try {
            $this->app->dispatch(new Request());
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException) {
        }

        $this->assertTrue($ran);
        $this->assertFalse($this->app->hasDeferScope());
    }

    public function testConsoleRunExecutesDeferredCallbacksAfterTheCommand(): void
    {
        $order = [];
        $app = $this->app;

        $console = new class($app) extends Console {
            protected function bootstrapCommands(): void {}
        };
        $console->setCatchExceptions(false);

        $command = new Command('defer:test');
        $command->setCode(function () use ($app, &$order): int {
            $app->defer(function () use (&$order) { $order[] = 'cleanup'; });
            $order[] = 'command';

            return Command::SUCCESS;
        });
        $console->addCommand($command);

        $status = $console->run(new ArrayInput(['command' => 'defer:test']), new NullOutput());

        $this->assertSame(0, $status);
        $this->assertSame(['command', 'cleanup'], $order);
        $this->assertFalse($app->hasDeferScope());
    }

    private function makeResponse(): Response
    {
        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'send'])
            ->getMock();
        $response->method('prepare')->willReturnSelf();
        $response->method('send')->willReturnSelf();

        return $response;
    }

    public function testOverlappingDispatchesDoNotCleanUpEachOther(): void
    {
        $ran = [];
        $calls = 0;

        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturnCallback(function () use (&$ran, &$calls) {
            $name = 'request-' . (++$calls);
            $this->app->defer(function () use (&$ran, $name) { $ran[] = $name; });

            return $this->makeResponse();
        });
        $this->app->router = $router;

        $first = $this->app->dispatch(new Request());
        $second = $this->app->dispatch(new Request());

        $this->assertSame([], $ran);
        $this->assertFalse($this->app->hasDeferScope());

        $first->terminate();
        $this->assertSame(['request-1'], $ran);

        $second->terminate();
        $this->assertSame(['request-1', 'request-2'], $ran);
    }

    public function testTerminatingCallbacksCanDeferIntoTheRequestScope(): void
    {
        $order = [];

        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturnCallback(fn() => $this->makeResponse());
        $this->app->router = $router;

        $this->app->terminating(function () use (&$order) {
            $order[] = 'terminating';
            $this->app->defer(function () use (&$order) { $order[] = 'deferred-in-terminating'; });
        });

        $this->app->dispatch(new Request())->terminate();

        $this->assertSame(['terminating', 'deferred-in-terminating'], $order);
        $this->assertFalse($this->app->hasDeferScope());
    }

    public function testDeferAfterDispatchButBeforeTerminateFailsClearly(): void
    {
        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturnCallback(fn() => $this->makeResponse());
        $this->app->router = $router;

        $result = $this->app->dispatch(new Request());

        try {
            $this->expectException(LogicException::class);
            $this->app->defer(fn() => null);
        } finally {
            $result->terminate();
        }
    }

    public function testHttpExceptionPathStillRunsCallbacksWhateverRenderingDoes(): void
    {
        $ran = false;

        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturnCallback(function () use (&$ran) {
            $this->app->defer(function () use (&$ran) { $ran = true; });

            throw new HttpException(418, 'teapot');
        });
        $this->app->router = $router;

        // Rendering an error page can succeed (an error view exists) or throw
        // (it does not, or the environment is not bootstrapped). Both must end
        // with cleanup run and no scope left behind.
        $level = ob_get_level();
        ob_start();
        $result = null;

        try {
            $result = $this->app->dispatch(new Request());
        } catch (\Throwable) {
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        $result?->terminate();

        $this->assertTrue($ran);
        $this->assertFalse($this->app->hasDeferScope());
    }

    public function testRenderFailureInHttpExceptionPathEndsTheScope(): void
    {
        $ran = false;

        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturnCallback(function () use (&$ran) {
            $this->app->defer(function () use (&$ran) { $ran = true; });

            throw new HttpException(599, 'no page for this status');
        });
        $this->app->router = $router;

        // No bootstrapped application: rendering the error page throws.
        \Phaseolies\DI\Container::forgetInstance();

        ob_start();

        try {
            $this->app->dispatch(new Request());
            $this->fail('Expected the rendering failure to propagate.');
        } catch (\Throwable) {
        } finally {
            ob_end_clean();
        }

        $this->assertTrue($ran);
        $this->assertFalse($this->app->hasDeferScope());
    }

    public function testConsoleCallbacksSeeTheExceptionTheCommandThrows(): void
    {
        $app = $this->app;
        $reported = [];
        $app->reportDeferredExceptionsUsing(function (\Throwable $failure, ?\Throwable $original) use (&$reported) {
            $reported[] = $original;
        });

        $console = new class($app) extends Console {
            protected function bootstrapCommands(): void {}
        };
        $console->setCatchExceptions(false);

        $boom = new RuntimeException('command failed');
        $command = new Command('defer:fail');
        $command->setCode(function () use ($app, $boom): int {
            $app->defer(function () { throw new LogicException('cleanup failed too'); });

            throw $boom;
        });
        $console->addCommand($command);

        try {
            $console->run(new ArrayInput(['command' => 'defer:fail']), new NullOutput());
            $this->fail('Expected the command exception to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame($boom, $e);
        }

        $this->assertSame([$boom], $reported);
        $this->assertFalse($app->hasDeferScope());
    }

    public function testNestedConsoleCommandsHaveIndependentScopes(): void
    {
        $app = $this->app;
        $order = [];

        $console = new class($app) extends Console {
            protected function bootstrapCommands(): void {}
        };

        $inner = new Command('defer:inner');
        $inner->setCode(function () use ($app, &$order): int {
            $app->defer(function () use (&$order) { $order[] = 'inner-cleanup'; });

            return Command::SUCCESS;
        });

        $outer = new Command('defer:outer');
        $outer->setCode(function () use ($app, $console, &$order): int {
            $app->defer(function () use (&$order) { $order[] = 'outer-cleanup'; });
            $console->doRun(new ArrayInput(['command' => 'defer:inner']), new NullOutput());
            $order[] = 'after-inner';

            return Command::SUCCESS;
        });

        $console->addCommand($inner);
        $console->addCommand($outer);
        $console->setAutoExit(false);
        $console->doRun(new ArrayInput(['command' => 'defer:outer']), new NullOutput());

        $this->assertSame(['inner-cleanup', 'after-inner', 'outer-cleanup'], $order);
    }

    public function testCleanupFailuresAreWrittenToTheLogService(): void
    {
        $logged = [];
        $logger = new class($logged) {
            public function __construct(public array &$logged) {}

            public function error(string $message, array $context = []): void
            {
                $this->logged[] = [$message, $context];
            }
        };

        $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->bind('log', fn() => $logger);
        $app->bind('app', fn() => $app);
        \Phaseolies\Facade\BaseFacade::setFacadeApplication($app);

        try {
            $failure = new RuntimeException('cleanup failed');

            $app->runInDeferScope(function () use ($app, $failure) {
                $app->defer(function () use ($failure) { throw $failure; });
            });
        } finally {
            \Phaseolies\Facade\BaseFacade::setFacadeApplication(null);
        }

        $this->assertCount(1, $logged);
        $this->assertSame('Deferred callback failed: cleanup failed', $logged[0][0]);
        $this->assertSame($failure, $logged[0][1]['exception']);
    }
}
