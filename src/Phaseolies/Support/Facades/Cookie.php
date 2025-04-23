<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\CookieJar make(string $name, ?string $value = null, array $options = []): Cookie
 * @method static \Phaseolies\Support\CookieJar get(string $key, $default = null)
 * @method static \Phaseolies\Support\CookieJar has(string $key): bool
 * @method static \Phaseolies\Support\CookieJar store($name, $value = null, array $options = []): bool
 * @method static \Phaseolies\Support\CookieJar remove(string $name, array $options = []): void
 * @method static \Phaseolies\Support\CookieJar forever(string $name, string $value, array $options = []): void
 * @method static \Phaseolies\Support\CookieJar all(): ?array
 * @see \Phaseolies\Support\CookieJar
 */

use Phaseolies\Facade\BaseFacade;

class Cookie extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'cookie';
    }
}
