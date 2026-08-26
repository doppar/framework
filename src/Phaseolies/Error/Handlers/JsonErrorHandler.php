<?php

namespace Phaseolies\Error\Handlers;

use Throwable;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Error\JsonErrorRenderer;
use Phaseolies\Error\Contracts\ErrorHandlerInterface;

class JsonErrorHandler implements ErrorHandlerInterface
{
    /**
     * Handle a given exception by generating a JSON error response.
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

        $renderer = new JsonErrorRenderer();
        if ($exception instanceof HttpResponseException) {
            $responseErrors = $exception->getValidationErrors();

            $statusCode = (int) $exception->getStatusCode() ?: 500;
            $response = $renderer->render($exception, $statusCode, $responseErrors);
        } else {
            $statusCode = (int) $exception->getCode() ?: 500;
            $response = $renderer->render($exception, $statusCode);
        }

        $response->prepare(request())->send();
    }

    /**
     * Determines whether this handler supports the current request context.
     *
     * @return bool
     */
    public function supports(): bool
    {
        return request()->isAjax() || request()->isApiRequest();
    }
}
