<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phaseolies\Http\Exceptions;

/**
 * HttpException.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class HttpException extends \RuntimeException implements HttpExceptionInterface
{
    /**
     * Constructor for the HttpException.
     *
     * @param int $statusCode The HTTP status code associated with the exception.
     * @param string $message The exception message (optional).
     * @param \Throwable|null $previous The previous throwable used for exception chaining (optional).
     * @param array $headers Additional HTTP headers to include with the exception (optional).
     * @param int $code The internal exception code (optional).
     */
    public function __construct(
        private int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
        private array $headers = [],
        int $code = 0
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Creates and returns an appropriate HttpException subclass instance based on the HTTP status code.
     *
     * @param int $statusCode The HTTP status code to create an exception for.
     * @param string $message Optional error message.
     * @param \Throwable|null $previous Optional previous exception for chaining.
     * @param array $headers Optional HTTP headers to include in the exception.
     * @return static
     */
    public static function fromStatusCode(
        int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
        array $headers = []
    ): self {
        return match ($statusCode) {
            400 => new BadRequestHttpException($message, $previous, 0, $headers),
            403 => new AccessDeniedHttpException($message, $previous, 0, $headers),
            404 => new NotFoundHttpException($message, $previous, 0, $headers),
            406 => new NotAcceptableHttpException($message, $previous, 0, $headers),
            409 => new ConflictHttpException($message, $previous, 0, $headers),
            423 => new LockedHttpException($message, $previous, 0, $headers),
            415 => new UnsupportedMediaTypeHttpException($message, $previous, 0, $headers),
            422 => new UnprocessableEntityHttpException($message, $previous, 0, $headers),
            429 => new TooManyRequestsHttpException(null, $message, $previous, 0, $headers),
            500 => new InternalServerErrorHttpException($message, $previous, 0, $headers),
            503 => new ServiceUnavailableHttpException(null, $message, $previous, 0, $headers),
            default => new static($statusCode, $message, $previous, $headers, 0),
        };
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
     * Get the HTTP headers.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set the HTTP headers.
     *
     * @param array $headers
     * @return void
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }
}
