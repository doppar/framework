<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\Router get(string $uri, array|string|callable|null)
 * @method static \Phaseolies\Support\Router post(string $uri, array|string|callable|null)
 * @method static \Phaseolies\Support\Router put(string $uri, array|string|callable|null)
 * @method static \Phaseolies\Support\Router patch(string $uri, array|string|callable|null)
 * @method static \Phaseolies\Support\Router delete(string $uri, array|string|callable|null)
 * @method static \Phaseolies\Support\Router options(string $uri, array|string|callable|null)
 * @method static \Phaseolies\Support\Router head(string $uri, array|string|callable|null)
 * @method static \Phaseolies\Support\Router any(string $uri, array|string|callable|null)
 * @see \Phaseolies\Support\Router
 */

use Phaseolies\Facade\BaseFacade;

class Route extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'route';
    }
}
