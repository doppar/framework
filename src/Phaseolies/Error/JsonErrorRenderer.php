<?php

namespace Phaseolies\Error;

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

        $message = $messages[$statusCode] ?? $exception->getMessage();

        $response = isset($messages[$statusCode])
            ? [
                'message' => $message,
                'errors'  => $errorDetails ?? $exception->getMessage(),
            ]
            : $this->exceptionPayload($exception, $statusCode, $errorDetails);

        return response()->json($response, $statusCode);
    }

    /**
     * Describe an exception, leaving out its internals unless debugging.
     *
     * @param Throwable $exception
     * @param int $statusCode
     * @param mixed|null $errorDetails
     * @return array
     */
    protected function exceptionPayload(Throwable $exception, int $statusCode, mixed $errorDetails): array
    {
        if (filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'message' => $errorDetails ?? $exception->getMessage(),
                'errors'  => [
                    'file'  => $exception->getFile(),
                    'line'  => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ],
            ];
        }

        return [
            'message' => $errorDetails ?? ($statusCode >= 500 ? 'Something went wrong' : $exception->getMessage()),
            'errors'  => [],
        ];
    }
}
