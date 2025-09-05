<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\Router get(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router post(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router put(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router patch(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router delete(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router options(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router head(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router any(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router redirect(string $uri, string $destination, int $status = 302)
 * @method static \Phaseolies\Support\Router bundle(string $uri, string $controller, array $options = []): void
 * @method static \Phaseolies\Support\Router apiBundle(string $uri, string $controller, array $options = []): void
 * @method static \Phaseolies\Support\Router nestedBundle(string $parent, string $child, string $controller, array $options = []): void
 * @method static \Phaseolies\Support\Router getCurrentMiddlewareNames(): ?array
 *
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
