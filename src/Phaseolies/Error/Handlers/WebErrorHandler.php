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

        if (env('APP_DEBUG') === "true") {
            $renderer->renderDebug($exception);
        } else {
            $renderer->renderProduction($exception);
        }

        exit(1);
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
