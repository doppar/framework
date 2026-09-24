<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static \Phaseolies\Support\Router get(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router post(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router put(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router patch(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router delete(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router options(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router head(string $uri, array|string|callable|null $callback)
 * @method static \Phaseolies\Support\Router any(string $uri, array|string|callable|null $callback)
 * @method static void group(array $attributes, \Closure $callback)
 * @method static \Phaseolies\Support\Router redirect(string $uri, string $destination, int $status = 302)
 * @method static void bundle(string $uri, string $controller, array $options = [])
 * @method static void apiBundle(string $uri, string $controller, array $options = [])
 * @method static void nestedBundle(string $parent, string $child, string $controller, array $options = [])
 * @method static array|null getCurrentMiddlewareNames()
 * @method static array|null getRouteNames()
 * @method static bool has(string $name)
 * @method static bool is(string $name)
 * @method static string|null currentRouteName()
 * @method static string|array|null currentRouteAction()
 * @method static bool currentRouteUsesController(string $controllerClass): bool
 *
 * @see \Phaseolies\Support\Router
 */
class Route extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'route';
    }
}
