<?php

namespace Phaseolies\Middleware;

use Closure;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Middleware\Contracts\Middleware as ContractsMiddleware;

class Middleware
{
    /**
     * Closure that handles the request processing.
     * @var Closure(Request, Closure): Response
     */
    public Closure $start;

    /**
     * Initialize the middleware with a default request handler.
     */
    public function __construct()
    {
        $this->start = fn(Request $request, Closure $next): Response => $next($request);
    }

    /**
     * Apply a given middleware to the current middleware chain.
     *
     * @param ContractsMiddleware $middleware The middleware to apply.
     * @return void
     */
    public function applyMiddleware(ContractsMiddleware $middleware, array|string $params = []): void
    {
        $next = $this->start;
        $this->start = function (Request $request, Closure $handler) use ($middleware, $next, $params) {
            return $middleware($request, function (Request $request) use ($next, $handler) {
                return $next($request, $handler);
            }, ...$params);
        };
    }

    /**
     * Handle the incoming request through the middleware chain
     *
     * @param Request $request
     * @param Closure $handler
     * @return Response
     */
    public function handle(Request $request, Closure $handler): Response
    {
        return ($this->start)($request, $handler);
    }
}
