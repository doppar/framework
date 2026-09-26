<?php

namespace Phaseolies\Support;

use Ramsey\Collection\Collection;
use Phaseolies\Middleware\Attributes\Middleware;
use Phaseolies\Support\Router\Plan\ActionPlanStore;
use Phaseolies\Support\Router\InteractsWithCurrentRouter;
use Phaseolies\Support\Router\InteractsWithBundleRouter;
use Phaseolies\Support\Router\InteractsWithDynamicControllerBinding;
use Phaseolies\Middleware\Contracts\Middleware as ContractsMiddleware;
use Phaseolies\Middleware\Middleware as MiddlewareChain;
use Phaseolies\Http\Validation\Contracts\ValidatesWhenResolved;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Builder;
use Phaseolies\Application;
use Phaseolies\Http\Contracts\GatewayInterface;

class Router
{
    use InteractsWithBundleRouter, InteractsWithCurrentRouter, InteractsWithDynamicControllerBinding;

    /**
     * The application's HTTP middleware gateway
     *
     * @var GatewayInterface
     */
    protected GatewayInterface $gateway;

    /**
     * Compiled and memoized action plans
     *
     * @var ActionPlanStore|null
     */
    private ?ActionPlanStore $actionPlans = null;

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
     * Global middleware pushed at runtime by launchers (e.g. packages)
     *
     * @var array<int, class-string>
     */
    protected array $pushedGlobalMiddleware = [];

    /**
     * Create a new router instance.
     *
     * @param GatewayInterface $gateway
     */
    public function __construct(GatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Get the application's HTTP middleware gateway.
     *
     * @return GatewayInterface
     */
    public function getGateway(): GatewayInterface
    {
        return $this->gateway;
    }

    /**
     * Push a middleware onto the global chain from a launcher
     *
     * @param class-string $middleware
     * @return void
     */
    public function pushGlobalMiddleware(string $middleware): void
    {
        if (!in_array($middleware, $this->pushedGlobalMiddleware, true)) {
            $this->pushedGlobalMiddleware[] = $middleware;
        }
    }

    /**
     * Get the global middleware: the gateway's list followed by any pushed ones.
     *
     * @return array<int, class-string|string>
     */
    public function getGlobalMiddleware(): array
    {
        $global = $this->gateway->getGlobalMiddleware();

        foreach ($this->pushedGlobalMiddleware as $middleware) {
            if (!in_array($middleware, $global, true)) {
                $global[] = $middleware;
            }
        }

        return $global;
    }

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

        $this->cacheActionPlans($cacheData['routes']);
    }

    /**
     * Compile the action plan of every cached route
     *
     * @param array $routes
     * @return void
     */
    protected function cacheActionPlans(array $routes): void
    {
        $actions = [];

        foreach ($routes as $entries) {
            foreach ($entries as $entry) {
                ['callback' => $callback] = $this->unwrapRouteEntry($entry);

                $action = is_array($callback) ? $callback : [$callback, '__invoke'];

                $actions[ActionPlanStore::key($action[0], $action[1])] = $action;
            }
        }

        $this->actionPlans()->compile(array_values($actions));
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
            foreach ($routes as $path => $entry) {
                ['callback' => $callback, 'domain' => $domain] = $this->unwrapRouteEntry($entry);

                if ($this->isCacheableRoute($entry)) {
                    $cacheableRoutes[$method][$path] = $domain
                        ? ['__callback' => $callback, '__domain' => $domain]
                        : $callback;
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
        if (is_array($callback) && isset($callback['__callback'], $callback['__domain'])) {
            $callback = $callback['__callback'];
        }

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

        // Plans compiled with these routes are valid for them; use them.
        $this->actionPlans()->useCompiled();

        return true;
    }

    /**
     * Determines whether route caching should be enabled based on environment configuration.
     *
     * @return bool
     */
    public function shouldCacheRoutes(): bool
    {
        return env('APP_ROUTE_CACHE', false);
    }

    /**
     * Clears all cached route files from the framework cache directory.
     *
     * @return bool
     */
    public function clearRouteCache(): bool
    {
        $this->initializeCachePath();

        $plansCleared = $this->actionPlans()->clear();

        if (file_exists(static::$cachePath)) {
            return @unlink(static::$cachePath) && $plansCleared;
        }

        return $plansCleared;
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
     * Get all controller classes using Composer autodiscovery
     *
     * @return array
     */
    protected function getControllerClasses(): array
    {
        $controllers = [];
        $composerLoader = $this->getComposerClassLoader();

        $prefixes = $composerLoader?->getPrefixesPsr4() ?? [];

        foreach ($prefixes as $namespace => $paths) {
            if (
                !str_starts_with($namespace, 'App\\') &&
                !str_starts_with($namespace, 'Modules\\')
            ) {
                continue;
            }

            foreach ($paths as $path) {

                if (!is_dir($path)) {
                    continue;
                }

                $files = $this->findPhpFiles($path);

                foreach ($files as $file) {

                    $class = $this->convertFileToClassName($file, $path, $namespace);

                    if (!$class || !class_exists($class)) {
                        continue;
                    }

                    if ($this->isControllerClass($class)) {
                        $controllers[] = $class;
                    }
                }
            }
        }

        $controllers = array_unique($controllers);

        if (empty($controllers)) {
            return $this->scanDefaultControllerDirectory();
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

        $mapperAttributes = $reflection->getAttributes(\Phaseolies\Support\Router\Attributes\Mapper::class);
        $classPrefix = null;
        $classMiddleware = [];

        if (!empty($mapperAttributes)) {
            $mapper = $mapperAttributes[0]->newInstance();
            $classPrefix = $mapper->prefix;
            $classMiddleware = $mapper->middleware ?? [];
        }

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $routeAttributes = $method->getAttributes(\Phaseolies\Support\Router\Attributes\Route::class);

            foreach ($routeAttributes as $attribute) {
                $route = $attribute->newInstance();

                // Apply class prefix to route URI
                if ($classPrefix) {
                    $route->uri = '/' . trim($classPrefix, '/') . '/' . ltrim($route->uri, '/');
                    $route->uri = rtrim($route->uri, '/') ?: '/';
                }

                $mergedMiddleware = array_merge($classMiddleware, $route->middleware ?? []);
                $route->middleware = $mergedMiddleware;

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
        $rateLimit = $route->rateLimit ?? null;
        $rateLimitDecay = $route->rateLimitDecay ?? 1;
        $domain = $route->domain ?? null;

        foreach ($httpMethods as $httpMethod) {
            $this->addRouteNameToAttributesRouting($httpMethod, $path, [$controllerClass, $method], $name, $domain);
            if (!empty($middleware)) {
                $this->middleware($middleware);
            }

            if ($rateLimit) {
                $this->middleware("throttle:{$rateLimit},{$rateLimitDecay}");
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
     * @param string|null $domain
     * @return self
     */
    protected function addRouteNameToAttributesRouting(string $method, string $path, $callback, ?string $name = null, ?string $domain = null): self
    {
        $this->addRoute($method, $path, $callback, $domain);

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
     * Unwrap the route entry, separating the callback from optional domain metadata.
     *
     * @param mixed $entry
     * @return array{callback: mixed, domain: string|null}
     */
    protected function unwrapRouteEntry(mixed $entry): array
    {
        if (is_array($entry) && isset($entry['__callback'], $entry['__domain'])) {
            return ['callback' => $entry['__callback'], 'domain' => $entry['__domain']];
        }

        return ['callback' => $entry, 'domain' => null];
    }

    /**
     * Add a route with group attributes applied.
     *
     * @param string $method
     * @param string $path
     * @param callable|array $callback
     * @param string|null $domain
     * @return self
     */
    protected function addRoute(string $method, string $path, $callback, ?string $domain = null): self
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

        $entry = $domain
            ? ['__callback' => $callback, '__domain' => $domain]
            : $callback;

        self::$routes[$method][$fullPath] = $entry;
        $this->currentRoutePath = $fullPath;
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
     * Restricts the last registered route to a specific domain.
     * Supports exact domains ('api.example.com'), wildcard subdomains
     * ('{tenant}.example.com'), and universal wildcard ('*').
     *
     * @param string $domain The domain pattern to restrict to.
     * @return self
     */
    public function domain(string $domain): self
    {
        if ($this->currentRoutePath) {
            $method = $this->getCurrentRequestMethod();
            $entry  = self::$routes[$method][$this->currentRoutePath];

            ['callback' => $callback] = $this->unwrapRouteEntry($entry);

            self::$routes[$method][$this->currentRoutePath] = [
                '__callback' => $callback,
                '__domain'   => $domain,
            ];
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
        $url    = $request->getPath();

        $url = ($url !== '/') ? rtrim($url, '/') : $url;

        $routes = self::$routes[$method] ?? [];

        foreach ($routes as $route => $entry) {
            ['callback' => $callback, 'domain' => $domain] = $this->unwrapRouteEntry($entry);

            // Domain guard — skip routes whose domain pattern doesn't match the request host
            if ($domain !== null && !$this->matchesDomain($domain, $request)) {
                continue;
            }

            // Exact match
            if ($route === $url) {
                return $callback;
            }

            // Catch-all wildcard
            if ($route === '(.*)') {
                return $callback;
            }

            // Pattern match with named parameters
            $routeRegex = $this->convertRouteToRegex($route);

            if (preg_match($routeRegex, $url, $matches)) {
                $params = $this->extractRouteParameters($route, $matches);
                if ($params !== false) {
                    $existing = $request->getRouteParams();
                    $merged = array_merge($existing, $params);
                    $request->setRouteParams($merged);
                    return $callback;
                }
            }
        }

        return false;
    }

    /**
     * Match the incoming host against a route domain pattern.
     * Supports:
     *   - Exact domain:            'api.example.com'
     *   - Port-qualified domain:   'localhost:8000'
     *   - Wildcard subdomain:      '{tenant}.example.com' (injects param into route params)
     *   - Universal wildcard:      '*'
     *
     * @param string $domain
     * @param mixed  $request
     * @return bool
     */
    protected function matchesDomain(string $domain, $request): bool
    {
        $host = $request->getHost();
        $host = strtolower($host);

        // Universal wildcard: match any host
        if ($domain === '*') {
            return true;
        }

        // Named subdomain wildcard: {subdomain}.example.com
        if (preg_match('/^\{(\w+)\}\.(.+)$/', $domain, $m)) {
            // Strip port from host before matching the suffix
            $bareHost     = explode(':', $host)[0];
            $domainSuffix = strtolower($m[2]);
            $pattern      = '/^([^.]+)\.' . preg_quote($domainSuffix, '/') . '$/';

            if (preg_match($pattern, $bareHost, $hostMatch)) {
                // Inject the captured subdomain segment as a route parameter
                $existing        = $request->getRouteParams();
                $existing[$m[1]] = $hostMatch[1];
                $request->setRouteParams($existing);
                $request->mergeIfMissing([$m[1] => $hostMatch[1]]);

                return true;
            }

            return false;
        }

        // Exact match — intentionally port-aware so 'localhost:8000' != 'localhost'
        return $host === strtolower($domain);
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
     * @param MiddlewareChain $chain The request-local chain to apply onto.
     * @return void
     * @throws \Exception
     */
    private function applyRouteMiddleware($request, $app, $currentMiddleware, MiddlewareChain $chain): void
    {
        $routeMiddleware = $this->gateway->getRouteMiddleware();

        foreach ($currentMiddleware as $key) {
            [$name, $params] = array_pad(explode(':', $key, 2), 2, null);
            $params = $params ? explode(',', $params) : [];
            if (!$request->isApiRequest()) {
                if (!isset($routeMiddleware['web'][$name])) {
                    throw new \Exception("Undefined middleware [$name]");
                }

                $middlewareClass = $routeMiddleware['web'][$name];
            } else {
                if (!isset($routeMiddleware['api'][$name])) {
                    throw new \Exception("Undefined middleware [$name]");
                }

                $middlewareClass = $routeMiddleware['api'][$name];
            }

            $chain->applyMiddleware($this->makeGatewayMiddleware($app, $middlewareClass), $params);
        }
    }

    /**
     * Resolve a middleware class name to an instance, validating its contract.
     *
     * @param Application $app
     * @param string $middlewareClass
     * @return ContractsMiddleware
     * @throws \Exception
     */
    private function makeGatewayMiddleware($app, string $middlewareClass): ContractsMiddleware
    {
        $middlewareInstance = $app->make($middlewareClass);

        if (!$middlewareInstance instanceof ContractsMiddleware) {
            throw new \Exception("Unresolved dependency $middlewareClass", 1);
        }

        return $middlewareInstance;
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

        $routeParams = $request->getRouteParams();

        $handler = function ($request) use ($callback, $app, $routeParams) {
            $result = $this->resolveAction($callback, $app, $routeParams);
            if (!($result instanceof Response)) {
                return $this->getResolutionResponse($request, $result);
            }
            return $result;
        };

        $chain = new MiddlewareChain();

        if ($currentMiddleware = $this->getCurrentRouteMiddleware($request)) {
            $this->applyRouteMiddleware($request, $app, $currentMiddleware, $chain);
        }

        $groupKey = $request->isApiRequest() ? 'api' : 'web';
        foreach ($this->gateway->getMiddlewareGroups()[$groupKey] ?? [] as $middlewareClass) {
            $chain->applyMiddleware($this->makeGatewayMiddleware($app, $middlewareClass));
        }

        foreach ($this->getGlobalMiddleware() as $middlewareClass) {
            $chain->applyMiddleware($this->makeGatewayMiddleware($app, $middlewareClass));
        }

        return $chain->handle($request, $handler);
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
            base_path('runtime/routes/web.php')
        ];

        foreach ($routeFiles as $file) {
            if (file_exists($file)) {
                require $file;
            }
        }
    }

    /**
     * Process Throttle attribute from controller method
     *
     * @param \ReflectionMethod $method
     * @return void
     */
    protected function processThrottleAttribute(\ReflectionMethod $method): void
    {
        $throttleAttributes = $method->getAttributes(\Phaseolies\Support\Router\Attributes\Throttle::class);

        if (empty($throttleAttributes)) {
            return;
        }

        $throttle = $throttleAttributes[0]->newInstance();

        // Convert to middleware format: throttle:60,1
        $middlewareKey = "throttle:{$throttle->maxAttempts},{$throttle->decayMinutes}";

        $this->middleware($middlewareKey);
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
            $this->processThrottleAttribute($method);
        }
    }

    /**
     * Replace the store that serves action plans
     *
     * @param ActionPlanStore $store
     * @return void
     */
    public function useActionPlans(ActionPlanStore $store): void
    {
        $this->actionPlans = $store;
    }

    /**
     * Get the store that serves compiled action plans
     *
     * @return ActionPlanStore
     */
    public function actionPlans(): ActionPlanStore
    {
        return $this->actionPlans ??= new ActionPlanStore(function (): string {
            $this->initializeCachePath();

            return dirname(static::$cachePath) . DIRECTORY_SEPARATOR . 'actions.php';
        });
    }

    /**
     * Resolves and runs an action with its dependencies injected.
     *
     * @param mixed $callback
     * @param Application $app
     * @param array $routeParams
     * @return mixed
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function resolveAction(mixed $callback, $app, array $routeParams): mixed
    {
        if ($callback instanceof \Closure) {
            $plan = $this->actionPlans()->forClosure($callback);

            return $callback(...$this->resolvePlannedParameters($plan['action'], $app, $routeParams, $callback));
        }

        if (is_array($callback)) {
            [$controllerClass, $actionMethod] = $callback;
        } elseif (is_string($callback)) {
            $controllerClass = $callback;
            $actionMethod = '__invoke';
        } else {
            throw new \InvalidArgumentException(
                'Invalid route callback: expected [Controller::class, \'method\'], a class string or a Closure; got ' . get_debug_type($callback) . '.'
            );
        }

        $plan = $this->actionPlans()->forAction($controllerClass, $actionMethod);

        foreach ($plan['resolvers'] as [$abstract, $concrete, $singleton]) {
            $singleton
                ? $app->singleton($abstract, $concrete)
                : $app->bind($abstract, $concrete);
        }

        $constructorDependencies = $plan['constructor'] === null
            ? []
            : $this->resolvePlannedParameters($plan['constructor'], $app, $routeParams, [$controllerClass, '__construct'], true);

        $controllerInstance = new $controllerClass(...$constructorDependencies);

        $unmatched = array_diff(array_keys($routeParams), $plan['action']['names']);

        if (!empty($unmatched)) {
            throw new \InvalidArgumentException(
                "Route provides parameter(s) [" . implode(', ', $unmatched) . "] " .
                    "but not accepted by method " . $plan['class'] . "::" . $actionMethod . "()."
            );
        }

        $actionDependencies = $this->resolvePlannedParameters($plan['action'], $app, $routeParams, [$controllerClass, $actionMethod], true);

        // Check if method should be wrapped in a transaction
        if ($plan['transaction'] !== null) {
            return $this->executeInTransaction(
                $controllerInstance,
                $actionMethod,
                $actionDependencies,
                $plan['transaction'][0],
                $plan['transaction'][1]
            );
        }

        return call_user_func([$controllerInstance, $actionMethod], ...$actionDependencies);
    }

    /**
     * Resolve the arguments of a planned function
     *
     * @param array<string, mixed> $function
     * @param Application $app
     * @param array $routeParams
     * @param \Closure|array{0: string, 1: string} $target
     * @param bool $forController
     * @return array
     */
    private function resolvePlannedParameters(array $function, Application $app, array $routeParams, \Closure|array $target, bool $forController = false): array
    {
        $dependencies = [];

        foreach ($function['parameters'] as $parameter) {
            // #[Model] attribute - HIGHEST PRIORITY
            if ($parameter['model'] !== null) {
                $dependencies[] = $this->resolveModelParameter($parameter, $routeParams);
                continue;
            }

            // #[BindPayload()]
            if ($parameter['payload'] !== null) {
                $dependencies[] = $this->resolvePayloadParameter($parameter, $app);
                continue;
            }

            // #[Bind()]
            if ($parameter['bind'] !== null) {
                $dependencies[] = $this->resolveBindParameter($parameter, $app);
                continue;
            }

            $name = $parameter['name'];

            if ($parameter['type'] !== null) {
                $typeName = $parameter['type'];

                if (!$forController) {
                    if (is_subclass_of($typeName, ValidatesWhenResolved::class)) {
                        $this->resolveFormRequestValidationClass($app, $typeName);
                    }

                    $dependencies[] = $app->make($typeName);
                    continue;
                }

                if (!$app->has($typeName) && !class_exists($typeName)) {
                    throw new \InvalidArgumentException(
                        ($function['declaring'] ? $function['declaring'] . '::' : '') .
                            $function['name'] .
                            "(): Argument #" . ($parameter['index'] + 1) . " (\${$name}) cannot be resolved. " .
                            "'{$typeName}' is not bound in the container. "
                    );
                }

                $dependencies[] = $this->resolveFormRequestValidationClass($app, $typeName);
            } elseif (isset($routeParams[$name])) {
                $dependencies[] = $routeParams[$name];
            } elseif ($parameter['optional']) {
                $dependencies[] = $parameter['lazyDefault']
                    ? $this->readDefault($parameter, $target)
                    : $parameter['default'];
            } elseif ($forController) {
                throw new \Exception("Cannot resolve parameter '$name'");
            } else {
                throw new \Exception("Cannot resolve parameter '$name' for closure");
            }
        }

        return $dependencies;
    }

    /**
     * Read the default of a parameter whose default cannot be stored in a plan
     *
     * @param array<string, mixed> $parameter
     * @param \Closure|array{0: string, 1: string} $target
     * @return mixed
     */
    private function readDefault(array $parameter, \Closure|array $target): mixed
    {
        $function = $target instanceof \Closure
            ? new \ReflectionFunction($target)
            : new \ReflectionMethod($target[0], $target[1]);

        return $function->getParameters()[$parameter['index']]->getDefaultValue();
    }

    /**
     * Resolve a parameter marked with #[BindPayload]
     *
     * @param array<string, mixed> $parameter
     * @param Application $app
     * @return object
     */
    private function resolvePayloadParameter(array $parameter, Application $app): object
    {
        $paramName = $parameter['name'];

        if ($parameter['builtin'] || !$parameter['typed']) {
            throw new \Exception("Parameter '$paramName' must be a class-typed DTO when using Payload");
        }

        $dtoClass = $parameter['type'];
        if (!class_exists($dtoClass)) {
            throw new \Exception("Cannot resolve DTO class '$dtoClass' for parameter '$paramName'");
        }

        [$strict, $validate] = $parameter['payload'];

        $dto = $app->make($dtoClass);
        $request = $app->make('request');

        $instance = $validate
            ? $request->validateDto($dto, $strict)
            : $request->bindTo($dto, $strict);

        return $instance;
    }

    /**
     * Resolve a parameter marked with #[Bind]
     *
     * @param array<string, mixed> $parameter
     * @param Application $app
     * @return mixed
     */
    private function resolveBindParameter(array $parameter, Application $app): mixed
    {
        if ($parameter['builtin'] || !$parameter['typed']) {
            throw new \Exception("Parameter '{$parameter['name']}' must be a class-typed when using Bind");
        }

        $abstract = $parameter['type'];
        [$concrete, $singleton] = $parameter['bind'];

        $singleton
            ? $app->singleton($abstract, $concrete)
            : $app->bind($abstract, $concrete);

        return $app->make($abstract);
    }

    /**
     * Resolve a parameter marked with #[Model]
     *
     * @param array<string, mixed> $parameter
     * @param array $routeParams
     * @return mixed
     */
    private function resolveModelParameter(array $parameter, array $routeParams): mixed
    {
        $paramName = $parameter['name'];

        // Ensure parameter has a type hint
        if ($parameter['builtin'] || !$parameter['typed']) {
            throw new \Exception(
                "Parameter '\$$paramName' must have a class type hint when using #[Model] attribute"
            );
        }

        $modelClass = $parameter['type'];
        [$attributeColumn, $exception] = $parameter['model'];

        $modelInstance = app($modelClass);
        $modelRouteKey = $modelInstance->getRouteKeyName();
        $modelPrimaryKey = $modelInstance->getPrimaryKey();

        $column = $attributeColumn ?? $modelRouteKey;

        if (!isset($routeParams[$paramName])) {
            throw new \Exception(
                "Route parameter '\$$paramName' not found in URL for model binding"
            );
        }

        return $this->resolveModelInstance($modelClass, $column, $routeParams[$paramName], $modelPrimaryKey, $exception);
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
     * @return Response
     */
    private function getResolutionResponse($request, $result): Response
    {
        $response = response()->make();
        $response->setOriginal($result);

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
     * Execute a controller action within a database transaction
     *
     * @param object $controllerInstance
     * @param string $actionMethod
     * @param array $actionDependencies
     * @param string|null $connection
     * @param int $attempts
     * @return mixed
     * @throws \Throwable
     */
    protected function executeInTransaction(
        object $controllerInstance,
        string $actionMethod,
        array $actionDependencies,
        ?string $connection,
        int $attempts
    ): mixed {
        $db = new \Phaseolies\Database\Database($connection);

        return $db->transaction(function () use ($controllerInstance, $actionMethod, $actionDependencies) {
            return call_user_func([$controllerInstance, $actionMethod], ...$actionDependencies);
        }, $attempts);
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
     * Process attributes based middleware
     *
     * @param array $middlewareAttributes
     * @return void
     */
    public function processAttributesMiddlewares(array $middlewareAttributes): void
    {
        $middlewareToApply = [];
        $routeMiddleware = $this->gateway->getRouteMiddleware();

        foreach ($middlewareAttributes as $attribute) {
            $middleware = $attribute->newInstance();
            foreach ($middleware->getMiddlewareClasses() as $middlewareItem) {
                if (
                    isset($routeMiddleware['web'][$middlewareItem]) ||
                    isset($routeMiddleware['api'][$middlewareItem])
                ) {
                    $middlewareToApply[] = $middlewareItem;
                } elseif (class_exists($middlewareItem)) {
                    $key = array_search($middlewareItem, $routeMiddleware['web'], true) ?:
                        array_search($middlewareItem, $routeMiddleware['api'], true);
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
     * Resolve a model instance from the database
     *
     * @param string $modelClass
     * @param string $column
     * @param mixed $value
     * @param string $modelPrimaryKey
     * @param bool $exception
     * @return Model
     * @throws \Exception
     */
    private function resolveModelInstance(
        string $modelClass,
        string $column,
        mixed $value,
        string $modelPrimaryKey,
        bool $exception
    ): ?Model {
        if ($column === $modelPrimaryKey) {
            $instance = $modelClass::find($value);
        } elseif ($column !== $modelPrimaryKey) {
            $instance = $modelClass::where($column, $value)->first();
        } else {
            throw new \Exception(
                "Model class '$modelClass' does not have a suitable method for model binding"
            );
        }

        if (!$instance && $exception) {
            abort(404, "$modelClass not found with $column = $value");
        }

        return $instance;
    }

}
