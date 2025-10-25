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
use Phaseolies\Auth\Security\Authenticate;
use Carbon\Carbon;

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

/**
 * Creates a new Authenticate instance
 *
 * @return Authenticate
 */
function auth(): Authenticate
{
    return app('auth');
}

/**
 * Generate translation
 *
 * @return string
 */
function trans($key, array $replace = [], $locale = null): string
{
    return app('translator')->trans($key, $replace, $locale);
}

/**
 * Get the translator instance
 *
 * @return Translator
 */
function lang(): Translator
{
    return app('translator');
}

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
    $response = app('response');
    $response->setBody($content);

    foreach ($headers as $name => $value) {
        $response->headers->set($name, $value);
    }

    return $response;
}

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
    if (is_null($to)) {
        return app('redirect');
    }

    return app('redirect')->to($to, $status, $headers, $secure);
}

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
    return app('redirect')->back($status, $headers, $fallback);
}

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

/**
 * Fetch csrf token
 *
 * @return null|string
 */
function csrf_token(): ?string
{
    return session('_token') ?? null;
}

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

/**
 * Retrieve a configuration value by key.
 *
 * @param string $key
 * @param string $default
 * @return string|array|null
 */
function config(string|array $key, ?string $default = null): null|string|array
{
    if (is_array($key)) {
        foreach ($key as $k => $v) {
            Config::set($k, $v);
        }
        return null;
    }

    return Config::get($key, $default);
}

/**
 * Check if the user is authenticated.
 *
 * @return bool
 */
function is_auth(): bool
{
    return app('auth')->check();
}

/**
 * Instance of the StringService class
 *
 * @return StringService
 */
function str(): StringService
{
    return app('str');
}

/**
 * Get the paginator instance
 *
 * @return Paginator
 */
function paginator(?array $data = null): Paginator
{
    return app(Paginator::class, [$data]);
}

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

    return $basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

/**
 * Get the base URL of the application.
 *
 * @param string $path
 * @return string
 */
function base_url(string $path = ''): string
{
    static $baseUrl = null;

    // Return cached version if available 
    // and not forcing a new check
    if ($baseUrl !== null && !defined('FORCE_BASE_URL_REFRESH')) {
        return $baseUrl . ($path ? '/' . ltrim($path, '/') : '');
    }

    if (PHP_SAPI === 'cli' || defined('STDIN')) {
        $appUrl = getenv('APP_URL') ?: 'http://localhost';
        $baseUrl = rtrim($appUrl, '/');
    } else {
        // Modern HTTPS detection
        $isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) === 'https'
            || ($_SERVER['HTTP_CF_VISITOR'] ?? null) === '{"scheme":"https"}'; // Cloudflare support

        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;

        // Handle subdirectory installations
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName) {
            $baseDir = str_replace(basename($scriptName), '', $scriptName);
            $baseUrl .= rtrim($baseDir, '/');
        }
    }

    // Allow environment override
    if (getenv('FORCE_HTTPS') === 'true') {
        $baseUrl = str_replace('http://', 'https://', $baseUrl);
    }

    return $baseUrl . ($path ? '/' . ltrim($path, '/') : '');
}

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

/**
 * Creates a new cookie instance to handle cookie.
 *
 * @return CookieJar
 */
function cookie(): CookieJar
{
    return app('cookie');
}

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

/**
 * Get the current timestamp with optional timezone
 *
 * @return \Carbon\Carbon
 */
function now()
{
    return Carbon::now();
}

/**
 * Generate log info message
 *
 * @param mixed $message
 * @return void
 */
function info(mixed $payload): void
{
    Log::info($payload);
}

/**
 * Generate log warning message
 *
 * @param mixed $message
 * @return void
 */
function warning(mixed $payload): void
{
    Log::warning($payload);
}

/**
 * Generate log error message
 *
 * @param mixed $message
 * @return void
 */
function error(mixed $payload): void
{
    Log::error($payload);
}

/**
 * Generate log alert message
 *
 * @param mixed $message
 * @return void
 */
function alert(mixed $payload): void
{
    Log::alert($payload);
}

/**
 * Generate log notice message
 *
 * @param mixed $message
 * @return void
 */
function notice(mixed $payload): void
{
    Log::notice($payload);
}

/**
 * Generate log emergency message
 *
 * @param mixed $message
 * @return void
 */
function emergency(mixed $payload): void
{
    Log::emergency($payload);
}

/**
 * Generate log critical message
 *
 * @param mixed $message
 * @return void
 */
function critical(mixed $payload): void
{
    Log::critical($payload);
}

/**
 * Generate log debug message
 *
 * @param mixed $message
 * @return void
 */
function debug(mixed $payload): void
{
    Log::debug($payload);
}

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

/**
 * Generate a UUID v4 string.
 *
 * @return string
 */
function uuid(): string
{
    return app('str')->uuid();
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

/**
 * Clean previous buffer and die
 *
 * @param mixed $values
 * @return never
 */
function ddd(mixed ...$values): never
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    dd(...$values);
}

/**
 * Get a database query builder instance
 *
 * @return Database
 */
function db(): Database
{
    return app('db');
}
