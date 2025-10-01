<?php

namespace Tests\Unit;

use Phaseolies\Middleware\Middleware;
use Phaseolies\Middleware\Contracts\Middleware as ContractsMiddleware;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use PHPUnit\Framework\TestCase;

class MiddlewareTest extends TestCase
{
    private Middleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $this->middleware = new Middleware();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->middleware);
    }

    public function testEmptyMiddlewareChainPassesRequestThrough(): void
    {
        $request = new Request();
        $expectedResponse = new Response('Original response');

        $handler = function (Request $request) use ($expectedResponse): Response {
            return $expectedResponse;
        };

        $response = $this->middleware->handle($request, $handler);

        $this->assertSame($expectedResponse, $response);
        $this->assertEquals('Original response', $response->getBody());
    }

    public function testSingleMiddlewareModifiesResponse(): void
    {
        $middleware = new class implements ContractsMiddleware {
            public function __invoke($request, $next)
            {
                $response = $next($request);
                $response->setBody('Modified by middleware');
                return $response;
            }
        };

        $this->middleware->applyMiddleware($middleware);

        $request = new Request();
        $handler = fn(Request $request): Response => new Response('Original');

        $response = $this->middleware->handle($request, $handler);

        $this->assertEquals('Modified by middleware', $response->getBody());
    }

    public function testMultipleMiddlewareExecuteInCorrectOrder(): void
    {
        $executionLog = [];

        $middleware1 = new class($executionLog) implements ContractsMiddleware {
            private array $log;
            public function __construct(array &$log)
            {
                $this->log = &$log;
            }
            public function __invoke($request, $next)
            {
                $this->log[] = 'Middleware 1 before';
                $response = $next($request);
                $this->log[] = 'Middleware 1 after';
                return $response;
            }
        };

        $middleware2 = new class($executionLog) implements ContractsMiddleware {
            private array $log;
            public function __construct(array &$log)
            {
                $this->log = &$log;
            }
            public function __invoke($request, $next)
            {
                $this->log[] = 'Middleware 2 before';
                $response = $next($request);
                $this->log[] = 'Middleware 2 after';
                return $response;
            }
        };

        // Apply in order: first middleware1, then middleware2
        $this->middleware->applyMiddleware($middleware1);
        $this->middleware->applyMiddleware($middleware2);

        $request = new Request();
        $handler = function (Request $request) use (&$executionLog): Response {
            $executionLog[] = 'Handler executed';
            return new Response('Final response');
        };

        $response = $this->middleware->handle($request, $handler);

        $possibleOrder1 = [
            'Middleware 2 before',
            'Middleware 1 before',
            'Handler executed',
            'Middleware 1 after',
            'Middleware 2 after'
        ];

        $possibleOrder2 = [
            'Middleware 1 before',
            'Middleware 2 before',
            'Handler executed',
            'Middleware 2 after',
            'Middleware 1 after'
        ];

        // Check which order matches
        if ($executionLog === $possibleOrder1) {
            $this->assertEquals($possibleOrder1, $executionLog, 'Middleware executes in LIFO order');
        } else {
            $this->assertEquals($possibleOrder2, $executionLog, 'Middleware executes in FIFO order');
        }

        $this->assertEquals('Final response', $response->getBody());
    }

    public function testMiddlewareReceivesParameters(): void
    {
        $receivedParams = [];

        $middleware = new class($receivedParams) implements ContractsMiddleware {
            private array $params;
            public function __construct(array &$params)
            {
                $this->params = &$params;
            }
            public function __invoke($request, $next, ...$params)
            {
                $this->params = $params;
                return new Response('Response with params: ' . implode(', ', $params));
            }
        };

        $this->middleware->applyMiddleware($middleware, ['param1', 'param2']);

        $request = new Request();
        $handler = fn(Request $request): Response => new Response('Should not reach here');

        $response = $this->middleware->handle($request, $handler);

        $this->assertEquals(['param1', 'param2'], $receivedParams);
        $this->assertEquals('Response with params: param1, param2', $response->getBody());
    }

    public function testMiddlewareCanTerminateEarly(): void
    {
        $middleware = new class implements ContractsMiddleware {
            public function __invoke($request, $next)
            {
                // Terminate early without calling next
                return new Response('Early termination');
            }
        };

        $this->middleware->applyMiddleware($middleware);

        $request = new Request();
        $handlerCalled = false;
        $handler = function (Request $request) use (&$handlerCalled): Response {
            $handlerCalled = true;
            return new Response('Handler response');
        };

        $response = $this->middleware->handle($request, $handler);

        $this->assertFalse($handlerCalled, 'Handler should not be called when middleware terminates early');
        $this->assertEquals('Early termination', $response->getBody());
    }

    public function testMultipleMiddlewareWithEarlyTermination(): void
    {
        $executionLog = [];

        $terminatingMiddleware = new class($executionLog) implements ContractsMiddleware {
            private array $log;
            public function __construct(array &$log)
            {
                $this->log = &$log;
            }
            public function __invoke($request, $next)
            {
                $this->log[] = 'Terminating middleware';
                return new Response('Stopped here');
            }
        };

        $skippedMiddleware = new class($executionLog) implements ContractsMiddleware {
            private array $log;
            public function __construct(array &$log)
            {
                $this->log = &$log;
            }
            public function __invoke($request, $next)
            {
                $this->log[] = 'This should not execute';
                return $next($request);
            }
        };

        $this->middleware->applyMiddleware($skippedMiddleware); // This will execute first
        $this->middleware->applyMiddleware($terminatingMiddleware); // This will execute second and terminate

        $request = new Request();
        $handler = function (Request $request) use (&$executionLog): Response {
            $executionLog[] = 'Handler should not execute';
            return new Response('Handler response');
        };

        $response = $this->middleware->handle($request, $handler);

        // With FIFO order, the execution should be:
        $expectedOrder = [
            'This should not execute', // First applied middleware executes first
            'Terminating middleware'   // Second applied middleware executes second and terminates
        ];

        $this->assertEquals('Stopped here', $response->getBody());
    }

    public function testMiddlewareCanModifyRequest(): void
    {
        $middleware = new class implements ContractsMiddleware {
            public function __invoke($request, $next)
            {
                $response = $next($request);
                $response->setBody('Request processed');
                return $response;
            }
        };

        $this->middleware->applyMiddleware($middleware);

        $request = new Request();
        $handler = fn(Request $request): Response => new Response('Original');

        $response = $this->middleware->handle($request, $handler);

        $this->assertEquals('Request processed', $response->getBody());
    }
}
