<?php

namespace Phaseolies\Http\Contracts;

use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Http\Exceptions\HttpResponseException;

interface RequestAbortionInterface
{
    /**
     * Abort the request with a specific HTTP status code and optional message.
     *
     * @param int $code
     * @param string $message
     * @param array $headers
     * @return void
     * @throws HttpException|HttpResponseException
     */
    public function abort(int $code, string $message = '', array $headers = []): void;

    /**
     * Abort the request if a condition is true.
     *
     * @param mixed $condition
     * @param int $code
     * @param string $message
     * @param array $headers
     * @return void
     * @throws HttpException|HttpResponseException
     */
    public function abortIf(mixed $condition, int $code, string $message = '', array $headers = []): void;
}
