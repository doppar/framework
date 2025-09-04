<?php

namespace Phaseolies\Http\Exceptions;

use RuntimeException;
use Throwable;

class HttpResponseException extends RuntimeException
{
    /**
     * The validation errors.
     *
     * @var mixed
     */
    protected $validationErrors;

    /**
     * The HTTP status code.
     *
     * @var int
     */
    protected $statusCode;

    /**
     * Create a new HTTP response exception instance.
     *
     * @param mixed $message
     * @param int $status
     * @param \Throwable|null $previous
     * @return void
     */
    public function __construct(mixed $message = null,  int $status = 500,  ?Throwable $previous = null)
    {
        parent::__construct($previous?->getMessage() ?? '', $previous?->getCode() ?? 0, $previous);

        $this->validationErrors = $message;
        $this->statusCode = $status;
    }

    /**
     * Get the validation errors.
     *
     * @return mixed
     */
    public function getValidationErrors(): mixed
    {
        return $this->validationErrors;
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
