<?php

namespace Phaseolies;

use Phaseolies\Support\TimezoneHandler;
use Phaseolies\Middleware\Contracts\Middleware as ContractsMiddleware;

class ApplicationBuilder
{
    /**
     * Holds the current HTTP request instance
     *
     * @var \Phaseolies\Http\Request<string>
     */
    protected $request;

    /**
     * @param Application $app The application instance to be built
     */
    public function __construct(protected Application $app)
    {
        $this->request = $this->app->make('request');
    }

    /**
     * Set the application timezone
     *
     * @return self
     */
    public function withTimezone(): self
    {
        $timezone = $this->app['config']->get('app.timezone', 'UTC');

        $this->app->singleton(
            'timezone',
            fn() => new TimezoneHandler($timezone)
        );

        return $this;
    }

    /**
     * Configures the application with middleware stack handling
     *
     * @return self
     * @throws \Exception
     */
    public function withMiddlewareStack(): self
    {
        $middlewareStack = $this->buildMiddlewareStack();

        $handler = $this->processMiddlewareStack($middlewareStack);

        $this->app->router->handle($this->request, $handler);

        return $this;
    }

    /**
     * Constructs the middleware stack based on request type
     *
     * @return array
     */
    protected function buildMiddlewareStack(): array
    {
        $middlewareStack = $this->app->router->middleware ?? [];

        $groupKey = $this->request->isApiRequest() ? 'api' : 'web';
        $groupMiddleware = $this->app->router->middlewareGroups[$groupKey] ?? [];

        return array_merge($middlewareStack, $groupMiddleware);
    }

    /**
     * Processes the middleware stack into a handler pipeline.
     *
     * @param array $middlewareStack
     * @return callable
     * @throws \Exception
     */
    protected function processMiddlewareStack(array $middlewareStack): callable
    {
        $response = fn() => $this->app->make('response');

        foreach ($middlewareStack as $middlewareClass) {
            $middlewareInstance = $this->app->make($middlewareClass);
            if (!$middlewareInstance instanceof ContractsMiddleware) {
                throw new \Exception(
                    "Failed to register middleware {$middlewareClass}: it must implement " . ContractsMiddleware::class . "."
                );
            }

            $response = function ($request) use ($middlewareInstance, $response) {
                return $middlewareInstance($request, $response);
            };
        }

        return $response;
    }

    /**
     * Finalizes the builder process and returns the configured application.
     *
     * @return Application
     */
    public function build(): Application
    {
        return $this->app;
    }
}
