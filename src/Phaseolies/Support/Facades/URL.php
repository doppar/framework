<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static string enqueue(string $path = '/', ?bool $secure = null)
 * @method static string full()
 * @method static string current()
 * @method static string route(string $name, array|string|int $parameters = [], ?bool $secure = null)
 * @method static \Phaseolies\Support\Contracts\UrlGeneratorInterface to(string $path = '/', array|string|int $parameters = [], ?bool $secure = null)
 * @method static \Phaseolies\Support\Contracts\UrlGeneratorInterface withQuery(array|string $query = [])
 * @method static \Phaseolies\Support\Contracts\UrlGeneratorInterface withSignature(int $expiration = 3600)
 * @method static \Phaseolies\Support\Contracts\UrlGeneratorInterface withFragment(string $fragment = '')
 * @method static string make()
 * @method static string signed(string $path = '/', array|string|int $parameters = [], int $expiration = 3600, ?bool $secure = null)
 * @method static bool isValid(mixed $url)
 * @method static string base()
 * @method static \Phaseolies\Support\Contracts\UrlGeneratorInterface setSecure(bool $secure)
 *
 * @see \Phaseolies\Support\Contracts\UrlGeneratorInterface
 */
class URL extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'url';
    }
}
