<?php

namespace Phaseolies\Http\Exceptions;

use Exception;

class HttpException extends Exception
{
    /**
     * The HTTP status code.
     *
     * @var int
     */
    protected $statusCode;

    /**
     * Create a new HTTP exception instance.
     *
     * @param int $statusCode The HTTP status code.
     * @param string $message The error message.
     */
    public function __construct(int $statusCode, string $message = '')
    {
        $this->statusCode = $statusCode;

        parent::__construct($message, $statusCode);
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
