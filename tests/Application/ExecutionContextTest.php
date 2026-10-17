<?php

namespace Tests\Application;

use Phaseolies\Application;
use Phaseolies\Console\Console;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Phaseolies\DI\Container;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Support\ExecutionContext;
use Phaseolies\Support\ExecutionService;
use Phaseolies\Support\Router;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[AllowMockObjectsWithoutExpectations]
final class ExecutionContextTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    // ---------------------------------------------------------------- value object

    public function testContextIsImmutableAndWithMetadataKeepsIdentity(): void
    {
        $context = ExecutionContext::create('request', ['path' => '/a']);
        $copy = $context->withMetadata(['tenant' => 7]);

        $this->assertNotSame($context, $copy);
        $this->assertSame($context->id(), $copy->id());
        $this->assertFalse($context->has('tenant'));
        $this->assertSame(7, $copy->get('tenant'));
        $this->assertSame('/a', $copy->get('path'));
    }

    public function testEmptyTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ExecutionContext::create('');
    }

    public function testChildInheritsAndOverridesParentMetadata(): void
    {
        $parent = ExecutionContext::create('request', ['tenant' => 1, 'path' => '/x']);
        $child = ExecutionContext::create('worker', ['tenant' => 2], $parent);

        $this->assertSame(2, $child->get('tenant'));
        $this->assertSame('/x', $child->get('path'));
        $this->assertSame($parent, $child->parent());
        $this->assertSame(1, $child->depth());
        $this->assertSame($parent->id(), $child->toArray()['parent_id']);
        $this->assertSame(1, $parent->get('tenant'), 'the parent must be untouched');
    }

    // ---------------------------------------------------------------- creation / nesting / teardown

    public function testNoContextIsActiveByDefault(): void
    {
        $this->assertNull($this->container->executionContext());
        $this->assertFalse($this->container->hasExecutionContext());
    }

    public function testBeginAndEndMaintainTheStack(): void
    {
        $outer = $this->container->beginContext('request');
        $inner = $this->container->beginContext('worker');

        $this->assertSame($inner, $this->container->executionContext());
        $this->assertSame($outer, $inner->parent());

        $this->container->endContext($inner);
        $this->assertSame($outer, $this->container->executionContext());

        $this->container->endContext($outer);
        $this->assertNull($this->container->executionContext());
    }

    public function testEndingAnOuterContextEndsNestedOnesFirst(): void
    {
        $order = [];
        $this->container->contextEnding(function (ExecutionContext $c) use (&$order) {
            $order[] = $c->type();
        });

        $outer = $this->container->beginContext('request');
        $this->container->beginContext('worker');

        $this->container->endContext($outer);

        $this->assertSame(['worker', 'request'], $order);
        $this->assertNull($this->container->executionContext());
    }

    public function testEndingTwiceIsANoOp(): void
    {
        $ended = 0;
        $this->container->contextEnding(function () use (&$ended) {
            $ended++;
        });

        $context = $this->container->beginContext('console');
        $this->container->endContext($context);
        $this->container->endContext($context);

        $this->assertSame(1, $ended);
    }

    public function testRunInContextEndsEvenWhenTheCallbackThrows(): void
    {
        try {
            $this->container->runInContext('console', [], function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('expected exception');
        } catch (\RuntimeException) {
        }

        $this->assertNull($this->container->executionContext());
    }

    public function testListenersFilterByTypeAndRunBeforeScopedInstancesAreDropped(): void
    {
        $this->container->bindExecution('svc', fn() => new \stdClass());

        $seen = [];
        $this->container->contextBeginning(function (ExecutionContext $c) use (&$seen) {
            $seen[] = 'begin:' . $c->type();
        }, 'request');
        $this->container->contextEnding(function (ExecutionContext $c) use (&$seen) {
            $seen[] = 'end:' . $c->type();
            $seen[] = get_class($this->container->get('svc'));
        });

        $this->container->runInContext('console', [], fn() => null);
        $this->container->runInContext('request', [], fn() => null);

        $this->assertSame(['end:console', \stdClass::class, 'begin:request', 'end:request', \stdClass::class], $seen);
    }

    public function testFailingEndListenerStillTearsTheContextDown(): void
    {
        $this->container->contextEnding(function () {
            throw new \RuntimeException('listener');
        });

        $context = $this->container->beginContext('request');

        try {
            $this->container->endContext($context);
            $this->fail('expected exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('listener', $e->getMessage());
        }

        $this->assertNull($this->container->executionContext());
    }

    public function testWithContextMetadataReplacesTheCurrentContextKeepingItsId(): void
    {
        $context = $this->container->beginContext('request');
        $updated = $this->container->stampContext(['tenant' => 'acme']);

        $this->assertSame($context->id(), $updated->id());
        $this->assertSame($updated, $this->container->executionContext());
        $this->assertFalse($context->has('tenant'), 'the original instance is immutable');

        $this->container->endContext($context);
    }

    public function testWithContextMetadataRequiresAnActiveContext(): void
    {
        $this->expectException(\LogicException::class);

        $this->container->stampContext(['a' => 1]);
    }

    public function testSuspendedContextCanBeResumedAndEnded(): void
    {
        $ended = 0;
        $this->container->contextEnding(function () use (&$ended) {
            $ended++;
        });

        $context = $this->container->beginContext('request');
        $this->container->suspendContext($context);

        $this->assertNull($this->container->executionContext());
        $this->assertSame(0, $ended);

        $this->container->resumeContext($context);
        $this->assertSame($context, $this->container->executionContext());

        $this->container->endContext($context);
        $this->assertSame(1, $ended);
    }

    public function testSuspendedContextWithoutScopedInstancesStillFiresEndListeners(): void
    {
        $ended = 0;
        $this->container->contextEnding(function () use (&$ended) {
            $ended++;
        });

        $context = $this->container->beginContext('request');
        $this->container->suspendContext($context);
        $this->container->endContext($context);

        $this->assertSame(1, $ended);
    }

    public function testEndingAParentEndsItsSuspendedChild(): void
    {
        $outer = $this->container->beginContext('outer');
        $inner = $this->container->beginContext('inner');
        $this->container->suspendContext($inner);

        $this->container->endContext($outer);
        $this->container->resumeContext($inner);

        $this->assertNull($this->container->executionContext());
        $this->assertFalse($this->container->hasExecutionContext());
    }

    public function testSuspendedChildEndListenersRunBeforeTheParentsAndOnlyOnce(): void
    {
        $order = [];
        $this->container->contextEnding(function (ExecutionContext $c) use (&$order) {
            $order[] = $c->type();
        });

        $outer = $this->container->beginContext('outer');
        $inner = $this->container->beginContext('inner');
        $this->container->suspendContext($inner);

        $this->container->endContext($outer);
        $this->container->endContext($inner); // already ended, must not fire again

        $this->assertSame(['inner', 'outer'], $order);
    }

    public function testEndingAParentEndsSuspendedGrandchildrenDeepestFirst(): void
    {
        $order = [];
        $this->container->contextEnding(function (ExecutionContext $c) use (&$order) {
            $order[] = $c->type();
        });

        $outer = $this->container->beginContext('outer');
        $middle = $this->container->beginContext('middle');
        $inner = $this->container->beginContext('inner');
        $this->container->suspendContext($inner);
        $this->container->suspendContext($middle);

        $this->container->endContext($outer);

        $this->assertSame(['inner', 'middle', 'outer'], $order);
        $this->container->resumeContext($inner);
        $this->container->resumeContext($middle);
        $this->assertNull($this->container->executionContext());
    }

    public function testEndingASuspendedParentAlsoEndsItsSuspendedChild(): void
    {
        $order = [];
        $this->container->contextEnding(function (ExecutionContext $c) use (&$order) {
            $order[] = $c->type();
        });

        $outer = $this->container->beginContext('outer');
        $inner = $this->container->beginContext('inner');
        $this->container->suspendContext($inner);
        $this->container->suspendContext($outer);

        $this->container->endContext($outer);

        $this->assertSame(['inner', 'outer'], $order);
    }

    public function testEndingAContextLeavesUnrelatedSuspendedContextsAlive(): void
    {
        $other = $this->container->beginContext('request');
        $this->container->suspendContext($other);

        $outer = $this->container->beginContext('outer');
        $this->container->endContext($outer);

        $this->container->resumeContext($other);
        $this->assertSame($other->id(), $this->container->executionContext()?->id());
    }

    public function testSuspendedChildScopedInstancesAreDroppedWithTheParent(): void
    {
        $this->container->bindExecution('scoped', fn () => new \stdClass());

        $outer = $this->container->beginContext('outer');
        $inner = $this->container->beginContext('inner');
        $first = $this->container->get('scoped');
        $this->container->suspendContext($inner);
        $this->container->endContext($outer);

        $this->container->resumeContext($inner); // ended, so nothing to resume

        $this->container->runInContext('again', [], function () use ($first) {
            $this->assertNotSame($first, $this->container->get('scoped'));
        });
    }

    public function testAFailingListenerForASuspendedChildStillEndsEverythingAndIsRethrown(): void
    {
        $ended = [];
        $this->container->contextEnding(function (ExecutionContext $c) use (&$ended) {
            $ended[] = $c->type();

            if ($c->type() === 'inner') {
                throw new \RuntimeException('inner listener failed');
            }
        });

        $outer = $this->container->beginContext('outer');
        $inner = $this->container->beginContext('inner');
        $this->container->suspendContext($inner);

        try {
            $this->container->endContext($outer);
            $this->fail('Expected the listener failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('inner listener failed', $e->getMessage());
        }

        $this->assertSame(['inner', 'outer'], $ended);
        $this->assertFalse($this->container->hasExecutionContext());
    }

    // ---------------------------------------------------------------- scopes

    public function testExecutionScopedServiceIsSharedWithinAContextAndNotAcrossContexts(): void
    {
        $this->container->bindExecution('svc', fn() => new \stdClass());

        $first = $this->container->runInContext('request', [], function () {
            $a = $this->container->get('svc');
            $this->assertSame($a, $this->container->get('svc'));

            return $a;
        });

        $second = $this->container->runInContext('request', [], fn() => $this->container->get('svc'));

        $this->assertNotSame($first, $second, 'a scoped instance must not leak into the next execution');
    }

    public function testNestedContextGetsItsOwnScopedInstanceAndOuterOneSurvives(): void
    {
        $this->container->bindExecution('svc', fn() => new \stdClass());

        $this->container->runInContext('request', [], function () {
            $outer = $this->container->get('svc');

            $inner = $this->container->runInContext('worker', [], fn() => $this->container->get('svc'));

            $this->assertNotSame($outer, $inner);
            $this->assertSame($outer, $this->container->get('svc'));
        });
    }

    public function testExecutionScopedServiceOutsideAContextFailsLoudly(): void
    {
        $this->container->bindExecution('svc', fn() => new \stdClass());

        $this->expectException(\LogicException::class);

        $this->container->get('svc');
    }

    public function testScopeOfReportsDeclaredScopes(): void
    {
        $this->container->singleton('a', fn() => 1);
        $this->container->bind('b', fn() => 1);
        $this->container->bindExecution('c', fn() => 1);
        $this->container->bindWithScope('d', fn() => 1, 'execution');

        $this->assertSame('singleton', $this->container->scopeOf('a'));
        $this->assertSame('transient', $this->container->scopeOf('b'));
        $this->assertSame('execution', $this->container->scopeOf('c'));
        $this->assertSame('execution', $this->container->scopeOf('d'));
        $this->assertNull($this->container->scopeOf('missing'));
    }

    public function testUnknownScopeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->container->bindWithScope('x', fn() => 1, 'forever');
    }

    public function testSingletonAndTransientBehaviourIsUnchanged(): void
    {
        $this->container->singleton('single', fn() => new \stdClass());
        $this->container->bind('transient', fn() => new \stdClass());

        $this->assertSame($this->container->get('single'), $this->container->get('single'));
        $this->assertNotSame($this->container->get('transient'), $this->container->get('transient'));

        $this->container->runInContext('request', [], function () {
            $this->assertSame($this->container->get('single'), $this->container->get('single'));
        });

        $this->assertTrue($this->container->isSingleton('single'));
        $this->assertFalse($this->container->isSingleton('transient'));
    }

    public function testExtendKeepsExecutionScope(): void
    {
        $this->container->bindExecution('svc', fn() => new \stdClass());
        $this->container->extend('svc', function (\stdClass $o) {
            $o->extended = true;

            return $o;
        });

        $this->container->runInContext('request', [], function () {
            $svc = $this->container->get('svc');

            $this->assertTrue($svc->extended);
            $this->assertSame($svc, $this->container->get('svc'));
        });
    }

    public function testRebindingAScopedServiceDropsItsLiveInstance(): void
    {
        $this->container->bindExecution('svc', fn() => (object) ['v' => 1]);

        $this->container->runInContext('request', [], function () {
            $this->assertSame(1, $this->container->get('svc')->v);

            $this->container->bindExecution('svc', fn() => (object) ['v' => 2]);

            $this->assertSame(2, $this->container->get('svc')->v);
        });
    }

    public function testFlushEndsOpenContextsAndRunsTheirEndingListenersThenClearsListeners(): void
    {
        $ended = [];
        $this->container->contextEnding(function (ExecutionContext $c) use (&$ended) {
            $ended[] = $c->type();
        });

        $this->container->beginContext('request');
        $this->container->beginContext('worker');
        $suspended = $this->container->beginContext('console');
        $this->container->suspendContext($suspended);

        $this->container->flush();

        $this->assertEqualsCanonicalizing(['request', 'worker', 'console'], $ended);
        $this->assertNull($this->container->executionContext());

        // Listeners were cleared by the flush.
        $this->container->runInContext('request', [], fn() => null);
        $this->assertCount(3, $ended);
    }

    public function testFlushEndsContextsBeforeBindingsAreCleared(): void
    {
        $this->container->bindExecution('svc', fn() => new \stdClass());

        $resolved = null;
        $this->container->contextEnding(function () use (&$resolved) {
            $resolved = $this->container->get('svc');
        });

        $this->container->beginContext('request');
        $this->container->flush();

        $this->assertInstanceOf(\stdClass::class, $resolved);
    }

    public function testFlushStillClearsEverythingWhenAnEndingListenerThrows(): void
    {
        $this->container->contextEnding(function () {
            throw new \RuntimeException('listener');
        });
        $this->container->beginContext('request');

        try {
            $this->container->flush();
            $this->fail('expected exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('listener', $e->getMessage());
        }

        $this->assertNull($this->container->executionContext());
        $this->container->runInContext('request', [], fn() => null);
    }

    public function testEndedContextCannotBeResumed(): void
    {
        $context = $this->container->beginContext('request');
        $this->container->endContext($context);

        $this->container->resumeContext($context);

        $this->assertNull($this->container->executionContext());
    }

    public function testContextEndedByFlushCannotBeResumed(): void
    {
        $context = $this->container->beginContext('request');
        $this->container->suspendContext($context);
        $this->container->flush();

        $this->container->resumeContext($context);

        $this->assertNull($this->container->executionContext());
    }

    public function testResumingACurrentContextDoesNotDuplicateIt(): void
    {
        $context = $this->container->beginContext('request');

        $this->container->resumeContext($context);
        $this->container->endContext($context);

        $this->assertNull($this->container->executionContext());
    }

    public function testResumeRestoresMetadataStampedAfterTheContextWasHandedOut(): void
    {
        $original = $this->container->beginContext('request');
        $this->container->stampContext(['tenant' => 'acme']);
        $this->container->suspendContext($original);

        $this->container->resumeContext($original);

        $this->assertSame('acme', $this->container->executionContext()->get('tenant'));

        $this->container->endContext($original);
    }

    public function testEndingASuspendedContextShowsListenersItsLatestMetadata(): void
    {
        $seen = null;
        $this->container->contextEnding(function (ExecutionContext $c) use (&$seen) {
            $seen = $c->get('tenant');
        });

        $original = $this->container->beginContext('request');
        $this->container->stampContext(['tenant' => 'acme']);
        $this->container->suspendContext($original);

        $this->container->endContext($original);

        $this->assertSame('acme', $seen);
    }

    // ---------------------------------------------------------------- service / facade accessor

    public function testExecutionServiceReadsAndExtendsTheActiveContext(): void
    {
        $service = new ExecutionService($this->container);

        $this->assertFalse($service->running());
        $this->assertNull($service->id());
        $this->assertSame('fallback', $service->get('tenant', 'fallback'));
        $this->assertSame([], $service->all());

        $service->within('console', ['command' => 'migrate'], function () use ($service) {
            $this->assertTrue($service->running());
            $this->assertSame('console', $service->type());
            $this->assertSame('migrate', $service->get('command'));

            $id = $service->id();
            $service->stamp(['tenant' => 9]);

            $this->assertSame($id, $service->id());
            $this->assertSame(9, $service->get('tenant'));
        });

        $this->assertFalse($service->running());
    }

    public function testExecutionHelperReturnsTheExecutionService(): void
    {
        Container::setInstance($this->container);
        $this->container->singleton('execution', fn() => new ExecutionService($this->container));

        try {
            $this->assertInstanceOf(ExecutionService::class, execution());

            $this->container->runInContext('console', ['command' => 'x'], function () {
                $this->assertSame('console', execution()->type());
            });
        } finally {
            Container::forgetInstance();
        }
    }

    public function testExecutionServiceReturnsAnExplicitNullInsteadOfTheDefault(): void
    {
        $service = new ExecutionService($this->container);

        $this->container->runInContext('request', ['tenant' => null], function () use ($service) {
            $this->assertNull($service->get('tenant', 'fallback'));
            $this->assertSame('fallback', $service->get('missing', 'fallback'));
        });
    }

    // ---------------------------------------------------------------- HTTP lifecycle

    private function application(): Application
    {
        $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->reportDeferredExceptionsUsing(fn() => null);

        return $app;
    }

    private function respondingRouter(callable $inside): Router
    {
        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturnCallback(function () use ($inside) {
            $inside();

            $response = $this->getMockBuilder(Response::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['prepare', 'send'])
                ->getMock();
            $response->method('prepare')->willReturnSelf();
            $response->method('send')->willReturnSelf();

            return $response;
        });

        return $router;
    }

    public function testDispatchRunsTheRequestInsideAContextThatEndsOnTerminate(): void
    {
        $app = $this->application();
        $seen = null;

        $app->router = $this->respondingRouter(function () use ($app, &$seen) {
            $seen = $app->executionContext();
        });

        $result = $app->dispatch(new Request());

        $this->assertSame('request', $seen->type());
        $this->assertNull($app->executionContext(), 'the context is suspended while the response is sent');

        $ended = null;
        $app->contextEnding(function (ExecutionContext $c) use (&$ended) {
            $ended = $c;
        });

        $result->terminate();

        $this->assertSame($seen->id(), $ended->id());
        $this->assertNull($app->executionContext());
    }

    public function testTerminatingCallbacksStillSeeTheRequestContext(): void
    {
        $app = $this->application();
        $app->router = $this->respondingRouter(fn() => null);

        $during = null;
        $app->terminating(function () use ($app, &$during) {
            $during = $app->executionContext();
        });

        $app->dispatch(new Request())->terminate();

        $this->assertNotNull($during);
        $this->assertSame('request', $during->type());
    }

    public function testExecutionScopedServicesDoNotLeakBetweenSequentialRequests(): void
    {
        $app = $this->application();
        $app->bindExecution('tenant.state', fn() => new \stdClass());

        $instances = [];
        $contexts = [];

        $app->router = $this->respondingRouter(function () use ($app, &$instances, &$contexts) {
            $state = $app->get('tenant.state');
            $state->touched = ($state->touched ?? 0) + 1;

            $instances[] = $state;
            $contexts[] = $app->executionContext()->id();
        });

        $app->dispatch(new Request())->terminate();
        $app->dispatch(new Request())->terminate();

        $this->assertCount(2, $instances);
        $this->assertNotSame($instances[0], $instances[1]);
        $this->assertSame(1, $instances[1]->touched, 'the second request must start from a clean instance');
        $this->assertNotSame($contexts[0], $contexts[1]);
    }

    public function testScopedInstanceSurvivesFromHandlerToTerminatingCallback(): void
    {
        $app = $this->application();
        $app->bindExecution('state', fn() => new \stdClass());

        $fromHandler = null;
        $fromTerminating = null;

        $app->router = $this->respondingRouter(function () use ($app, &$fromHandler) {
            $fromHandler = $app->get('state');
        });
        $app->terminating(function () use ($app, &$fromTerminating) {
            $fromTerminating = $app->get('state');
        });

        $app->dispatch(new Request())->terminate();

        $this->assertSame($fromHandler, $fromTerminating);
    }

    public function testContextEndsWhenTheHandlerThrows(): void
    {
        $app = $this->application();
        $ended = 0;
        $app->contextEnding(function () use (&$ended) {
            $ended++;
        });

        $router = $this->createMock(Router::class);
        $router->method('resolve')->willThrowException(new \RuntimeException('handler failed'));
        $app->router = $router;

        try {
            $app->dispatch(new Request());
            $this->fail('expected exception');
        } catch (\RuntimeException) {
        }

        $this->assertSame(1, $ended);
        $this->assertNull($app->executionContext());
    }

    public function testEndingListenerFailureDoesNotReplaceTheHandlerException(): void
    {
        $app = $this->application();
        $app->contextEnding(function () {
            throw new \LogicException('teardown failed');
        });

        $router = $this->createMock(Router::class);
        $router->method('resolve')->willThrowException(new \RuntimeException('handler failed'));
        $app->router = $router;

        $log = $this->captureErrorLog();

        try {
            $app->dispatch(new Request());
            $this->fail('expected the handler exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('handler failed', $e->getMessage());
        } finally {
            $logged = $this->releaseErrorLog($log);
        }

        $this->assertStringContainsString('Execution context ending listener failed: LogicException: teardown failed', $logged);
        $this->assertNull($app->executionContext());
    }

    public function testEndingListenerFailureStillSurfacesWhenNothingElseFailed(): void
    {
        $app = $this->application();
        $app->contextEnding(function () {
            throw new \LogicException('teardown failed');
        });
        $app->router = $this->respondingRouter(function () {});

        $result = $app->dispatch(new Request());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('teardown failed');

        $result->terminate();
    }

    public function testEndingListenerFailureDoesNotReplaceATerminatingCallbackException(): void
    {
        $app = $this->application();
        $app->contextEnding(function () {
            throw new \LogicException('teardown failed');
        });
        $app->terminating(function () {
            throw new \RuntimeException('terminating failed');
        });
        $app->router = $this->respondingRouter(function () {});

        $result = $app->dispatch(new Request());
        $log = $this->captureErrorLog();

        try {
            $result->terminate();
            $this->fail('expected the terminating exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('terminating failed', $e->getMessage());
        } finally {
            $logged = $this->releaseErrorLog($log);
        }

        $this->assertStringContainsString('teardown failed', $logged);
        $this->assertNull($app->executionContext());
    }

    private function applicationWithFailingCleanup(): Application
    {
        $app = (new ReflectionClass(FailingCleanupApplication::class))->newInstanceWithoutConstructor();
        $app->reportDeferredExceptionsUsing(fn() => null);

        return $app;
    }

    public function testCleanupFailureDoesNotReplaceATerminatingCallbackException(): void
    {
        $app = $this->applicationWithFailingCleanup();
        $app->terminating(function () {
            throw new \RuntimeException('terminating failed');
        });
        $app->router = $this->respondingRouter(function () {});

        $result = $app->dispatch(new Request());
        $log = $this->captureErrorLog();

        try {
            $result->terminate();
            $this->fail('expected the terminating exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('terminating failed', $e->getMessage());
        } finally {
            $logged = $this->releaseErrorLog($log);
        }

        $this->assertStringContainsString('Request cleanup failed: LogicException: cleanup failed', $logged);
        $this->assertNull($app->executionContext());
    }

    public function testCleanupFailureStillSurfacesAndTheContextStillEndsWhenNothingElseFailed(): void
    {
        $app = $this->applicationWithFailingCleanup();
        $ended = 0;
        $app->contextEnding(function () use (&$ended) {
            $ended++;
        });
        $app->router = $this->respondingRouter(function () {});

        $result = $app->dispatch(new Request());

        try {
            $result->terminate();
            $this->fail('expected the cleanup exception');
        } catch (\LogicException $e) {
            $this->assertSame('cleanup failed', $e->getMessage());
        }

        $this->assertSame(1, $ended);
        $this->assertNull($app->executionContext());
    }

    public function testCleanupFailureWinsOverAnEndingListenerFailureWhenNothingElseFailed(): void
    {
        $app = $this->applicationWithFailingCleanup();
        $app->contextEnding(function () {
            throw new \RuntimeException('listener failed');
        });
        $app->router = $this->respondingRouter(function () {});

        $result = $app->dispatch(new Request());
        $log = $this->captureErrorLog();

        try {
            $result->terminate();
            $this->fail('expected the cleanup exception');
        } catch (\LogicException $e) {
            $this->assertSame('cleanup failed', $e->getMessage());
        } finally {
            $logged = $this->releaseErrorLog($log);
        }

        $this->assertStringContainsString('listener failed', $logged);
        $this->assertNull($app->executionContext());
    }

    public function testRunInContextKeepsTheCallbackExceptionWhenAnEndingListenerFails(): void
    {
        $this->container->contextEnding(function () {
            throw new \LogicException('teardown failed');
        });

        $log = $this->captureErrorLog();

        try {
            $this->container->runInContext('job', [], function () {
                throw new \RuntimeException('job failed');
            });
            $this->fail('expected the job exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('job failed', $e->getMessage());
        } finally {
            $logged = $this->releaseErrorLog($log);
        }

        $this->assertStringContainsString('teardown failed', $logged);
        $this->assertFalse($this->container->hasExecutionContext());
    }

    public function testRunInContextThrowsTheEndingListenerFailureWhenTheCallbackSucceeded(): void
    {
        $this->container->contextEnding(function () {
            throw new \LogicException('teardown failed');
        });

        $this->expectException(\LogicException::class);

        $this->container->runInContext('job', [], fn () => 'done');
    }

    public function testRunInContextReturnsTheCallbackResult(): void
    {
        $this->assertSame('done', $this->container->runInContext('job', [], fn () => 'done'));
        $this->assertFalse($this->container->hasExecutionContext());
    }

    public function testBeginningListenerFailureIsNotReplacedByAnEndingListenerFailure(): void
    {
        $this->container->contextBeginning(function () {
            throw new \RuntimeException('begin failed');
        });
        $this->container->contextEnding(function () {
            throw new \LogicException('teardown failed');
        });

        $log = $this->captureErrorLog();

        try {
            $this->container->beginContext('job');
            $this->fail('expected the begin failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('begin failed', $e->getMessage());
        } finally {
            $this->releaseErrorLog($log);
        }

        $this->assertFalse($this->container->hasExecutionContext());
    }

    public function testEveryEndingListenerFailureIsReportedBeyondTheFirstThrownOne(): void
    {
        $this->container->contextEnding(function (ExecutionContext $c) {
            throw new \LogicException('failed: ' . $c->type());
        });

        $outer = $this->container->beginContext('outer');
        $inner = $this->container->beginContext('inner');
        $this->container->suspendContext($inner);

        $log = $this->captureErrorLog();

        try {
            $this->container->endContext($outer);
            $this->fail('expected the first failure');
        } catch (\LogicException $e) {
            $this->assertSame('failed: inner', $e->getMessage());
        } finally {
            $logged = $this->releaseErrorLog($log);
        }

        $this->assertStringContainsString('failed: outer', $logged);
    }

    /**
     * @return array{string, string|false}
     */
    private function captureErrorLog(): array
    {
        $file = tempnam(sys_get_temp_dir(), 'ctxlog');
        $previous = ini_set('error_log', $file);

        return [$file, $previous];
    }

    private function releaseErrorLog(array $log): string
    {
        [$file, $previous] = $log;

        ini_set('error_log', $previous === false ? '' : $previous);

        $contents = (string) file_get_contents($file);
        @unlink($file);

        return $contents;
    }

    public function testRequestMetadataComesFromTheRequest(): void
    {
        $app = $this->application();
        $request = new Request();
        $request->headers->set('X-Request-Id', 'abc-123');

        $captured = null;
        $app->router = $this->respondingRouter(function () use ($app, &$captured) {
            $captured = $app->executionContext();
        });

        $app->dispatch($request)->terminate();

        $this->assertSame('abc-123', $captured->get('request_id'));
    }

    public function testMetadataStampedDuringTheRequestIsVisibleAtTermination(): void
    {
        $app = $this->application();

        $app->router = $this->respondingRouter(function () use ($app) {
            $app->stampContext(['tenant' => 'acme']);
        });

        $inTerminating = null;
        $app->terminating(function () use ($app, &$inTerminating) {
            $inTerminating = $app->executionContext()->get('tenant');
        });

        $inEnding = null;
        $app->contextEnding(function (ExecutionContext $c) use (&$inEnding) {
            $inEnding = $c->get('tenant');
        }, 'request');

        $app->dispatch(new Request())->terminate();

        $this->assertSame('acme', $inTerminating);
        $this->assertSame('acme', $inEnding);
    }

    public function testRequestMetadataDoesNotIncludeThePersonalClientAddress(): void
    {
        $app = $this->application();
        $captured = null;

        $app->router = $this->respondingRouter(function () use ($app, &$captured) {
            $captured = $app->executionContext();
        });

        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';

        try {
            $app->dispatch(new Request())->terminate();
        } finally {
            unset($_SERVER['REMOTE_ADDR']);
        }

        $this->assertFalse($captured->has('ip'));
        $this->assertArrayNotHasKey('ip', $captured->all());
    }

    // ---------------------------------------------------------------- console lifecycle

    public function testConsoleCommandRunsInAConsoleContextThatEndsAfterDeferredCleanup(): void
    {
        $app = $this->application();
        $app->bindExecution('job.state', fn() => new \stdClass());

        $order = [];
        $seen = null;
        $cleanupSawContext = null;

        $console = new class($app) extends Console {
            protected function bootstrapCommands(): void {}
        };
        $console->setCatchExceptions(false);

        $command = new Command('ctx:test');
        $command->setCode(function () use ($app, &$order, &$seen, &$cleanupSawContext): int {
            $seen = $app->executionContext();
            $app->get('job.state');
            $app->defer(function () use ($app, &$order, &$cleanupSawContext) {
                $order[] = 'defer';
                $cleanupSawContext = $app->executionContext();
            });

            return Command::SUCCESS;
        });
        $console->addCommand($command);

        $app->contextEnding(function () use (&$order) {
            $order[] = 'context-end';
        });

        $console->run(new ArrayInput(['command' => 'ctx:test']), new NullOutput());

        $this->assertSame('console', $seen->type());
        $this->assertSame('ctx:test', $seen->get('command'));
        $this->assertSame(['defer', 'context-end'], $order);
        $this->assertSame($seen->id(), $cleanupSawContext->id());
        $this->assertNull($app->executionContext());
    }
}

class FailingCleanupApplication extends Application
{
    protected function cleanupRequestScopedServices(): void
    {
        throw new \LogicException('cleanup failed');
    }
}
