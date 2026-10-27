<?php

namespace Phaseolies\Http\Contracts;

interface RequestAbortionInterface
{
    /**
     * Abort the request with a specific HTTP status code and optional message.
     *
     * @param int $code
     * @param string $message
     * @param array $headers
     * @return void
     */
    public function abort(int $code, string $message = '', array $headers = []): void;

    /**
     * Abort the request if a condition is true.
     *
     * @param bool $condition
     * @param int $code
     * @param string $message
     * @param array $headers
     * @return void
     */
    public function abortIf(bool $condition, int $code, string $message = '', array $headers = []): void;
}
