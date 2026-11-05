<?php

namespace Phaseolies\Http\Contracts;

use Closure;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Middleware\Contracts\Middleware as ContractsMiddleware;

interface GatewayInterface
{
    /**
     * Get the application's global HTTP middleware stack — the
     * middleware run on every request.
     *
     * @return array
     */
    public function getGlobalMiddleware(): array;

    /**
     * Get the application's route middleware groups (e.g. "web", "api").
     *
     * @return array<string, array<int, class-string|string>>
     */
    public function getMiddlewareGroups(): array;

    /**
     * Get the application's named route middleware aliases.
     *
     * @return array<string, array<string, class-string|string>>
     */
    public function getRouteMiddleware(): array;

    /**
     * Apply a given middleware to the current middleware chain.
     *
     * @param ContractsMiddleware $middleware
     * @param array|string $params
     * @return void
     */
    public function applyMiddleware(ContractsMiddleware $middleware, array|string $params = []): void;

    /**
     * Handle the incoming request through the middleware chain.
     *
     * @param Request $request
     * @param Closure $handler
     * @return Response
     */
    public function handle(Request $request, Closure $handler): Response;
}
