<?php

use Phaseolies\Utilities\Paginator;
use Phaseolies\Translation\Translator;
use Phaseolies\Support\UrlGenerator;
use Phaseolies\Support\StringService;
use Phaseolies\Support\Facades\Log;
use Phaseolies\Support\Facades\Crypt;
use Phaseolies\Support\CookieJar;
use Phaseolies\Support\Collection;
use Phaseolies\Session\MessageBag;
use Phaseolies\Http\Response\RedirectResponse;
use Phaseolies\Http\ResponseFactory;
use Phaseolies\Http\Response;
use Phaseolies\Http\Controllers\Controller;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Phaseolies\Config\Config;
use Phaseolies\Cache\RateLimiter;
use Phaseolies\Auth\Security\Authenticate;
use Carbon\Carbon;
use Phaseolies\Auth\ActorManager;

if (!function_exists('env')) {
    /**
     * Gets an environment variable from available sources
     *
     * @param string $key
     * @param string|float|int|bool|null $default
     * @return string|float|int|bool|null
     */
    function env(string $key, string|float|int|bool|null $default = null): string|float|int|bool|null
    {
        return dopparEnv($key, $default);
    }
}

if (!function_exists('dopparEnv')) {
    /**
     * Retrieves an environment variable or returns a default value if the variable is not set.
     *
     * @param string $key
     * @param string|float|int|bool|null $default
     * @return string|float|int|bool|null
     */
    function dopparEnv(string $key, string|float|int|bool|null $default = null): string|float|int|bool|null
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return $value !== false ? $value : $default;
    }
}

if (!function_exists('app')) {
    /**
     * Get the available container instance
     *
     * @param string|class-string|null $abstract
     * @param array $parameters
     * @return mixed
     */
    function app($abstract = null, array $parameters = [])
    {
        if (is_null($abstract)) {
            return Container::getInstance();
        }

        return Container::getInstance()->get($abstract, $parameters);
    }
}

if (!function_exists('request')) {
    /**
     * Creates a new request instance to handle HTTP requests.
     *
     * @return mixed
     */
    function request($key = null, $default = null): mixed
    {
        if (is_null($key)) {
            return app('request');
        }

        if (is_array($key)) {
            return app('request')->only($key);
        }

        return app('request')->input($key, $default);
    }
}

if (!function_exists('auth')) {
    /**
     * Get the ActorManager, or resolve a specific actor by name.
     *
     * Usage:
     *   auth()           → ActorManager (proxies calls to the default actor)
     *   auth('web')      → Authenticate instance for the "web" actor
     *   auth('admin')    → Authenticate instance for the "admin" actor
     *
     * @param string|null $actor
     * @return ActorManager|Authenticate
     */
    function auth(?string $actor = null): ActorManager|Authenticate
    {
        /** @var ActorManager $manager */
        $manager = app(ActorManager::class);

        return $actor !== null ? $manager->actor($actor) : $manager;
    }
}

if (!function_exists('trans')) {
    /**
     * Generate translation
     *
     * @return string
     */
    function trans($key, array $replace = [], $locale = null): string
    {
        return app('translator')->trans($key, $replace, $locale);
    }
}

if (!function_exists('lang')) {
    /**
     * Get the translator instance
     *
     * @return Translator
     */
    function lang(): Translator
    {
        return app('translator');
    }
}

if (!function_exists('url')) {
    /**
     * Generate a url for the application.
     *
     * @param string|null $path
     * @param mixed $parameters
     * @param bool|null $secure
     * @return ($path is null ? \Phaseolies\Support\UrlGenerator : string)
     */
    function url(?string $path = null, $parameters = [], ?bool $secure = null)
    {
        $urlGenerator = app(UrlGenerator::class);

        if (is_null($path)) {
            return $urlGenerator;
        }

        return $urlGenerator->to($path, $parameters, $secure)->make();
    }
}

if (!function_exists('response')) {
    /**
     * Creates a new response instance to handle HTTP requests.
     *
     * @param null $content
     * @param int $status
     * @param array $headers
     * @return ($content is null ? \Phaseolies\Http\ResponseFactory : \Phaseolies\Http\Response)
     */
    function response($content = null, $status = 200, array $headers = [])
    {
        $factory = app(ResponseFactory::class);

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($content ?? '', $status, $headers);
    }
}

if (!function_exists('view')) {
    /**
     * Renders a view with the given data.
     *
     * @param string $view
     * @param array $data
     * @return Response
     */
    function view($view, array $data = [], array $headers = []): Response
    {
        $instance = app(Controller::class);
        $content = $instance->render($view, $data, true);

        return response($content, 200, $headers)->setOriginal([
            'view' => $view,
            'data' => $data,
        ]);
    }
}

if (!function_exists('redirect')) {
    /**
     * Creates a new redirect instance for handling HTTP redirects.
     *
     * @param string|null $to
     * @param int $status
     * @param array $headers
     * @param bool|null $secure
     * @return RedirectResponse
     */
    function redirect($to = null, $status = 302, $headers = [], $secure = null)
    {
        $redirect = new RedirectResponse();

        if (is_null($to)) {
            return $redirect;
        }

        return $redirect->to($to, $status, $headers, $secure);
    }
}

if (!function_exists('back')) {
    /**
     * Create a new redirect response to the previous location.
     *
     * @param int $status
     * @param array $headers
     * @param mixed $fallback
     * @return \Phaseolies\Http\RedirectResponse
     */
    function back($status = 302, $headers = [], $fallback = false)
    {
        return redirect()->back($status, $headers, $fallback);
    }
}

if (!function_exists('session')) {
    /**
     * Creates a new Session instance
     *
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    function session($key = null, $default = null): mixed
    {
        if (is_null($key)) {
            return app('session');
        }

        if (is_array($key)) {
            return app('session')->put($key);
        }

        return app('session')->get($key, $default);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Fetch csrf token
     *
     * @return null|string
     */
    function csrf_token(): ?string
    {
        return session('_token') ?? null;
    }
}

if (!function_exists('bcrypt')) {
    /**
     * Creates a password hashing helper
     *
     * @param string $plainText
     * @return string
     */
    function bcrypt(string $plainText): string
    {
        return app('hash')->make($plainText);
    }
}

if (!function_exists('old')) {
    /**
     * Retrieves the old input value for a given key from the session.
     *
     * @param mixed $key
     * @return string|null
     */
    function old($key): ?string
    {
        return MessageBag::old($key);
    }
}

if (!function_exists('fake')) {
    /**
     * Creates and returns a Faker generator instance for generating fake data.
     *
     * @return \Faker\Generator
     */
    function fake(): \Faker\Generator
    {
        $faker = \Faker\Factory::create();

        return $faker;
    }
}

if (!function_exists('route')) {
    /**
     * Generates a full URL for a named route.
     *
     * @param string $name
     * @param mixed $params
     * @return string|null
     */
    function route(string $name, mixed $params = []): ?string
    {
        return app('url')->route($name, $params);
    }
}

if (!function_exists('config')) {
    /**
     * Retrieve a configuration value by key.
     *
     * @param string $key
     * @param string $default
     * @return mixed
     */
    function config(string|array $key, ?string $default = null): mixed
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                Config::set($k, $v);
            }
            return null;
        }

        return Config::get($key, $default);
    }
}

if (!function_exists('cache')) {
    /**
     * Get the cache store instance, retrieve an item, or store multiple items
     *
     * @param string|array|null $key
     * @param mixed $default
     * @return mixed
     */
    function cache(string|array|null $key = null, mixed $default = null): mixed
    {
        $store = app('cache');

        if (is_null($key)) {
            return $store;
        }

        if (is_array($key)) {
            return $store->setMultiple($key, $default);
        }

        return $store->get($key, $default);
    }
}

if (!function_exists('is_auth')) {
    /**
     * Check if the user is authenticated.
     *
     * @return bool
     */
    function is_auth(): bool
    {
        return app('auth')->check();
    }
}

if (!function_exists('str')) {
    /**
     * Instance of the StringService class
     *
     * @return StringService
     */
    function str(): StringService
    {
        return app('str');
    }
}

if (!function_exists('paginator')) {
    /**
     * Get the paginator instance
     *
     * @return Paginator
     */
    function paginator(?array $data = null): Paginator
    {
        return app(Paginator::class, [$data]);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the base path of the application.
     *
     * @param string $path
     * @return string
     */
    function base_path(string $path = ''): string
    {
        static $basePath = null;

        if ($basePath === null) {
            if (app()->runningInConsole()) {
                $basePath = rtrim(getcwd(), DIRECTORY_SEPARATOR);
            } elseif (defined('BASE_PATH')) {
                $basePath = rtrim(BASE_PATH, DIRECTORY_SEPARATOR);
            } else {
                $basePath = rtrim(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''), DIRECTORY_SEPARATOR);
            }
        }

        if ($path === '') {
            return $basePath;
        }

        $normalizedPath = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        return $normalizedPath === ''
            ? $basePath
            : $basePath . DIRECTORY_SEPARATOR . $normalizedPath;
    }
}

if (!function_exists('base_url')) {
    /**
     * Get the base URL of the application dynamically.
     *
     * For multi-tenant apps, this detects the current subdomain automatically:
     * - acme.app.com → https://acme.app.com
     * - globex.app.com → https://globex.app.com
     * - app.com → https://app.com
     *
     * @param string $path
     * @return string
     */
    function base_url(string $path = ''): string
    {
        static $baseUrl = null;
        static $currentHost = null;

        $requestHost = $_SERVER['HTTP_HOST'] ?? null;

        if ($baseUrl === null || $currentHost !== $requestHost) {
            $currentHost = $requestHost;
            $baseUrl = determine_base_url();
        }

        return $baseUrl . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('determine_base_url')) {
    /**
     * Determine the base URL based on the current request context.
     *
     * @return string
     */
    function determine_base_url(): string
    {
        if (PHP_SAPI === 'cli' || defined('STDIN')) {
            $appUrl = getenv('APP_URL') ?: 'http://localhost';
            return rtrim($appUrl, '/');
        }

        $scheme = detect_scheme();
        $host = detect_host();
        $baseDir = detect_base_directory();

        return $scheme . '://' . $host . rtrim($baseDir, '/');
    }
}

if (!function_exists('detect_scheme')) {
    /**
     * Detect the current request scheme (http or https).
     *
     * Handles:
     * - Standard HTTPS detection
     * - Reverse proxies (X-Forwarded-Proto)
     * - Load balancers
     * - Cloudflare
     * - AWS ELB
     *
     * @return string 'https' or 'http'
     */
    function detect_scheme(): string
    {
        // Force HTTPS via environment variable
        if (getenv('FORCE_HTTPS') === 'true') {
            return 'https';
        }

        // Standard HTTPS detection
        if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return 'https';
        }

        // HTTPS via port
        if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
            return 'https';
        }

        // Behind reverse proxy or load balancer
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https' ? 'https' : 'http';
        }

        // Cloudflare
        if (isset($_SERVER['HTTP_CF_VISITOR'])) {
            $cfVisitor = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
            if (isset($cfVisitor['scheme']) && $cfVisitor['scheme'] === 'https') {
                return 'https';
            }
        }

        // AWS ELB
        if (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == 443) {
            return 'https';
        }

        return 'http';
    }
}

if (!function_exists('detect_host')) {
    /**
     * Detect the current request host (domain + port if non-standard).
     *
     * This is THE KEY for multi-tenant routing:
     * - Reads HTTP_HOST directly from the request
     * - Includes port for local development (localhost:8000)
     * - Validates against trusted hosts if configured
     *
     * Examples:
     * - acme.app.com → 'acme.app.com'
     * - globex.app.com → 'globex.app.com'
     * - localhost:8000 → 'localhost:8000'
     *
     * @return string The detected host
     */
    function detect_host(): string
    {
        // Priority 1: HTTP_HOST (includes port for non-standard ports)
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];

            // Strip port if it's standard (80 for HTTP, 443 for HTTPS)
            $scheme = detect_scheme();
            $standardPort = $scheme === 'https' ? 443 : 80;
            $currentPort = $_SERVER['SERVER_PORT'] ?? $standardPort;

            // If port is explicitly in HTTP_HOST and it's standard, strip it
            if (($scheme === 'https' && str_ends_with($host, ':443')) ||
                ($scheme === 'http' && str_ends_with($host, ':80'))
            ) {
                $host = preg_replace('/:(443|80)$/', '', $host);
            }

            return $host;
        }

        // Priority 2: SERVER_NAME (fallback without port)
        if (isset($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        }

        // Priority 3: SERVER_ADDR (IP address fallback)
        if (isset($_SERVER['SERVER_ADDR'])) {
            return $_SERVER['SERVER_ADDR'];
        }

        // Ultimate fallback
        return 'localhost';
    }
}

if (!function_exists('detect_base_directory')) {
    /**
     * Detect if the application is installed in a subdirectory.
     *
     * Examples:
     * - http://example.com/myapp/public/index.php → '/myapp'
     * - http://example.com/public/index.php → ''
     * 
     * @return string
     */
    function detect_base_directory(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        if (empty($scriptName)) {
            return '';
        }

        // Remove /public/index.php or /index.php from script name
        $baseDir = str_replace(['\\', '/public/index.php', '/index.php'], ['/', '', ''], $scriptName);

        return $baseDir;
    }
}

if (!function_exists('current_url')) {
    /**
     * Get the current full URL including query string.
     *
     * Example: https://acme.app.com/dashboard?page=2
     *
     * @return string The current complete URL
     */
    function current_url(): string
    {
        $scheme = detect_scheme();
        $host = detect_host();
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return $scheme . '://' . $host . $uri;
    }
}

if (!function_exists('current_domain')) {
    /**
     * Get the current domain without scheme or path.
     *
     * Examples:
     * - https://acme.app.com/dashboard → 'acme.app.com'
     * - http://localhost:8000/test → 'localhost:8000'
     *
     * @return string
     */
    function current_domain(): string
    {
        return detect_host();
    }
}

if (!function_exists('is_subdomain')) {
    /**
     * Check if the current request is on a subdomain.
     *
     * @param string
     * @return bool
     */
    function is_subdomain(string $baseDomain): bool
    {
        $currentHost = detect_host();

        // Strip port if present
        $currentHost = preg_replace('/:\d+$/', '', $currentHost);
        $baseDomain = preg_replace('/:\d+$/', '', $baseDomain);

        // If current host is longer and ends with base domain, it's a subdomain
        if ($currentHost === $baseDomain) {
            return false;
        }

        return str_ends_with($currentHost, '.' . $baseDomain);
    }
}

if (!function_exists('extract_subdomain')) {
    /**
     * Extract the subdomain from the current host.
     *
     * Examples:
     * - acme.app.com (base: app.com) → 'acme'
     * - api.staging.app.com (base: app.com) → 'api.staging'
     * - app.com (base: app.com) → null
     *
     * @param string $baseDomain
     * @return string|null
     */
    function extract_subdomain(string $baseDomain): ?string
    {
        $currentHost = detect_host();

        // Strip port if present
        $currentHost = preg_replace('/:\d+$/', '', $currentHost);
        $baseDomain = preg_replace('/:\d+$/', '', $baseDomain);

        if (!str_ends_with($currentHost, '.' . $baseDomain)) {
            return null;
        }

        // Extract subdomain by removing base domain
        $subdomain = str_replace('.' . $baseDomain, '', $currentHost);

        return $subdomain ?: null;
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the storage path of the application.
     *
     * @param string $path
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        return app()->storagePath($path);
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the public path of the application.
     *
     * @param string $path
     * @return string
     */
    function public_path(string $path = ''): string
    {
        return app()->publicPath($path);
    }
}

if (!function_exists('resource_path')) {
    /**
     * Get the resources path of the application.
     *
     * @param string $path
     * @return string
     */
    function resource_path(string $path = ''): string
    {
        return app()->resourcesPath($path);
    }
}

if (!function_exists('client_path')) {
    /**
     * Get the client assets path of the application.
     *
     * @param string $path
     * @return string
     */
    function client_path(string $path = ''): string
    {
        return app()->clientPath($path);
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the config path of the application.
     *
     * @param string $path
     * @return string
     */
    function config_path(string $path = ''): string
    {
        return app()->configPath($path);
    }
}

if (!function_exists('database_path')) {
    /**
     * Get the database path of the application.
     *
     * @param string $path
     * @return string
     */
    function database_path(string $path = ''): string
    {
        return app()->databasePath($path);
    }
}

if (!function_exists('lang_path')) {
    /**
     * Get the language path of the application.
     *
     * @param string $path
     * @return string
     */
    function lang_path(string $path = ''): string
    {
        return app()->langPath($path);
    }
}

if (!function_exists('enqueue')) {
    /**
     * Generate the URL for an asset in the public directory.
     *
     * @param string $path
     * @return string
     */
    function enqueue(string $path = '', $secure = null): string
    {
        return app('url')->enqueue($path, $secure);
    }
}

if (!function_exists('vite')) {
    /**
     * Render Vite script/link tags for the provided entrypoints.
     *
     * @param string|array $entrypoints
     * @param string $buildDirectory
     * @return string
     */
    function vite(string|array $entrypoints, string $buildDirectory = 'build'): string
    {
        return app('vite')->tags($entrypoints, $buildDirectory);
    }
}

if (!function_exists('vite_asset')) {
    /**
     * Resolve a single asset URL through the Vite manifest or dev server.
     *
     * @param string $asset
     * @param string $buildDirectory
     * @return string
     */
    function vite_asset(string $asset, string $buildDirectory = 'build'): string
    {
        return app('vite')->asset($asset, $buildDirectory);
    }
}

if (!function_exists('cookie')) {
    /**
     * Creates a new cookie instance to handle cookie.
     *
     * @return CookieJar
     */
    function cookie(): CookieJar
    {
        return app('cookie');
    }
}

if (!function_exists('abort')) {
    /**
     * Abort the request with a specific HTTP status code and optional message.
     *
     * @param int
     * @param int $code
     * @param string $message
     * @param array $headers
     * @throws HttpException
     */
    function abort($code, $message = '', array $headers = []): void
    {
        app('abort')->abort($code, $message, $headers);
    }
}

if (!function_exists('abort_if')) {
    /**
     * Abort the request if a condition is true.
     *
     * @param bool $condition
     * @param int $code
     * @param string $message
     * @param array $headers
     * @throws HttpException
     */
    function abort_if($condition, $code, $message = '', array $headers = []): void
    {
        app('abort')->abortIf($condition, $code, $message, $headers);
    }
}

if (!function_exists('now')) {
    /**
     * Get the current timestamp with optional timezone
     *
     * @return \Carbon\Carbon
     */
    function now()
    {
        return Carbon::now();
    }
}

if (!function_exists('info')) {
    /**
     * Generate log info message
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    function info(mixed $message, array $context = []): void
    {
        Log::info($message, $context);
    }
}

if (!function_exists('warning')) {
    /**
     * Generate log warning message
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    function warning(mixed $message, array $context = []): void
    {
        Log::warning($message, $context);
    }
}

if (!function_exists('error')) {
    /**
     * Generate log error message
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    function error(mixed $message, array $context = []): void
    {
        Log::error($message, $context);
    }
}

if (!function_exists('alert')) {
    /**
     * Generate log alert message
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    function alert(mixed $message, array $context = []): void
    {
        Log::alert($message, $context);
    }
}

if (!function_exists('notice')) {
    /**
     * Generate log notice message
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    function notice(mixed $message, array $context = []): void
    {
        Log::notice($message, $context);
    }
}

if (!function_exists('emergency')) {
    /**
     * Generate log emergency message
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    function emergency(mixed $message, array $context = []): void
    {
        Log::emergency($message, $context);
    }
}

if (!function_exists('critical')) {
    /**
     * Generate log critical message
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    function critical(mixed $message, array $context = []): void
    {
        Log::critical($message, $context);
    }
}

if (!function_exists('debug')) {
    /**
     * Generate log debug message
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    function debug(mixed $message, array $context = []): void
    {
        Log::debug($message, $context);
    }
}

if (!function_exists('collect')) {
    /**
     * Helper function to create a Ramsey Collection instance.
     *
     * @param array $items
     * @return Collection
     */
    function collect(array $items = []): Collection
    {
        return new Collection('mixed', $items);
    }
}

if (!function_exists('delete_folder_recursively')) {
    /**
     * Delete folder recursively
     *
     * @param string $folderPath
     * @return bool
     */
    function delete_folder_recursively(string $folderPath): bool
    {
        if (!is_dir($folderPath)) {
            return false;
        }

        $files = array_diff(scandir($folderPath), ['.', '..']);
        foreach ($files as $file) {
            $path = $folderPath . DIRECTORY_SEPARATOR . $file;

            if (is_dir($path)) {
                delete_folder_recursively($path);
            } else {
                @unlink($path);
            }
        }

        return rmdir($folderPath);
    }
}

if (!function_exists('uuid')) {
    /**
     * Generate a UUID v4 string.
     *
     * @return string
     */
    function uuid(): string
    {
        return app('str')->uuid();
    }
}


if (!function_exists('class_basename')) {
    /**
     * Get the class basename
     *
     * @param mixed $class
     * @return string
     */
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('resolve')) {
    /**
     * Resolve a service from the container.
     *
     * @param string $name
     * @param array $parameters
     * @return mixed
     */
    function resolve($name, array $parameters = [])
    {
        return app()->make($name, $parameters);
    }
}

if (!function_exists('__')) {
    /**
     * Translate the given message.
     *
     * @param string|null $key
     * @param array $replace
     * @param string|null $locale
     * @return string|array|null
     */
    function __($key = null, $replace = [], $locale = null)
    {
        if (is_null($key)) {
            return $key;
        }

        return trans($key, $replace, $locale);
    }
}

if (!function_exists('encrypt')) {
    /**
     * Encrypts a given string
     *
     * @param string $string
     * @return string
     */
    function encrypt($string): string
    {
        return (string) Crypt::encrypt($string);
    }
}

if (!function_exists('decrypt')) {
    /**
     * Decrypts a given string
     *
     * @param string $string
     * @return mixed
     */
    function decrypt($string)
    {
        return Crypt::decrypt($string);
    }
}

if (!function_exists('tap')) {
    /**
     * Tap the model and return the value.
     *
     * @param mixed $value
     * @param callable|null $callback
     * @return mixed
     */
    function tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return new class($value) {
                protected $value;

                public function __construct($value)
                {
                    $this->value = $value;
                }

                public function __call($method, $parameters)
                {
                    $this->value->{$method}(...$parameters);

                    return $this;
                }

                public function get()
                {
                    return $this->value;
                }
            };
        }

        $callback($value);

        return $value;
    }
}

if (!function_exists('ddd')) {
    /**
     * Clean previous buffer and die
     *
     * @param mixed $values
     * @return void
     */
    function ddd(mixed ...$values): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        dd(...$values);
    }
}

if (!function_exists('db')) {
    /**
     * Get a database query builder instance
     *
     * @return Database
     */
    function db(): Database
    {
        return app('db');
    }
}

if (!function_exists('throttle')) {
    /**
     * Get a RateLimiter instance
     *
     * @return RateLimiter
     */
    function throttle(): RateLimiter
    {
        return app(RateLimiter::class);
    }
}
