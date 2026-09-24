<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;
use Phaseolies\Http\Response\Cookie as BrowserCookie;

/**
 * @method static BrowserCookie make(string $name, ?string $value = null, array $options = [])
 * @method static mixed get(string $key, $default = null)
 * @method static bool has(string $key)
 * @method static bool store($name, $value = null, array $options = [])
 * @method static void remove(string $name, array $options = [])
 * @method static void forever(string $name, string $value, array $options = [])
 * @method static array all(bool $decodeValues = true)
 *
 * @see \Phaseolies\Support\CookieJar
 */
class Cookie extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'cookie';
    }
}
