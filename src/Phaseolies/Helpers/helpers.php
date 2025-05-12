<?php

use Phaseolies\Utilities\Paginator;
use Phaseolies\Translation\Translator;
use Phaseolies\Support\UrlGenerator;
use Phaseolies\Support\StringService;
use Phaseolies\Support\Facades\Log;
use Phaseolies\Support\CookieJar;
use Phaseolies\Support\Collection;
use Phaseolies\Session\MessageBag;
use Phaseolies\Http\Response\RedirectResponse;
use Phaseolies\Http\ResponseFactory;
use Phaseolies\Http\Response;
use Phaseolies\Http\Controllers\Controller;
use Phaseolies\DI\Container;
use Phaseolies\Config\Config;
use Phaseolies\Auth\Security\Authenticate;
use Carbon\Carbon;

/**
 * Gets an environment variable from available sources, and provides emulation
 * for unsupported or inconsistent environment variables (i.e., DOCUMENT_ROOT on
 * IIS, or SCRIPT_NAME in CGI mode). Also exposes some additional custom
 * environment information.
 *
 * @param string $key Environment variable name.
 * @param string|float|int|bool|null $default Specify a default value in case the environment variable is not defined.
 * @return string|float|int|bool|null Environment variable setting.
 */
function env(string $key, string|float|int|bool|null $default = null): string|float|int|bool|null
{
    return dopparEnv($key, $default);
}

/**
 * Retrieves an environment variable or returns a default value if the variable is not set.
 *
 * @param string $key Environment variable name.
 * @param string|float|int|bool|null $default Default value to return if the variable is not set.
 * @return string|float|int|bool|null Environment variable value or default.
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
 * @param array  $parameters
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
 * @return mixed A new instance of the Request class.
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
 * @param  string|null  $path
 * @param  mixed  $parameters
 * @param  bool|null  $secure
 * @return ($path is null ? \Phaseolies\Support\UrlGenerator : string)
 */
function url($path = null, $parameters = [], $secure = null)
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
 * @param string $view The name of the view file to render.
 * @param array $data An associative array of data to pass to the view (default is an empty array).
 * @return Response
 */
function view($view, array $data = [], array $headers = []): Response
{
    $instance = app(Controller::class);
    $content = $instance->render($view, $data, true);
    $response = app('response');
    $response->setBody($content);

    foreach ($headers as $name => $value) {
        request()->headers->set($name, $value);
    }

    return $response;
}

/**
 * Creates a new redirect instance for handling HTTP redirects.
 *
 * @return RedirectResponse A new instance of the RedirectResponse class.
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
 * @param  int  $status
 * @param  array  $headers
 * @param  mixed  $fallback
 * @return \Phaseolies\Http\RedirectResponse
 */
function back($status = 302, $headers = [], $fallback = false)
{
    return app('redirect')->back($status, $headers, $fallback);
}

/**
 * Creates a new Session instance
 *
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
    return request()->session()->token() ?? null;
}

/**
 * Creates a password hashing helper
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
 * @param mixed $key The key to retrieve the old input for.
 * @return string|null The old input value or null if not found.
 */
function old($key): ?string
{
    return MessageBag::old($key);
}

/**
 * Creates and returns a Faker generator instance for generating fake data.
 *
 * @return \Faker\Generator An instance of the Faker Generator.
 */
function fake(): \Faker\Generator
{
    $faker = Faker\Factory::create();
    return $faker;
}

/**
 * Generates a full URL for a named route.
 *
 * @param string $name The route name.
 * @param mixed $params The parameters for the route.
 * @return string|null The generated URL or null if the route doesn't exist.
 */
function route(string $name, mixed $params = []): ?string
{
    return app('url')->route($name, $params);
}

/**
 * Retrieve a configuration value by key.
 *
 * @param string $key The configuration key to retrieve.
 * @param string $default The default configuration key to retrieve.
 * @return string|array|null The configuration value associated with the key, or null if not found.
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
 * @return bool Returns true if the user is logged in, otherwise false.
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
 * @param string $path An optional path to append to the base path.
 * @return string The full base path.
 */
function base_path(string $path = ''): string
{
    return BASE_PATH . ($path ? DIRECTORY_SEPARATOR . $path : '');
}

/**
 * Get the base URL of the application.
 *
 * @param string $path An optional path to append to the base URL.
 * @return string The full base URL.
 */
function base_url(string $path = ''): string
{
    static $baseUrl = null;

    // If we already determined the base URL, return it with the path
    if ($baseUrl !== null) {
        return $baseUrl . ($path ? '/' . ltrim($path, '/') : '');
    }

    if (PHP_SAPI === 'cli' || defined('STDIN')) {
        $appUrl = getenv('APP_URL') ?: 'http://localhost';
        $baseUrl = rtrim($appUrl, '/');
    } else {
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;

        // If using a subdirectory, include that in base URL
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $baseDir = str_replace(basename($scriptName), '', $scriptName);
        $baseUrl .= rtrim($baseDir, '/');
    }

    return $baseUrl . ($path ? '/' . ltrim($path, '/') : '');
}

/**
 * Get the storage path of the application.
 *
 * @param string $path An optional path to append to the storage path.
 * @return string The full storage path.
 */
function storage_path(string $path = ''): string
{
    return app()->storagePath($path);
}

/**
 * Get the public path of the application.
 *
 * @param string $path An optional path to append to the public path.
 * @return string The full public path.
 */
function public_path(string $path = ''): string
{
    return app()->publicPath($path);
}

/**
 * Get the resources path of the application.
 *
 * @param string $path An optional path to append to the resources path.
 * @return string The full resources path.
 */
function resource_path(string $path = ''): string
{
    return app()->resourcesPath($path);
}

/**
 * Get the config path of the application.
 *
 * @param string $path An optional path to append to the config path.
 * @return string The full config path.
 */
function config_path(string $path = ''): string
{
    return app()->configPath($path);
}

/**
 * Get the database path of the application.
 *
 * @param string $path An optional path to append to the database path.
 * @return string The full database path.
 */
function database_path(string $path = ''): string
{
    return app()->databasePath($path);
}

/**
 * Get the language path of the application.
 *
 * @param string $path An optional path to append to the language path.
 * @return string The full language path.
 */
function lang_path(string $path = ''): string
{
    return app()->langPath($path);
}

/**
 * Generate the URL for an asset in the public directory.
 *
 * @param string $path The path to the asset relative to the public directory.
 * @return string The full URL to the asset.
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
 * @param bool
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
 * Get the current timestamps
 * @return \Carbon\Carbon
 */
function now()
{
    return Carbon::now();
}

/**
 * Log helper
 *
 * @param mixed
 * @return void
 */
function info(mixed $payload): void
{
    Log::info($payload);
}

/**
 * Log helper
 *
 * @param mixed
 * @return void
 */
function warning(mixed $payload): void
{
    Log::warning($payload);
}

/**
 * Log helper
 *
 * @param mixed
 * @return void
 */
function error(mixed $payload): void
{
    Log::error($payload);
}

/**
 * Log helper
 *
 * @param mixed
 * @return void
 */
function alert(mixed $payload): void
{
    Log::alert($payload);
}

/**
 * Log helper
 *
 * @param mixed
 * @return void
 */
function notice(mixed $payload): void
{
    Log::notice($payload);
}

/**
 * Log helper
 *
 * @param mixed
 * @return void
 */
function emergency(mixed $payload): void
{
    Log::emergency($payload);
}

/**
 * Log helper
 *
 * @param mixed
 * @return void
 */
function critical(mixed $payload): void
{
    Log::critical($payload);
}

/**
 * Log helper
 *
 * @param mixed
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
 * Delete folder
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
 * @return string The generated UUID.
 *
 * @example
 * Str::uuid(); // Returns something like "f47ac10b-58cc-4372-a567-0e02b2c3d479"
 */
function uuid(): string
{
    return app('str')->uuid();
}

if (!function_exists('class_basename')) {
    /**
     * Get the class basename
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