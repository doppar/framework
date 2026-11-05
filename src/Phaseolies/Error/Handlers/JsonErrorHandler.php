<?php

namespace Phaseolies\Error\Handlers;

use Throwable;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Http\Response;
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

        $this->buildResponse($exception)->prepare(request())->send();
    }

    /**
     * Build the JSON error response for an exception.
     *
     * @param Throwable $exception
     * @return Response
     */
    protected function buildResponse(Throwable $exception): Response
    {
        $renderer = new JsonErrorRenderer();
        if ($exception instanceof HttpResponseException) {
            $responseErrors = $exception->getValidationErrors();

            $statusCode = (int) $exception->getStatusCode() ?: 500;

            return $renderer->render($exception, $statusCode, $responseErrors);
        }

        $response = $renderer->render($exception, $this->resolveStatusCode($exception));

        if ($exception instanceof HttpException) {
            $response->withHeaders($exception->getHeaders());
        }

        return $response;
    }

    /**
     * The HTTP status for an exception. Any other exception's code can be any
     * number, so it only counts when it is an HTTP error status.
     *
     * @param Throwable $exception
     * @return int
     */
    protected function resolveStatusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpException) {
            return $exception->getStatusCode();
        }

        $statusCode = (int) $exception->getCode();

        return $statusCode >= 400 && $statusCode < 600 ? $statusCode : 500;
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
