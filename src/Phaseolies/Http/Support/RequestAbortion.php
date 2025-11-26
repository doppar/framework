<?php

namespace Phaseolies\Http\Support;

use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Http\Exceptions\HttpException;

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
        $shouldJsonResponse = request()->isAjax() || request()->isApiRequest();

        $customPath =
            base_path(
                'resources'
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
                if (ob_get_level() > 0) {
                    ob_get_clean();
                }
                http_response_code($code);
                include $viewPath;
                exit;
            }
        }

        if ($shouldJsonResponse) {
            throw new HttpResponseException($message, $code, null);
        }

        throw HttpException::fromStatusCode($code, $message, null, $headers);
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
}
