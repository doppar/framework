<?php

namespace Phaseolies;

use Phaseolies\Http\Request;
use Phaseolies\Middleware\Contracts\Middleware as ContractsMiddleware;
use Exception;

/**
 * ApplicationBuilder constructs and configures the application instance with middleware handling.
 *
 * This class is responsible for building the middleware stack and processing it
 * to create a request handling pipeline for the application.
 */
class ApplicationBuilder
{
    /**
     * @param Application $app The application instance to be built
     */
    public function __construct(protected Application $app) {}

    /**
     * Configures the application with middleware stack handling.
     *
     * This is the main entry point for middleware configuration that:
     * 1. Builds the appropriate middleware stack based on request type
     * 2. Processes the stack to create a handler pipeline
     * 3. Delegates request handling to the router with the prepared pipeline
     *
     * @return self Returns the builder instance for method chaining
     * @throws Exception If any middleware dependency is invalid
     */
    public function withMiddlewareStack(): self
    {
        $middlewareStack = $this->buildMiddlewareStack();

        $finalHandler = $this->processMiddlewareStack($middlewareStack);

        $this->app->router->handle(app('request'), $finalHandler);

        return $this;
    }

    /**
     * Constructs the middleware stack based on request type.
     *
     * Combines:
     * - Global middleware (always runs)
     * - Group-specific middleware (api or web, based on request type)
     *
     * @return array The complete middleware stack for the current request
     */
    protected function buildMiddlewareStack(): array
    {
        $middlewareStack = $this->app->router->middleware ?? [];

        $request = app('request');
        $groupKey = $request->isApiRequest() ? 'api' : 'web';
        $groupMiddleware = $this->app->router->middlewareGroups[$groupKey] ?? [];

        return array_merge($middlewareStack, $groupMiddleware);
    }

    /**
     * Processes the middleware stack into a handler pipeline.
     *
     * Creates a nested series of closures where each middleware wraps the next,
     * forming an onion-like request handling pipeline.
     *
     * @param array $middlewareStack Array of middleware class names
     * @return callable The final request handler pipeline
     * @throws Exception If any middleware doesn't implement the required interface
     */
    protected function processMiddlewareStack(array $middlewareStack): callable
    {
        $response = fn() => app('response');

        foreach ($middlewareStack as $middlewareClass) {
            $middlewareInstance = new $middlewareClass();
            if (!$middlewareInstance instanceof ContractsMiddleware) {
                throw new Exception(
                    "Middleware {$middlewareClass} must implement " . ContractsMiddleware::class
                );
            }

            $response = function (Request $request) use ($middlewareInstance, $response) {
                return $middlewareInstance($request, $response);
            };
        }

        return $response;
    }

    /**
     * Finalizes the builder process and returns the configured application.
     *
     * @return Application The fully configured application instance
     */
    public function build(): Application
    {
        return $this->app;
    }
}
