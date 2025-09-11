<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static get(string $key, $default = null)
 * @method static pull(string $key, $default = null)
 * @method static getPeek(string $key, $default = null)
 * @method static putPeek(string $key, $value): void
 * @method static flushPeek(): void
 * @method static put(string|array $key, $value = null): void
 * @method static has(string $key): bool
 * @method static forget(string $key): void
 * @method static flush(): void
 * @method static regenerate(bool $deleteOldSession = true): void
 * @method static all(): array
 * @method static getId(): string
 * @method static setId(string $id): void
 * @method static destroy(): void
 * @method static token(): ?string
 * @method static flash(string $key, $value): void
 * @method static reflash($keys): void
 * @method static invalidate(): void
 * @method static regenerateToken(): void
 *
 * @see \Phaseolies\Support\Session
 */

use Phaseolies\Facade\BaseFacade;

class Session extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'session';
    }
}
