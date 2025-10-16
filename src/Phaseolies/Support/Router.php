<?php

namespace Phaseolies\Support;

use Ramsey\Collection\Collection;
use Phaseolies\Utilities\Attributes\Resolver;
use Phaseolies\Utilities\Attributes\Middleware;
use Phaseolies\Utilities\Attributes\BindPayload;
use Phaseolies\Utilities\Attributes\Bind;
use Phaseolies\Support\Router\InteractsWithCurrentRouter;
use Phaseolies\Support\Router\InteractsWithBundleRouter;
use Phaseolies\Middleware\Contracts\Middleware as ContractsMiddleware;
use Phaseolies\Http\Validation\Contracts\ValidatesWhenResolved;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Phaseolies\Database\Eloquent\Model;
use Phaseolies\Database\Eloquent\Builder;
use Phaseolies\Application;
use App\Http\Kernel;

class Router extends Kernel
{
    use InteractsWithBundleRouter, InteractsWithCurrentRouter;

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
     * The path of the current route being defined.
     *
     * @var string|null
     */
    protected ?string $currentRoutePath = null;

    /**
     * The current requested method
     *
     * @var string
     */
    protected string $currentRequestMethod;

    /**
     * The middleware keys for the current route.
     *
     * @var array<string, array<string, array<string>>>
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
     * Stack of group attributes (middleware, prefix, etc.)
     *
     * @var array
     */
    protected static array $groupStack = [];

    /**
     * Cache file path for routes
     *
     * @var string
     */
    protected static string $cachePath;

    /**
     * Flag to track cache loading state
     *
     * @var bool
     */
    protected static bool $cacheLoaded = false;

    /**
     * Initialize the cache path
     *
     * @return void
     */
    protected function initializeCachePath(): void
    {
        if (!isset(static::$cachePath)) {
            static::$cachePath = storage_path('framework/cache/routes.php');
        }
    }

    /**
     * Cache all the routes
     *
     * @return void
     */
    public function cacheRoutes(): void
    {
        $this->initializeCachePath();

        foreach (self::$routeMiddlewares as $method => $routes) {
            foreach ($routes as $path => $middlewares) {
                self::$routeMiddlewares[$method][$path] = array_values(array_unique((array)$middlewares));
            }
        }

        $cacheData = [
            'routes' => $this->getCacheableRoutes(),
            'namedRoutes' => self::$namedRoutes,
            'routeMiddlewares' => self::$routeMiddlewares,
            'timestamp' => time()
        ];

        file_put_contents(static::$cachePath, '<?php return ' . var_export($cacheData, true) . ';');
    }

    /**
     * Get cacheable route excluding closure based route
     *
     * @return array
     */
    protected function getCacheableRoutes(): array
    {
        $cacheableRoutes = [];

        foreach (self::$routes as $method => $routes) {
            foreach ($routes as $path => $callback) {
                if ($this->isCacheableRoute($callback)) {
                    $cacheableRoutes[$method][$path] = $callback;
                }
            }
        }

        return $cacheableRoutes;
    }

    /**
     * Check is the route is cacheable or not
     *
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

        if (is_string($callback)) {
            $reflection = new \ReflectionClass($callback);
            return $reflection->hasMethod('__invoke');
        }

        return false;
    }

    /**
     * Modified loadCachedRoutes to handle the new cache structure
     *
     * @return bool
     */
    public function loadCachedRoutes(): bool
    {
        $this->initializeCachePath();

        if (!file_exists(static::$cachePath)) {
            return false;
        }

        $cached = require static::$cachePath;

        if (!isset($cached['routes']) || !isset($cached['namedRoutes']) || !isset($cached['routeMiddlewares'])) {
            return false;
        }

        self::$routes = $cached['routes'] ?? [];
        self::$namedRoutes = $cached['namedRoutes'] ?? [];
        self::$routeMiddlewares = $cached['routeMiddlewares'] ?? [];

        static::$cacheLoaded = true;

        return true;
    }

    /**
     * Determines whether route caching should be enabled based on environment configuration.
     *
     * @return bool
     */
    public function shouldCacheRoutes(): bool
    {
        return env('APP_ROUTE_CACHE', false) === 'true';
    }

    /**
     * Clears all cached route files from the framework cache directory.
     *
     * @return bool
     */
    public function clearRouteCache(): bool
    {
        $this->initializeCachePath();

        if (file_exists(static::$cachePath)) {
            return @unlink(static::$cachePath);
        }

        return true;
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
     * Register attributes based route
     *
     * @return void
     */
    public function registerAttributeRoutes(): void
    {
        $controllers = $this->getControllerClasses();

        foreach ($controllers as $controllerClass) {
            $this->registerRoutesFromController($controllerClass);
        }
    }

    /**
     * Get all controller classes from app directory
     *
     * @return array
     */
    protected function getControllerClasses(): array
    {
        $controllerPath = base_path('app/Http/Controllers');
        $controllers = [];

        if (!is_dir($controllerPath)) {
            return $controllers;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllerPath)
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
            $relativePath = str_replace($controllerPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
            $className = 'App\\Http\\Controllers\\' . str_replace('.php', '', $relativePath);

            if (class_exists($className)) {
                $controllers[] = $className;
            }
        }

        return $controllers;
    }

    /**
     * Register routes from a controller class using attributes
     *
     * @param string $controllerClass
     * @return void
     */
    protected function registerRoutesFromController(string $controllerClass): void
    {
        $reflection = new \ReflectionClass($controllerClass);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $routeAttributes = $method->getAttributes(\Phaseolies\Utilities\Attributes\Route::class);

            foreach ($routeAttributes as $attribute) {
                $route = $attribute->newInstance();
                $this->registerAttributeRoute($controllerClass, $method->getName(), $route);
            }
        }
    }

    /**
     * Register a single route from attribute
     *
     * @param string $controllerClass
     * @param string $method
     * @param object $route
     * @return void
     */
    protected function registerAttributeRoute(string $controllerClass, string $method, object $route): void
    {
        $path = $route->uri;
        $httpMethods = $route->methods ?? ['GET'];
        $name = $route->name ?? null;
        $middleware = $route->middleware ?? [];

        foreach ($httpMethods as $httpMethod) {
            $this->addRouteNameToAttributesRouting($httpMethod, $path, [$controllerClass, $method], $name);
            if (!empty($middleware)) {
                $this->middleware($middleware);
            }
        }
    }

    /**
     * Add a route name to attribute routing
     *
     * @param string $method
     * @param string $path
     * @param callable|array $callback
     * @param string|null $name
     * @return self
     */
    protected function addRouteNameToAttributesRouting(string $method, string $path, $callback, ?string $name = null): self
    {
        $this->addRoute($method, $path, $callback);

        if ($name) {
            self::$namedRoutes[$name] = $this->currentRoutePath;
        }

        return $this;
    }

    /**
     * Load both file-based and attribute-based routes
     *
     * @return void
     */
    public function loadAttributeBasedRoutes(): void
    {
        $this->loadRoutesFromFiles();

        $this->registerAttributeRoutes();
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
        $request = app('request');

        $method = $request->_method ?? $request->getMethod();

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
     * Register a redirect route
     *
     * @param string $uri The path to redirect from
     * @param string $destination The path or URL to redirect to
     * @param int $status HTTP status code (default: 302)
     * @return self
     */
    public function redirect(string $uri, string $destination, int $status = 302): self
    {
        return $this->get(
            path: $uri,
            callback: function (Request $request) use ($destination, $status) {
                if (strpos($destination, '/') !== 0 && isset(self::$namedRoutes[$destination])) {
                    $destination = $this->route($destination);
                }

                if (filter_var($destination, FILTER_VALIDATE_URL)) {
                    return redirect($destination, $status);
                }

                $destination = str_starts_with($destination, '/') ? $destination : '/' . $destination;

                return redirect($request->getBaseUrl() . $destination, $status);
            }
        );
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
        $this->failFastOnBadRouteDefinition($callback);

        if ($path === '*') {
            $fullPath = '(.*)';
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

        if (!static::$cacheLoaded) {
            if (is_array($callback) || is_string($callback)) {
                $this->processControllerMiddleware($callback);
            }
        }

        return $this;
    }

    /**
     * Validates a route callback and throws exceptions for invalid definitions.
     *
     * @param mixed $callback The route callback to validate
     * @throws \BadMethodCallException When array-style controller method doesn't exist
     * @throws \LogicException When invokable controller lacks __invoke method
     * @throws \InvalidArgumentException For all other invalid callback formats
     * @return void
     */
    public function failFastOnBadRouteDefinition(mixed $callback): void
    {
        if (!is_callable($callback)) {
            if (is_array($callback) && count($callback) === 2 && is_string($callback[0])) {
                if (!method_exists($callback[0], $callback[1])) {
                    throw new \BadMethodCallException(
                        "Method {$callback[0]}::{$callback[1]}() does not exist"
                    );
                }
            } elseif (is_string($callback) && class_exists($callback)) {
                $reflection = new \ReflectionClass($callback);
                if (!$reflection->hasMethod('__invoke')) {
                    throw new \LogicException("Method {$callback}::__invoke() does not exist");
                }
            } else {
                $type = is_object($callback) ? get_class($callback) : gettype($callback);
                throw new \InvalidArgumentException(
                    sprintf(
                        "Invalid route callback: expected array [Controller::class, 'method'], class string, or Closure; got [%s].",
                        $type
                    )
                );
            }
        }
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
            $current = self::$routeMiddlewares[$method][$this->currentRoutePath] ?? [];
            self::$routeMiddlewares[$method][$this->currentRoutePath] = array_merge($current, (array) $keys);
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
     *
     * @param Application $app
     * @param Request $request
     * @throws \ReflectionException If there is an issue with reflection.
     * @throws \Exception
     * @return Response
     */
    public function resolve(Application $app, Request $request): Response
    {
        $callback = $this->getCallback($request);

        if (!$callback) {
            $callback = $this->getOriginalClosure($request);

            if (!$callback) {
                abort(404, "Route [" . $request->getRequestUri() . "] not found");
            }
        }

        if ($currentMiddleware = $this->getCurrentRouteMiddleware($request)) {
            $this->applyRouteMiddleware($request, $app, $currentMiddleware);
        }

        $routeParams = $request->getRouteParams();

        $handler = function ($request) use ($callback, $app, $routeParams) {
            $result = $this->resolveAction($callback, $app, $routeParams);
            $response = $app->make('response');
            $response->setOriginal($result);
            if (!($result instanceof Response)) {
                return $this->getResolutionResponse($request, $result, $response);
            }
            return $result;
        };

        $response = $this->handle($request, $handler);

        return $response;
    }

    /**
     * Get the original closure for a route when loading from cache
     *
     * @param Request $request
     * @return mixed
     */
    protected function getOriginalClosure(Request $request): mixed
    {
        $cachedRoutes = self::$routes;

        self::$routes = [];
        $this->loadRoutesFromFiles();

        $originalCallback = $this->getCallback($request);

        self::$routes = $cachedRoutes;

        return $originalCallback;
    }

    /**
     * Load routes from route files (uncached)
     *
     * @return void
     */
    protected function loadRoutesFromFiles(): void
    {
        $routeFiles = [
            base_path('routes/web.php')
        ];

        foreach ($routeFiles as $file) {
            if (file_exists($file)) {
                require $file;
            }
        }
    }

    /**
     * Handle attributes based middleware defination
     *
     * @param array $callback
     * @return void
     */
    protected function processControllerMiddleware(array|string $callback): void
    {
        if (is_string($callback)) {
            $controllerClass = $callback;
            $actionMethod = "__invoke";
        } else {
            [$controllerClass, $actionMethod] = $callback;
        }

        $reflector = new \ReflectionClass($controllerClass);

        // Process middleware defined at the class level
        $classMiddlewareAttributes = $reflector->getAttributes(Middleware::class);
        $this->processAttributesMiddlewares($classMiddlewareAttributes);

        // Process middleware defined at the method level
        if ($reflector->hasMethod($actionMethod)) {
            $method = $reflector->getMethod($actionMethod);
            $methodMiddlewareAttributes = $method->getAttributes(Middleware::class);
            $this->processAttributesMiddlewares($methodMiddlewareAttributes);
            $this->processRateLimitAnnotation($method);
        }
    }

    /**
     * Resolve form request class
     *
     * @param Application $app The application instance
     * @param string $typeName The class name to resolve
     * @return mixed
     */
    private function resolveFormRequestValidationClass($app, $typeName): mixed
    {
        if (!$app->has($typeName)) {
            if (is_subclass_of($typeName, ValidatesWhenResolved::class)) {
                $app->singleton($typeName, fn() => new $typeName(app('request')));
            } else {
                $app->singleton($typeName, $typeName);
            }
        }

        $resolvedInstance = app($typeName);

        if ($resolvedInstance instanceof ValidatesWhenResolved) {
            $resolvedInstance->resolvedFormRequestValidation();
        }

        return $resolvedInstance;
    }

    /**
     * Converts mixed data into a JSON response.
     *
     * @param $request
     * @param $result
     * @param mixed $response
     * @return Response
     */
    private function getResolutionResponse($request, $result, $response): Response
    {
        if ($this->shouldBeJson($result)) {
            $request->setRequestFormat('json');
            $response->headers->set('Content-Type', 'application/json');
            $result = $this->convertToSerializable($result);
            $response->setBody(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $response->setBody($result ?? '');
        }

        return $response;
    }

    /**
     * Determine if the given data should be returned as JSON.
     *
     * @param mixed $data
     * @return bool
     */
    protected function shouldBeJson($data): bool
    {
        return is_array($data) ||
            is_object($data) ||
            $data instanceof \JsonSerializable ||
            $data instanceof Model ||
            $data instanceof Collection ||
            $data instanceof Builder ||
            $data instanceof \stdClass ||
            $data instanceof \ArrayObject;
    }

    /**
     * Convert various data types into a format suitable for serialization
     *
     * @param mixed $data
     * @return mixed
     */
    protected function convertToSerializable($data)
    {
        if ($data instanceof Model || $data instanceof Collection) {
            return $data->toArray();
        }

        if ($data instanceof Builder) {
            return $data->get()->toArray();
        }

        if ($data instanceof \JsonSerializable) {
            return $data->jsonSerialize();
        }

        if ($data instanceof \stdClass || $data instanceof \ArrayObject) {
            return (array) $data;
        }

        return $data;
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
    private function resolveAction(mixed $callback, $app, array $routeParams): mixed
    {
        if (is_array($callback)) {
            [$controllerClass, $actionMethod] = $callback;
        } else if (is_string($callback)) {
            $controllerClass = $callback;
            $actionMethod = "__invoke";
        } else if ($callback instanceof \Closure) {
            $reflection = new \ReflectionFunction($callback);
            foreach ($reflection->getParameters() as $parameter) {
                $paramType = $parameter->getType();
                if (!$paramType->isBuiltin() && $paramType) {
                    $typeName = $paramType->getName();
                    if (is_subclass_of($typeName, ValidatesWhenResolved::class)) {
                        $this->resolveFormRequestValidationClass($app, $typeName);
                    }
                }
            }

            return $app->call($callback, $routeParams);
        }

        $reflector = new \ReflectionClass($controllerClass);

        $this->processAttributesClassDependencies($controllerClass, $app);
        $this->processAttributesMethodDependencies($reflector, $actionMethod, $app);

        $constructorDependencies = $this->resolveConstructorDependencies($reflector, $app, $routeParams);
        $controllerInstance = new $controllerClass(...$constructorDependencies);

        $actionDependencies = $this->resolveActionDependencies($reflector, $actionMethod, $app, $routeParams);

        return call_user_func([$controllerInstance, $actionMethod], ...$actionDependencies);
    }

    /**
     * Process RateLimit annotations from docblock comments
     *
     * @param \ReflectionMethod $method
     * @return void
     */
    protected function processRateLimitAnnotation(\ReflectionMethod $method): void
    {
        $docComment = $method->getDocComment();

        if (!$docComment) {
            return;
        }

        // Parse the @RateLimit annotation
        if (preg_match('/@RateLimit\s+([^\s]+)/', $docComment, $matches)) {
            $rateLimitConfig = $matches[1];

            // Convert the annotation format "60/1"
            // To throttle middleware format "throttle:60,1"
            if (preg_match('/^(\d+)\/(\d+)$/', $rateLimitConfig, $configMatches)) {
                $maxAttempts = $configMatches[1];
                $decayMinutes = $configMatches[2];
                $middlewareKey = "throttle:{$maxAttempts},{$decayMinutes}";

                $this->middleware($middlewareKey);
            }
        }
    }

    /**
     * Handle class level dependency injection
     *
     * @param string $className
     * @param Application $app
     * @return void
     */
    protected function processAttributesClassDependencies(string $className, $app): void
    {
        $reflection = new \ReflectionClass($className);
        $attributes = $reflection->getAttributes(Resolver::class);

        $this->resolveAttributesDependency($attributes ?? [], $app);
    }

    /**
     * Process attributes based middleware
     *
     * @param array $middlewareAttributes
     * @return void
     */
    public function processAttributesMiddlewares(array $middlewareAttributes): void
    {
        $middlewareToApply = [];

        foreach ($middlewareAttributes as $attribute) {
            $middleware = $attribute->newInstance();
            foreach ($middleware->getMiddlewareClasses() as $middlewareItem) {
                if (
                    isset($this->routeMiddleware['web'][$middlewareItem]) ||
                    isset($this->routeMiddleware['api'][$middlewareItem])
                ) {
                    $middlewareToApply[] = $middlewareItem;
                } elseif (class_exists($middlewareItem)) {
                    $key = array_search($middlewareItem, $this->routeMiddleware['web'], true) ?:
                        array_search($middlewareItem, $this->routeMiddleware['api'], true);
                    if ($key !== false) {
                        $middlewareToApply[] = $key;
                    } else {
                        $middlewareToApply[] = $middlewareItem;
                    }
                }
            }
        }

        // Apply middleware to current route
        if (!empty($middlewareToApply) && $this->currentRoutePath) {
            $method = $this->getCurrentRequestMethod();
            self::$routeMiddlewares[$method][$this->currentRoutePath] = array_merge(
                self::$routeMiddlewares[$method][$this->currentRoutePath] ?? [],
                $middlewareToApply
            );
        }
    }

    /**
     * Handle method attribute dependency injection
     *
     * @param \ReflectionClass $class
     * @param string $methodName
     * @param Application $app
     * @return void
     */
    protected function processAttributesMethodDependencies(\ReflectionClass $class, string $methodName, $app): void
    {
        if (!$class->hasMethod($methodName)) {
            throw new \BadMethodCallException(
                "Method {$class->getName()}::{$methodName}() does not exist"
            );
        }

        $method = $class->getMethod($methodName);
        $attributes = $method->getAttributes(Resolver::class);

        $this->resolveAttributesDependency($attributes ?? [], $app);
    }

    /**
     * Resolve attributes
     *
     * @param array $attributes
     * @param Application $app
     * @return void
     */
    public function resolveAttributesDependency(array $attributes, $app): void
    {
        foreach ($attributes as $attribute) {
            $dependency = $attribute->newInstance();
            $dependency->singleton
                ? $app->singleton($dependency->abstract, $dependency->concrete)
                : $app->bind($dependency->abstract, $dependency->concrete);
        }
    }

    /**
     * Resolves constructor dependencies for a controller.
     *
     * @param \ReflectionClass $reflector The reflection class of the controller.
     * @param Application $app The Application instance for resolving dependencies.
     * @param array $routeParams The route parameters.
     * @return array The resolved constructor dependencies.
     * @throws \Exception If dependency resolution fails.
     */
    private function resolveConstructorDependencies(\ReflectionClass $reflector, Application $app, array $routeParams): array
    {
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
     * @param Application $app The Application instance for resolving dependencies.
     * @param array $routeParams The route parameters.
     * @return array The resolved action dependencies.
     * @throws \ReflectionException If there is an issue with reflection.
     * @throws \Exception If dependency resolution fails.
     */
    private function resolveActionDependencies(\ReflectionClass $reflector, string $actionMethod, Application $app, array $routeParams): array
    {
        $method = $reflector->getMethod($actionMethod);
        $parameters = $method->getParameters();

        $methodParamNames = [];
        foreach ($parameters as $param) {
            $methodParamNames[] = $param->getName();
        }

        $unmatchedParams = array_diff(array_keys($routeParams), $methodParamNames);

        if (!empty($unmatchedParams)) {
            throw new \InvalidArgumentException(
                "Route provides parameter(s) [" . implode(', ', $unmatchedParams) . "] " .
                    "but not accepted by method " . $reflector->getName() . "::" . $actionMethod . "()."
            );
        }

        return $this->resolveParameters($parameters, $app, $routeParams);
    }

    /**
     * Resolves parameters for a method or constructor.
     *
     * @param array $parameters The parameters to resolve.
     * @param Application $app The Application instance for resolving dependencies.
     * @param array $routeParams The route parameters.
     * @return array The resolved parameters.
     * @throws \Exception If dependency resolution fails.
     */
    private function resolveParameters(array $parameters, Application $app, array $routeParams): array
    {
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType();

            // Handle #[BindPayload()]
            $payloadResult = $this->handleBindPayloadAttribute($parameter, $app);
            if ($payloadResult['handled']) {
                $dependencies[] = $payloadResult['instance'];
                continue;
            }

            // Handle #[Bind()]
            $bindResult = $this->handleBindAttribute($parameter, $app);
            if ($bindResult['handled']) {
                $dependencies[] = $bindResult['instance'];
                continue;
            }

            if ($paramType && !$paramType->isBuiltin()) {
                $resolvedClass = $paramType->getName();
                $resolvedInstance = $this->resolveFormRequestValidationClass($app, $resolvedClass);
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
     * Handle BindPayload attribute for a parameter
     *
     * @param \ReflectionParameter $parameter
     * @param Application $app
     * @return array
     * @throws \Exception
     */
    private function handleBindPayloadAttribute(\ReflectionParameter $parameter, Application $app): array
    {
        $mapAttributes = $parameter->getAttributes(BindPayload::class);
        if (empty($mapAttributes)) {
            return ['handled' => false, 'instance' => null];
        }

        $paramName = $parameter->getName();
        $paramType = $parameter->getType();

        if (!$paramType || $paramType->isBuiltin()) {
            throw new \Exception("Parameter '$paramName' must be a class-typed DTO when using Payload");
        }

        $dtoClass = $paramType->getName();
        if (!class_exists($dtoClass)) {
            throw new \Exception("Cannot resolve DTO class '$dtoClass' for parameter '$paramName'");
        }

        $dto = $app->make($dtoClass);
        $request = $app->make('request');
        $attributeInstance = $mapAttributes[0]->newInstance();
        $instance = $request->bindTo($dto, (bool)($attributeInstance->strict ?? true));

        return ['handled' => true, 'instance' => $instance];
    }

    /**
     * Handle Bind attribute for a parameter
     *
     * @param \ReflectionParameter $parameter
     * @param Application $app
     * @return array
     * @throws \Exception
     */
    private function handleBindAttribute(\ReflectionParameter $parameter, Application $app): array
    {
        $binds = $parameter->getAttributes(Bind::class);
        if (empty($binds)) {
            return ['handled' => false, 'instance' => null];
        }

        $paramName = $parameter->getName();
        $paramType = $parameter->getType();

        if (!$paramType || $paramType->isBuiltin()) {
            throw new \Exception("Parameter '$paramName' must be a class-typed when using Bind");
        }

        $bindAttribute = $binds[0];
        $bindInstance = $bindAttribute->newInstance();

        $abstract = $paramType->getName();
        $bindInstance->singleton
            ? $app->singleton($abstract, $bindInstance->concrete)
            : $app->bind($abstract, $bindInstance->concrete);

        $instance = $app->make($abstract);

        return ['handled' => true, 'instance' => $instance];
    }
}
