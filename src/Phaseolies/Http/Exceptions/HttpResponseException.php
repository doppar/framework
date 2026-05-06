<?php

namespace Phaseolies\Http\Exceptions;

use Phaseolies\Http\Response;
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
     * The prepared response instance, if available.
     *
     * @var \Phaseolies\Http\Response|null
     */
    protected ?Response $response = null;

    /**
     * Create a new HTTP response exception instance.
     *
     * @param mixed $message
     * @param int $status
     * @param \Throwable|null $previous
     * @return void
     */
    public function __construct(mixed $message = null, int $status = 500, ?Throwable $previous = null, ?Response $response = null)
    {
        $exceptionMessage = $previous?->getMessage();

        if (($exceptionMessage === null || $exceptionMessage === '') && is_string($message)) {
            $exceptionMessage = $message;
        }

        parent::__construct($exceptionMessage ?? '', $status, $previous);

        $this->validationErrors = $message;
        $this->statusCode = $status;
        $this->response = $response;
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

    /**
     * Attach a prepared response instance to the exception.
     *
     * @param \Phaseolies\Http\Response $response
     * @return $this
     */
    public function setResponse(Response $response): static
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Get the prepared response instance.
     *
     * @return \Phaseolies\Http\Response|null
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }

    /**
     * Determine whether the exception already has a prepared response.
     *
     * @return bool
     */
    public function hasResponse(): bool
    {
        return $this->response instanceof Response;
    }
}
