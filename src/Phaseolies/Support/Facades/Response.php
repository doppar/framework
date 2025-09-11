<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static setBody(?string $body): static
 * @method static getBody(): string
 * @method static setOriginal($original): static
 * @method static getOriginal()
 * @method static setCharset(string $charset): static
 * @method static getCharset(): ?string
 * @method static setException($exception): static
 * @method static setStatusCode(int $statusCode, ?string $text = null): static
 * @method static getStatusCode(): int
 * @method static setHeader(string $name, string $value, $replace = true): Response
 * @method static withHeaders(array $headers): Response
 * @method static withException(Throwable $e)
 * @method static isInformational(): bool
 * @method static isEmpty(): bool
 * @method static setProtocolVersion(string $version): static
 * @method static getProtocolVersion(): string
 * @method static prepare(Request $request): static
 * @method static sendHeaders(?int $statusCode = null): static
 * @method static getStatusCodeText(int $statusCode): string
 * @method static sendContent(): static
 * @method static send(bool $flush = true): static
 * @method static closeOutputBuffers(int $targetLevel, bool $flush): void
 * @method static dispatchHttpException(HttpException $exception): void
 * @method static render(): string
 * @method static view(string $view, array $data = [], array $headers = []): Response
 * @method static renderView(string $view, array $data = []): string
 * @method static json(mixed $data, int $statusCode = 200, array $headers = []): JsonResponse
 * @method static text(string $content, int $statusCode = 200, array $headers = []): Response
 * @method static isCacheable(): bool
 * @method static isFresh(): bool
 * @method static isValidateable(): bool
 * @method static setPrivate(): static
 * @method static setPublic(): static
 * @method static setImmutable(bool $immutable = true): static
 * @method static isImmutable(): bool
 * @method static mustRevalidate(): bool
 * @method static getDate(): ?\DateTimeImmutable
 * @method static setDate(\DateTimeInterface $date): static
 * @method static getAge(): int
 * @method static expire(): static
 * @method static getExpires(): ?\DateTimeImmutable
 * @method static setExpires(?\DateTimeInterface $date): static
 * @method static getMaxAge(): ?int
 * @method static setMaxAge(int $value): static
 * @method static setStaleIfError(int $value): static
 * @method static setStaleWhileRevalidate(int $value): static
 * @method static setSharedMaxAge(int $value): static
 * @method static getTtl(): ?int
 * @method static setTtl(int $seconds): static
 * @method static setClientTtl(int $seconds): static
 * @method static getLastModified(): ?\DateTimeImmutable
 * @method static getEtag(): ?string
 * @method static setEtag(?string $etag, bool $weak = false): static
 * @method static setCache(array $options): static
 * @method static setNotModified(): static
 * @method static hasVary(): bool
 * @method static getVary(): array
 * @method static setVary(string|array $headers, bool $replace = true): static
 * @method static isNotModified(Request $request): bool
 * @method static isInvalid(): bool
 * @method static isSuccessful(): bool
 * @method static isRedirection(): bool
 * @method static isServerError(): bool
 * @method static isOk(): bool
 * @method static isForbidden(): bool
 * @method static isNotFound(): bool
 * @method static isRedirect(?string $location = null): bool
 * @method statis setContentSafe(bool $safe = true): void
 *
 * @see \Phaseolies\Http\Response
 */

use Phaseolies\Facade\BaseFacade;

class Response extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'response';
    }
}
