<?php

namespace Phaseolies\Http\Support;

use Phaseolies\Http\Exceptions\HttpException;

class RequestAbortion
{
    /**
     * Abort the request with a specific HTTP status code and optional message.
     *
     * @param int $code The HTTP status code.
     * @param string $message The optional error message.
     * @throws HttpException
     */
    public function abort($code, $message = '', array $headers = []): void
    {
        throw HttpException::fromStatusCode($code, $message, null, $headers);
    }

    /**
     * Abort the request if a condition is true.
     *
     * @param bool $condition The condition to check.
     * @param int $code The HTTP status code.
     * @param string $message The optional error message.
     * @throws HttpException
     */
    public function abortIf($condition, $code, $message = '', array $headers = []): void
    {
        if ($condition) {
            $this->abort($code, $message, $headers);
        }
    }
}
