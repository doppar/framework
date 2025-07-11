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
    public function __construct(
        private int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
        private array $headers = [],
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public static function fromStatusCode(int $statusCode, string $message = '', ?\Throwable $previous = null, array $headers = []): self
    {
        return match ($statusCode) {
            400 => new BadRequestHttpException($message, $previous, $statusCode, $headers),
            403 => new AccessDeniedHttpException($message, $previous, $statusCode, $headers),
            404 => new NotFoundHttpException($message, $previous, $statusCode, $headers),
            406 => new NotAcceptableHttpException($message, $previous, $statusCode, $headers),
            409 => new ConflictHttpException($message, $previous, $statusCode, $headers),
            423 => new LockedHttpException($message, $previous, $statusCode, $headers),
            415 => new UnsupportedMediaTypeHttpException($message, $previous, $statusCode, $headers),
            422 => new UnprocessableEntityHttpException($message, $previous, $statusCode, $headers),
            429 => new TooManyRequestsHttpException(null, $message, $previous, $statusCode, $headers),
            503 => new ServiceUnavailableHttpException(null, $message, $previous, $statusCode, $headers),
            default => new static($statusCode, $message, $previous, $headers, $statusCode),
        };
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }
}
