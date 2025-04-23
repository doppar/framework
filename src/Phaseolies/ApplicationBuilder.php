<?php

namespace Phaseolies;

use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use App\Http\Kernel;
use Phaseolies\Middleware\Contracts\Middleware as ContractsMiddleware;

class ApplicationBuilder
{
    /**
     * ApplicationBuilder constructor.
     *
     * Initializes the ApplicationBuilder with the application instance and base path.
     * Also instantiates singleton classes required for the application.
     *
     * @param Application $app The application instance.
     * @param string $basePath The base path of the application.
     */
    public function __construct(protected Application $app) {}

    /**
     * Handle global middleware and kernel.
     *
     * @return self
     * @throws \Exception If a middleware dependency is unresolved.
     */
    public function withMiddlewareStack(): self
    {
        $kernel = app(Kernel::class);

        $globalMiddlewares = [];
        foreach ($kernel->middleware as $middlewareClass) {
            $globalMiddlewares[] = $middlewareClass;
        }

        $request = app(Request::class);
        $finalHandler = fn() => app(Response::class);

        foreach ($globalMiddlewares as $middlewareClass) {
            $middlewareInstance = new $middlewareClass();
            if ($middlewareInstance instanceof ContractsMiddleware) {
                $finalHandler = function (Request $request) use ($middlewareInstance, $finalHandler) {
                    return $middlewareInstance($request, $finalHandler);
                };
            } else {
                throw new \Exception("Unresolved dependency $middlewareClass", 1);
            }
        }

        $kernel->handle($request, $finalHandler);

        return $this;
    }

    /**
     * Build and return the application instance.
     *
     * @return Application
     */
    public function build(): Application
    {
        return $this->app;
    }
}
