<?php

namespace Phaseolies\Error;

use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Http\Exceptions\TokenMismatchException;
use Phaseolies\Http\Response;
use Phaseolies\Http\Response\JsonResponse;
use Throwable;

class JsonErrorRenderer
{
    /**
     * Output a JSON-formatted error response.
     *
     * @param Throwable $exception
     * @param int $statusCode
     * @param mixed|null $errorDetails
     * @return \Phaseolies\Http\Response\JsonResponse
     */
    public function render(Throwable $exception, int $statusCode, mixed $errorDetails = null): JsonResponse
    {
        $messages = [
            Response::HTTP_TOO_MANY_REQUESTS    => trans('validation.rate_limit.message'),
            Response::HTTP_UNPROCESSABLE_ENTITY => trans('validation.default'),
            Response::PAGE_EXPIRED              => trans('validation.default'),
            Response::HTTP_UNAUTHORIZED         => trans('validation.unauthorized.message'),
        ];

        // Some callers pass the exception's own message, which is not extra detail.
        $errorDetails = $errorDetails === $exception->getMessage() ? null : $errorDetails;

        $response = isset($messages[$statusCode])
            ? [
                'message' => $messages[$statusCode],
                'errors'  => $errorDetails ?? $this->publicMessage($exception),
            ]
            : [
                'message' => $errorDetails ?? $this->publicMessage($exception),
                'errors'  => app_debug()
                    ? [
                        'file'  => $exception->getFile(),
                        'line'  => $exception->getLine(),
                        'trace' => $exception->getTraceAsString(),
                    ]
                    : [],
            ];

        return response()->json($response, $statusCode);
    }

    /**
     * The exception message, when it is safe to show to the client.
     *
     * The status of a non-HTTP exception comes from its code, so the decision
     * is made by exception type and not by status number.
     *
     * @param Throwable $exception
     * @return string
     */
    protected function publicMessage(Throwable $exception): string
    {
        $isClientError = $exception instanceof TokenMismatchException || (
            $exception instanceof HttpException &&
            $exception->getStatusCode() >= 400 &&
            $exception->getStatusCode() < 500
        );

        return app_debug() || $isClientError ? $exception->getMessage() : 'Something went wrong';
    }
}
