<?php

namespace Phaseolies\Http\Support;

use Phaseolies\Error\JsonErrorRenderer;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Http\Response;

class RequestAbortion
{
    /**
     * Abort the request with a specific HTTP status code and optional message.
     *
     * @param int $code
     * @param string $message
     * @param array $headers
     * @return void
     * @throws HttpException
     */
    public function abort(int $code, string $message = '', array $headers = []): void
    {
        $request = request();
        $shouldJsonResponse = $request->isAjax() || $request->isApiRequest();
        $httpException = HttpException::fromStatusCode($code, $message, null, $headers);

        $customPath =
            base_path(
                'templates'
                    . DIRECTORY_SEPARATOR . 'views'
                    . DIRECTORY_SEPARATOR . 'errors'
                    . DIRECTORY_SEPARATOR . "{$code}.odo.php"
            );

        $packagePath =
            base_path(
                'vendor'
                    . DIRECTORY_SEPARATOR . 'doppar'
                    . DIRECTORY_SEPARATOR . 'framework'
                    . DIRECTORY_SEPARATOR . 'src'
                    . DIRECTORY_SEPARATOR . 'Phaseolies'
                    . DIRECTORY_SEPARATOR . 'Support'
                    . DIRECTORY_SEPARATOR . 'View'
                    . DIRECTORY_SEPARATOR . 'errors'
                    . DIRECTORY_SEPARATOR . "{$code}.odo.php"
            );

        if (!$shouldJsonResponse) {
            $viewPath = file_exists($customPath) ? $customPath : (file_exists($packagePath) ? $packagePath : null);

            if ($viewPath) {
                throw (new HttpResponseException($message, $code, $httpException))
                    ->setResponse($this->buildErrorViewResponse($viewPath, $code, $message, $headers, $httpException));
            }
        }

        if ($shouldJsonResponse) {
            $response = (new JsonErrorRenderer())
                ->render($httpException, $code, $message)
                ->withHeaders($headers);

            throw (new HttpResponseException($message, $code, $httpException))
                ->setResponse($response);
        }

        throw $httpException;
    }

    /**
     * Abort the request if a condition is true.
     *
     * @param bool $condition
     * @param int $code
     * @param string $message
     * @param array $headers
     * @return void
     * @throws HttpException
     */
    public function abortIf($condition, int $code, string $message = '', array $headers = []): void
    {
        if ($condition) {
            $this->abort($code, $message, $headers);
        }
    }

    /**
     * Build an HTML response for a resolved error view.
     *
     * @param string $viewPath
     * @param int $statusCode
     * @param string $message
     * @param array<string, string> $headers
     * @param mixed $original
     * @return \Phaseolies\Http\Response
     */
    protected function buildErrorViewResponse(
        string $viewPath,
        int $statusCode,
        string $message = '',
        array $headers = [],
        mixed $original = null
    ): Response {
        if (ob_get_level() > 0) {
            ob_get_clean();
        }

        ob_start();
        include $viewPath;
        $content = ob_get_clean() ?: '';

        return response($content, $statusCode, $headers)
            ->setOriginal($original ?? $content);
    }
}
