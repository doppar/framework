<?php

namespace Phaseolies\Http\Support;

use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Http\Exceptions\HttpException;

class RequestAbortion
{
    /**
     * Abort the request with a specific HTTP status code and optional message.
     *
     * @param int $code The HTTP status code.
     * @param string $message The optional error message.
     * @param array $headers
     * @return void
     * @throws HttpException
     */
    public function abort(int $code, string $message = '', array $headers = []): void
    {
        $shouldJsonResponse = request()->isAjax() || request()->is('/api/*');

        $customPath = base_path("resources/views/errors/{$code}.blade.php");
        $packagePath = base_path("vendor/doppar/framework/src/Phaseolies/Support/View/errors/{$code}.blade.php");

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
     * @param bool $condition The condition to check.
     * @param int $code The HTTP status code.
     * @param string $message The optional error message.
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
