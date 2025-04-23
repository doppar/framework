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
    public function abort(int $code, string $message = ''): void
    {
        throw new HttpException($code, $message);
    }

    /**
     * Abort the request if a condition is true.
     *
     * @param bool $condition The condition to check.
     * @param int $code The HTTP status code.
     * @param string $message The optional error message.
     * @throws HttpException
     */
    public function abortIf(bool $condition, int $code, string $message = ''): void
    {
        if ($condition) {
            self::abort($code, $message);
        }
    }
}
