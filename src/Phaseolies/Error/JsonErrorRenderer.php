<?php

namespace Phaseolies\Error;

use Phaseolies\Http\Response;
use Throwable;

class JsonErrorRenderer
{
    /**
     * Output a JSON-formatted error response.
     *
     * @param Throwable $exception
     * @param int $statusCode
     * @param mixed|null $errorDetails
     * @return void
     */
    public function render(Throwable $exception, int $statusCode, mixed $errorDetails = null): void
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
                'errors'  => $errorDetails,
            ]
            : [
                'message' => $exception->getMessage(),
                'errors'  => [
                    'file'  => $exception->getFile(),
                    'line'  => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ],
            ];

        header('Content-Type: application/json');
        http_response_code($statusCode);

        echo json_encode($response, JSON_UNESCAPED_SLASHES);

        exit;
    }
}
