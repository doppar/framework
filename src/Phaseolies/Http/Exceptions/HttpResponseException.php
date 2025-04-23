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
     * @param mixed $validationErrors
     * @param int $statusCode
     * @param \Throwable|null $previous
     * @return void
     */
    public function __construct(
        mixed $validationErrors = null,
        int $statusCode = 500,
        ?Throwable $previous = null
    ) {
        parent::__construct($previous?->getMessage() ?? '', $previous?->getCode() ?? 0, $previous);

        $this->validationErrors = $validationErrors;
        $this->statusCode = $statusCode;
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
