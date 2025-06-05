<?php

namespace Tests\Unit;

use Phaseolies\Middleware\Middleware;
use Phaseolies\Middleware\Contracts\Middleware as ContractsMiddleware;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use PHPUnit\Framework\TestCase;

class TestRequest extends Request {}

class MiddlewareTest extends TestCase
{
    private Middleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new Middleware();
    }

    public function testInitialState()
    {
        $request = new TestRequest();

        $handler = fn(Request $request) => new Response('Original response');

        $response = $this->middleware->handle($request, $handler);

        $this->assertEquals('Original response', $response->getBody());
    }

    public function testApplyMiddleware()
    {
        $middleware = new class implements ContractsMiddleware {
            public function __invoke($request, $next)
            {
                $response = $next($request);
                $response->setBody('Modified response');
                return $response;
            }
        };

        $this->middleware->applyMiddleware($middleware);

        $request = new Request();
        $handler = fn(Request $request) => new Response('Original response');

        $response = $this->middleware->handle($request, $handler);

        $this->assertEquals('Modified response', $response->getBody());
    }

    public function testMultipleMiddlewareExecutionOrder()
    {
        $executionOrder = new class {
            public array $order = [];
            public function add(string $message): void
            {
                $this->order[] = $message;
            }
        };

        $middleware1 = new class($executionOrder) implements ContractsMiddleware {
            private $tracker;
            public function __construct($tracker)
            {
                $this->tracker = $tracker;
            }
            public function __invoke($request, $next, ...$params)
            {
                $this->tracker->add('Middleware1 start');
                $response = $next($request);
                $this->tracker->add('Middleware1 end');
                return $response;
            }
        };

        $middleware2 = new class($executionOrder) implements ContractsMiddleware {
            private $tracker;
            public function __construct($tracker)
            {
                $this->tracker = $tracker;
            }
            public function __invoke($request, $next, ...$params)
            {
                $this->tracker->add('Middleware2 start');
                $response = $next($request);
                $this->tracker->add('Middleware2 end');
                return $response;
            }
        };

        $this->middleware->applyMiddleware($middleware1);
        $this->middleware->applyMiddleware($middleware2);

        $request = new Request();
        $handler = fn(Request $request) => new Response('Original response');

        $this->middleware->handle($request, $handler);

        $expectedOrder = [
            'Middleware2 start',
            'Middleware1 start',
            'Middleware1 end',
            'Middleware2 end'
        ];
        $this->assertEquals($expectedOrder, $executionOrder->order);
    }

    public function testMiddlewareWithParameters()
    {
        $middleware = new class implements ContractsMiddleware {
            public function __invoke($request, $next, ...$params)
            {
                //     $params = [
                // 0 => "param1"
                //         1 => "param2"
                //     ];
                return new Response('Response with params');
            }
        };

        $this->middleware->applyMiddleware($middleware, ['param1', 'param2']);

        $request = new Request();
        $handler = fn(Request $request) => new Response('Original response');

        $response = $this->middleware->handle($request, $handler);

        $this->assertEquals('Response with params', $response->getBody());
    }

    public function setParam($params): void
    {
        $this->assertEquals('param1', $params[0]);
        $this->assertEquals('param2', $params[1]);
    }
}
