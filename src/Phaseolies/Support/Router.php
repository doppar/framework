<?php

namespace Phaseolies\Support;

use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Phaseolies\Database\Eloquent\Model;
use Phaseolies\Database\Eloquent\Builder;
use Phaseolies\Application;
use Ramsey\Collection\Collection;
use Phaseolies\Http\Validation\FormRequest;
use Phaseolies\Middleware\Contracts\Middleware as ContractsMiddleware;
use App\Http\Kernel;

class Router extends Kernel
{
    /**
     * Holds the registered routes.
     *
     * @var array
     */
    protected static array $routes = [];

    /**
     * Stores URL parameters extracted from routes.
     *
     * @var array
     */
    protected array $urlParams = [];

    /**
     * Holds the registered named routes.
     *
     * @var array<string, string>
     */
    public static array $namedRoutes = [];

    /**
     * @var string|null The path of the current route being defined.
     */
    protected ?string $currentRoutePath = null;

    /**
     * @var string
     */
    protected string $currentRequestMethod;

    /**
     * @var array<string> The middleware keys for the current route.
     */
    protected static array $routeMiddlewares = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
        'OPTIONS' => [],
        'HEAD' => [],
        'ANY' => [],
    ];

    /**
     * @var array Stack of group attributes (middleware, prefix, etc.)
     */
    protected static array $groupStack = [];

    /**
     * Generate route cache key
     * @return string
     */
    protected function getCacheKey(): string
    {
        return 'route_cache_' . md5(json_encode(self::$routes));
    }

    /**
     * Cache all the routes
     * @return void
     */
    public function cacheRoutes(): void
    {
        $cacheData = [
            'routes' => $this->getCacheableRoutes(),
            'namedRoutes' => self::$namedRoutes,
            'routeMiddlewares' => self::$routeMiddlewares
        ];

        $cachePath = $this->getCachePath();
        file_put_contents($cachePath, '<?php return ' . var_export($cacheData, true) . ';');
    }

    /**
     * Get cacheable route excluding closure based route
     * @return array
     */
    protected function getCacheableRoutes(): array
    {
        $cacheableRoutes = [];

        foreach (self::$routes as $method => $routes) {
            foreach ($routes as $path => $callback) {
                if ($this->isCacheableRoute($callback)) {
                    $cacheableRoutes[$method][$path] = $callback;
                } else {
                    $cacheableRoutes[$method][$path] = 'closure';
                }
            }
        }

        return $cacheableRoutes;
    }

    /**
     * Check is the route is cacheable or not
     * @param mixed $callback
     * @return bool
     */
    protected function isCacheableRoute($callback): bool
    {
        if (
            is_array($callback) &&
            count($callback) === 2 &&
            is_string($callback[0]) &&
            class_exists($callback[0]) &&
            is_string($callback[1])
        ) {
            return true;
        }

        if (is_string($callback) && class_exists($callback)) {
            $reflection = new \ReflectionClass($callback);
            return $reflection->hasMethod('__invoke');
        }

        return false;
    }

    /**
     * Modified loadCachedRoutes to handle the new cache structure
     * @return bool
     */
    public function loadCachedRoutes(): bool
    {
        $files = glob(storage_path('framework/cache/routes_*.php'));

        if (empty($files)) {
            return false;
        }

        $cachePath = end($files);

        if (file_exists($cachePath)) {
            $cached = require $cachePath;
            self::$routes = $cached['routes'] ?? [];
            self::$namedRoutes = $cached['namedRoutes'] ?? [];
            self::$routeMiddlewares = $cached['routeMiddlewares'] ?? [];
            $uri = strtok(request()->uri(), '?');
            $method = strtoupper(request()->method());
            if ($cached['routes'][$method][$uri] === 'closure') {
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Get the route cache path
     * @return string
     */
    protected function getCachePath(): string
    {
        return storage_path('framework/cache/routes_' . $this->getCacheKey() . '.php');
    }

    /**
     * Determines whether route caching should be enabled based on environment configuration.
     * @return bool
     */
    public function shouldCacheRoutes(): bool
    {
        return env('APP_ROUTE_CACHE', false) === 'true';
    }

    /**
     * Clears all cached route files from the framework cache directory.
     *
     * Searches for all route cache files (matching the pattern 'routes_*.php')
     * in the framework cache directory and attempts to delete each one.
     *
     * @return bool
     */
    public function clearRouteCache(): bool
    {
        $files = glob(storage_path('framework/cache/routes_*.php'));
        $success = true;

        foreach ($files as $file) {
            if (!@unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Create a route group with shared attributes.
     *
     * @param array $attributes
     * @param \Closure $callback
     * @return void
     */
    public function group(array $attributes, \Closure $callback): void
    {
        static::$groupStack[] = $attributes;

        $callback($this);

        array_pop(static::$groupStack);
    }

    /**
     * Get the prefix from the current group stack.
     *
     * @return string
     */
    protected function getGroupPrefix(): string
    {
        $prefix = '';

        foreach (static::$groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix = rtrim($prefix, '/') . '/' . ltrim($group['prefix'], '/');
            }
        }

        return $prefix;
    }

    /**
     * Get the middleware from the current group stack.
     *
     * @return array
     */
    protected function getGroupMiddleware(): array
    {
        if (empty(static::$groupStack)) {
            return [];
        }

        $middleware = [];
        foreach (static::$groupStack ?? [] as $group) {
            if (isset($group['middleware'])) {
                $middleware = array_merge(
                    $middleware,
                    (array) $group['middleware']
                );
            }
        }

        return $middleware;
    }

    /**
     * Registers a GET route with a callback.
     *
     * @param string $path The route path.
     * @param callable $callback The callback for the route.
     * @return self
     */
    public function get($path, $callback): self
    {
        return $this->addRoute('GET', $path, $callback);
    }

    /**
     * Registers an OPTIONS route with a callback.
     *
     * @param string $path The route path.
     * @param callable $callback The callback for the route.
     * @return self
     */
    public function options($path, $callback): self
    {
        return $this->addRoute('OPTIONS', $path, $callback);
    }

    /**
     * Registers a HEAD route with a callback.
     *
     * @param string $path The route path.
     * @param callable $callback The callback for the route.
     * @return self
     */
    public function head($path, $callback): self
    {
        return $this->addRoute('HEAD', $path, $callback);
    }

    /**
     * Registers a route that matches any HTTP method.
     *
     * @param string $path The route path.
     * @param callable $callback The callback for the route.
     * @return self
     */
    public function any($path, $callback): self
    {
        $method = request()->_method ?? request()->getMethod();

        return $this->addRoute($method, $path, $callback);
    }

    /**
     * Registers a POST route with a callback.
     *
     * @param string $path The route path.
     * @param callable $callback The callback for the route.
     * @return self
     */
    public function post($path, $callback): self
    {
        return $this->addRoute('POST', $path, $callback);
    }

    /**
     * Registers a PUT route with a callback.
     *
     * @param string $path The route path.
     * @param callable $callback The callback for the route.
     * @return self
     */
    public function put($path, $callback): self
    {
        return $this->addRoute('PUT', $path, $callback);
    }

    /**
     * Registers a PATCH route with a callback.
     *
     * @param string $path The route path.
     * @param callable $callback The callback for the route.
     * @return self
     */
    public function patch($path, $callback): self
    {
        return $this->addRoute('PATCH', $path, $callback);
    }

    /**
     * Registers a DELETE route with a callback.
     *
     * @param string $path The route path.
     * @param callable $callback The callback for the route.
     * @return self
     */
    public function delete($path, $callback): self
    {
        return $this->addRoute('DELETE', $path, $callback);
    }

    /**
     * Add a route with group attributes applied.
     *
     * @param string $method
     * @param string $path
     * @param callable|array $callback
     * @return self
     */
    protected function addRoute(string $method, string $path, $callback): self
    {
        if (
            !is_callable($callback) &&
            !is_array($callback) &&
            !is_string($callback) &&
            class_exists($callback)
        ) {
            throw new \InvalidArgumentException('Invalid route defination found');
        }

        // Handle wildcard routes
        if ($path === '*') {
            $fullPath = '(.*)'; // Special pattern for catch-all
        } else {
            $prefix = $this->getGroupPrefix();
            $path = ltrim($path, '/');
            $prefix = $prefix ? rtrim($prefix, '/') : '';

            $fullPath = $prefix ? $prefix . '/' . $path : $path;
            $fullPath = '/' . ltrim($fullPath, '/');

            // Normalize trailing slashes except for root
            $fullPath = ($fullPath !== '/' && substr($fullPath, -1) === '/')
                ? rtrim($fullPath, '/')
                : $fullPath;
        }

        // Special handling for root within a group
        if ($path === '' && $prefix !== '') {
            self::$routes[$method][$fullPath] = $callback;
            $this->currentRoutePath = $fullPath;
        } else {
            self::$routes[$method][$fullPath] = $callback;
            $this->currentRoutePath = $fullPath;
        }

        $this->currentRequestMethod = $method;

        // Apply group middleware if any
        $groupMiddleware = $this->getGroupMiddleware();
        if (!empty($groupMiddleware)) {
            $this->middleware($groupMiddleware);
        }

        return $this;
    }

    /**
     * Assigns a name to the last registered route.
     *
     * @param string $name The name for the route.
     * @return self
     */
    public function name(string $name): self
    {
        if ($this->currentRoutePath) {
            self::$namedRoutes[$name] = $this->currentRoutePath;
        }

        return $this;
    }

    /**
     * Generates a URL for a named route.
     *
     * @param string $name The route name.
     * @param array $params The parameters for the route.
     * @return string|null The generated URL or null if the route doesn't exist.
     */
    public function route(string $name, mixed $params = []): ?string
    {
        if (!isset(self::$namedRoutes[$name])) {
            return null;
        }

        $route = self::$namedRoutes[$name];

        if (!is_array($params)) {
            if (preg_match('/\{(\w+)(:[^}]+)?}/', $route, $matches)) {
                $params = [$matches[1] => $params];
            } else {
                $params = [$params];
            }
        }

        foreach ($params as $key => $value) {
            $route = preg_replace('/\{' . $key . '(:[^}]+)?}/', $value, $route, 1);
        }

        return $route;
    }

    /**
     * Applies middleware to the route.
     *
     * @param string|array $key The middleware key.
     * @return Route
     * @throws \Exception If the middleware is not defined.
     */
    public function middleware(string|array ...$keys): self
    {
        $keys = count($keys) === 1 && is_array($keys[0])
            ? $keys[0]
            : $keys;

        if ($this->currentRoutePath) {
            $method = $this->getCurrentRequestMethod();
            foreach ((array) $keys as $key) {
                self::$routeMiddlewares[$method][$this->currentRoutePath] = (array) $keys;
            }
        }

        return $this;
    }

    /**
     * Trace the current requested method
     * @return string
     */
    protected function getCurrentRequestMethod(): string
    {
        return $this->currentRequestMethod ?? 'GET';
    }

    /**
     * Retrieves the callback for the current route based on the request method and path.
     *
     * @return mixed The route callback or false if not found.
     */
    public function getCallback($request): mixed
    {
        $method = $request->getMethod();
        $url = $request->getPath();

        $url = ($url !== '/') ? rtrim($url, '/') : $url;

        $routes = self::$routes[$method] ?? [];

        if (isset($routes[$url])) {
            return $routes[$url];
        }

        foreach ($routes as $route => $callback) {
            if ($route === $url) {
                continue;
            }

            if ($route === '(.*)') {
                return $callback;
            }

            $routeRegex = $this->convertRouteToRegex($route);

            if (preg_match($routeRegex, $url, $matches)) {
                $params = $this->extractRouteParameters($route, $matches);
                if ($params !== false) {
                    $request->setRouteParams($params);
                    return $callback;
                }
            }
        }

        return false;
    }

    /**
     * Convert route to regex
     *
     * @param string $route
     * @return string
     */
    protected function convertRouteToRegex(string $route): string
    {
        $regex = str_replace('/', '\/', $route);

        // Replace {param} with named capture groups
        $regex = preg_replace('/\{(\w+)(:[^}]+)?}/', '(?P<$1>[^\/]+)', $regex);

        // Replace * with .* for wildcard matching
        $regex = str_replace('*', '.*', $regex);

        return '@^' . $regex . '$@D';
    }

    /**
     * Extract route params
     *
     * @param string $route
     * @param array $matches
     * @return array
     */
    protected function extractRouteParameters(string $route, array $matches): array|false
    {
        // Get all named parameters from the route pattern
        preg_match_all('/\{(\w+)(:[^}]+)?}/', $route, $paramNames);
        $params = [];

        foreach ($paramNames[1] as $name) {
            if (!isset($matches[$name])) {
                return false;
            }
            $params[$name] = $matches[$name];
        }

        return $params;
    }

    /**
     * Checks if the request is a modifying request (POST, PUT, PATCH, DELETE).
     *
     * @param Request $request The incoming request instance.
     * @return bool
     */
    protected function isModifyingRequest(Request $request): bool
    {
        return $request->isPost() || $request->isPut() || $request->isPatch() || $request->isDelete();
    }

    /**
     * Get the current route middleware
     * @return array|null
     */
    public function getCurrentRouteMiddleware($request): ?array
    {
        $url = $request->getPath();
        $method = $request->getMethod();
        $routes = self::$routes[$method] ?? [];

        foreach ($routes as $route => $callback) {
            $routeRegex = "@^" . preg_replace('/\{(\w+)(:[^}]+)?}/', '([^/]+)', $route) . "$@";
            if (preg_match($routeRegex, $url)) {
                return self::$routeMiddlewares[$method][$route] ?? null;
            }
        }

        return null;
    }

    /**
     * Applies middleware to the route
     *
     * @param Request $request
     * @param Application $app
     * @param array $currentMiddleware
     * @return void
     * @throws \Exception
     */
    private function applyRouteMiddleware($request, $app, $currentMiddleware): void
    {
        foreach ($currentMiddleware as $key) {
            [$name, $params] = array_pad(explode(':', $key, 2), 2, null);
            $params = $params ? explode(',', $params) : [];
            if (!$request->isApiRequest()) {
                if (!isset($this->routeMiddleware['web'][$name])) {
                    throw new \Exception("Undefined middleware [$name]");
                }

                $middlewareClass = $this->routeMiddleware['web'][$name];
            } else {
                if (!isset($this->routeMiddleware['api'][$name])) {
                    throw new \Exception("Undefined middleware [$name]");
                }

                $middlewareClass = $this->routeMiddleware['api'][$name];
            }

            $middlewareInstance = $app->make($middlewareClass);
            if (!$middlewareInstance instanceof ContractsMiddleware) {
                throw new \Exception("Unresolved dependency $middlewareClass", 1);
            }

            $this->applyMiddleware($middlewareInstance, $params);
        }
    }

    /**
     * Resolves and executes the route callback with middleware and dependencies.
     * @param Application $app
     * @param Request $request
     * @throws \ReflectionException If there is an issue with reflection.
     * @throws \Exception
     * @return Response
     */
    public function resolve(Application $app, Request $request): Response
    {
        $currentMiddleware = $this->getCurrentRouteMiddleware($request);
        if ($currentMiddleware) $this->applyRouteMiddleware($request, $app, $currentMiddleware);

        $callback = $this->getCallback($request);
        if (!$callback) abort(404);

        $routeParams = $request->getRouteParams();
        $handler = function ($request) use ($callback, $app, $routeParams) {
            if (is_array($callback)) {
                $result = $this->resolveControllerAction($callback, $app, $routeParams);
            } elseif (is_string($callback) && class_exists($callback)) {
                $result = $this->resolveControllerAction($callback, $app, $routeParams);
            } elseif ($callback instanceof \Closure) {
                $result = $this->resolveClosure($callback, $app, $routeParams, $request);
            } else {
                $result = call_user_func($callback, ...array_values($routeParams));
            }

            $response = app(Response::class);
            if (!($result instanceof Response)) {
                return $this->getResolutionResponse($result, $response);
            }

            return $result;
        };

        $response = $this->handle($request, $handler);

        return $response;
    }

    /**
     * Resolves a closure with dependency injection.
     *
     * @param \Closure $callback
     * @param mixed $app
     * @param mixed $routeParams
     * @param mixed $request
     * @return mixed
     */
    private function resolveClosure(\Closure $callback, $app, $routeParams, $request): mixed
    {
        $reflection = new \ReflectionFunction($callback);
        $parameters = $reflection->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType();

            if ($paramType && !$paramType->isBuiltin()) {
                $typeName = $paramType->getName();
                if ($app->has($typeName)) {
                    $dependencies[] = $app->get($typeName);
                } elseif (class_exists($typeName)) {
                    $dependencies[] = $app->make($typeName);
                } else {
                    throw new \Exception("Cannot resolve dependency {$typeName}");
                }
            } elseif (isset($routeParams[$paramName])) {
                $dependencies[] = $routeParams[$paramName];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve parameter {$paramName}");
            }
        }

        return $callback(...$dependencies);
    }

    /**
     * Converts mixed data into a JSON response.
     * - Objects (any class) are JSON-encoded.
     * - Arrays, Collections, Models, and Builders are JSON-encoded.
     * - Non-JSON types (strings, numbers, etc.) are returned as-is.
     * @param $response
     * @param mixed $result
     * @return Response
     */
    private function getResolutionResponse($result, $response): Response
    {
        if ($result instanceof Collection) {
            $response->headers->set('Content-Type', 'application/json');
            $result = json_encode($result->toArray());
        } elseif (
            $result instanceof Model ||
            $result instanceof Builder ||
            $result instanceof \stdClass ||
            $result instanceof \ArrayObject ||
            $result instanceof \JsonSerializable ||
            is_array($result) ||
            is_object($result)
        ) {
            $response->headers->set('Content-Type', 'application/json');
            $result = json_encode($result);
        }

        $response->setBody($result);

        return $response;
    }

    /**
     * Resolves and executes a controller action with dependencies.
     *
     * @param array $callback The controller callback (e.g., [Controller::class, 'action']).
     * @param Application $app The Application instance for resolving dependencies.
     * @param array $routeParams The route parameters.
     * @return mixed The result of the controller action execution.
     * @throws \ReflectionException If there is an issue with reflection.
     * @throws \Exception If dependency resolution fails.
     */
    private function resolveControllerAction(array|string $callback, $app, array $routeParams): mixed
    {
        if (is_string($callback)) {
            $controllerClass = $callback;
            $actionMethod = "__invoke";
        } else {
            [$controllerClass, $actionMethod] = $callback;
        }

        $reflector = new \ReflectionClass($controllerClass);

        $constructorDependencies = $this->resolveConstructorDependencies($reflector, $app, $routeParams);
        $controllerInstance = new $controllerClass(...$constructorDependencies);

        $actionDependencies = $this->resolveActionDependencies($reflector, $actionMethod, $app, $routeParams);

        return call_user_func([$controllerInstance, $actionMethod], ...$actionDependencies);
    }

    /**
     * Resolves constructor dependencies for a controller.
     *
     * @param \ReflectionClass $reflector The reflection class of the controller.
     * @param $app The Application instance for resolving dependencies.
     * @param array $routeParams The route parameters.
     * @return array The resolved constructor dependencies.
     * @throws \Exception If dependency resolution fails.
     */
    private function resolveConstructorDependencies(
        \ReflectionClass $reflector,
        $app,
        array $routeParams
    ): array {
        $constructor = $reflector->getConstructor();
        if (!$constructor) {
            return [];
        }

        return $this->resolveParameters($constructor->getParameters(), $app, $routeParams);
    }

    /**
     * Resolves action dependencies for a controller method.
     *
     * @param \ReflectionClass $reflector The reflection class of the controller.
     * @param string $actionMethod The name of the action method.
     * @param $app The Application instance for resolving dependencies.
     * @param array $routeParams The route parameters.
     * @return array The resolved action dependencies.
     * @throws \ReflectionException If there is an issue with reflection.
     * @throws \Exception If dependency resolution fails.
     */
    private function resolveActionDependencies(
        \ReflectionClass $reflector,
        string $actionMethod,
        $app,
        array $routeParams
    ): array {
        $method = $reflector->getMethod($actionMethod);

        return $this->resolveParameters($method->getParameters(), $app, $routeParams);
    }

    /**
     * Resolves parameters for a method or constructor.
     *
     * @param array $parameters The parameters to resolve.
     * @param $app The Application instance for resolving dependencies.
     * @param array $routeParams The route parameters.
     * @return array The resolved parameters.
     * @throws \Exception If dependency resolution fails.
     */
    private function resolveParameters(array $parameters, $app, array $routeParams): array
    {
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType();

            if ($paramType && !$paramType->isBuiltin()) {
                $resolvedClass = $paramType->getName();

                if (!$app->has($resolvedClass)) {
                    if (is_subclass_of($resolvedClass, FormRequest::class)) {
                        $app->singleton($resolvedClass, function () use ($app, $resolvedClass) {
                            return new $resolvedClass($app->get(Request::class));
                        });
                    } else {
                        $app->singleton($resolvedClass, $resolvedClass);
                    }
                }

                $resolvedInstance = app($resolvedClass);
                if ($resolvedInstance instanceof FormRequest) {
                    $resolvedInstance->resolvedFormRequestValidation();
                }
                $dependencies[] = $resolvedInstance;
            } elseif (isset($routeParams[$paramName])) {
                $dependencies[] = $routeParams[$paramName];
            } elseif ($parameter->isOptional()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve parameter '$paramName'");
            }
        }

        return $dependencies;
    }

    /**
     * Get the current matched route information (path, callback, method, etc.).
     *
     * @return array|null Array with route details or null if no route matched
     */
    public function getRouteNames(): ?array
    {
        return self::$namedRoutes;
    }
}
