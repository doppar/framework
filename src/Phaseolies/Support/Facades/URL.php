<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static string enqueue(string $path = '/', $secure = null)
 * @method static string full()
 * @method static string current()
 * @method static string route(string $name, array|string|int $parameters = [], ?bool $secure = null)
 * @method static \Phaseolies\Support\UrlGenerator to($path = '/')
 * @method static \Phaseolies\Support\UrlGenerator withQuery(array|string $query = [])
 * @method static \Phaseolies\Support\UrlGenerator withSignature($expiration = 3600)
 * @method static \Phaseolies\Support\UrlGenerator withFragment($fragment = '')
 * @method static string make()
 * @method static string signed(string $path = '/', array|string|int $parameters = [], int $expiration = 3600, ?bool $secure = null)
 * @method static bool isValid(string $url)
 * @method static string base()
 * @method static \Phaseolies\Support\UrlGenerator setSecure(bool $secure)
 *
 * @see \Phaseolies\Support\UrlGenerator
 */
class URL extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'url';
    }
}
