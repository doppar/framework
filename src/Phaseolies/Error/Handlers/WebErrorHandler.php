<?php

namespace Phaseolies\Error\Handlers;

use Throwable;
use Phaseolies\Error\WebErrorRenderer;
use Phaseolies\Error\Contracts\ErrorHandlerInterface;
use Phaseolies\Http\Exceptions\HttpResponseException;

class WebErrorHandler implements ErrorHandlerInterface
{
    /**
     * Renders the error for a standard web request.
     *
     * @param Throwable $exception
     * @return void
     */
    public function handle(Throwable $exception): void
    {
        if ($exception instanceof HttpResponseException && $exception->hasResponse()) {
            $exception->getResponse()?->prepare(request())->send();

            return;
        }

        $renderer = new WebErrorRenderer();

        if ($this->isDebugging()) {
            $response = $renderer->renderDebug($exception);
        } else {
            $response = $renderer->renderProduction($exception);
        }

        $response->prepare(request())->send();
    }

    /**
     * Whether debugging is on. A server environment variable arrives as a
     * string, and "false" must not count as true.
     *
     * @return bool
     */
    protected function isDebugging(): bool
    {
        return filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Checks if this handler should handle the current request.
     *
     * @return bool
     */
    public function supports(): bool
    {
        return !request()->isAjax() || !request()->isApiRequest() || PHP_SAPI !== 'cli';
    }
}
