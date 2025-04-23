<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\UrlGenerator enqueue(string $path = '/', $secure = null)
 * @method static \Phaseolies\Support\UrlGenerator full()
 * @method static \Phaseolies\Support\UrlGenerator current()
 * @method static \Phaseolies\Support\UrlGenerator route($name, array $parameters = [], $secure = null)
 * @method static \Phaseolies\Support\UrlGenerator to($path = '/')
 * @method static \Phaseolies\Support\UrlGenerator withQuery(array $query = [])
 * @method static \Phaseolies\Support\UrlGenerator withSignature($expiration = 3600)
 * @method static \Phaseolies\Support\UrlGenerator withFragment($fragment = '')
 * @method static \Phaseolies\Support\UrlGenerator make()
 * @method static \Phaseolies\Support\UrlGenerator signed($path = '/', array $parameters = [], $expiration = 3600, $secure = null)
 * @method static \Phaseolies\Support\UrlGenerator isValid(string $url)
 * @method static \Phaseolies\Support\UrlGenerator base(string $url)
 * @method static \Phaseolies\Support\UrlGenerator setSecure(bool $secure)
 * @see \Phaseolies\Support\UrlGenerator
 */

use Phaseolies\Facade\BaseFacade;

class URL extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'url';
    }
}
