<?php

namespace Phaseolies\Providers;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Phaseolies\Support\Validation\Sanitizer;
use Phaseolies\Support\UrlGenerator;
use Phaseolies\Support\StringService;
use Phaseolies\Support\Storage\StorageFileService;
use Phaseolies\Support\Session;
use Phaseolies\Support\Mail\MailService;
use Phaseolies\Support\LoggerService;
use Phaseolies\Support\Encryption;
use Phaseolies\Support\CookieJar;
use Phaseolies\Http\Support\RequestAbortion;
use Phaseolies\Http\Response\RedirectResponse;
use Phaseolies\Http\Response;
use Phaseolies\Database\Migration\Schema;
use Phaseolies\Database\Database;
use Phaseolies\Config\Config;
use Phaseolies\Cache\CacheStore;
use Phaseolies\Auth\Security\PasswordHashing;
use Phaseolies\Auth\Security\Authenticate;
use Phaseolies\Application;

/**
 * FacadeServiceProvider is responsible for binding key application services
 * into the service container. These services can then be resolved and used
 * throughout the application via facades or dependency injection.
 *
 * This provider registers singleton instances of various services, ensuring
 * that the same instance is reused whenever the service is requested.
 */
class FacadeServiceProvider extends ServiceProvider
{
    /**
     * @var \Closure[] Custom adapter factories
     */
    protected array $customAdapters = [];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Bind the 'config' service to a singleton instance of the Config class.
        // This allows the application to access configuration settings globally.
        $this->app->singleton('config', Config::class);

        // Bind the 'session' service to a singleton instance of the Session class.
        // This manages user session data throughout the application.
        $this->app->singleton('session', Session::class);

        // Bind the 'response' service to a singleton instance of the Response class.
        // This is used to generate HTTP responses.
        $this->app->singleton('response', Response::class);

        // Bind the 'hash' service to a singleton instance of the PasswordHashing class.
        // This provides password hashing and verification functionality.
        $this->app->singleton('hash', PasswordHashing::class);

        // Bind the 'auth' service to a singleton instance of the Authenticate class.
        // This handles user authentication and authorization.
        $this->app->singleton('auth', Authenticate::class);

        // Bind the 'crypt' service to a singleton instance of the Encryption class.
        // This provides encryption and decryption functionality.
        $this->app->singleton('crypt', Encryption::class);

        // Bind the 'redirect' service to a singleton instance of the RedirectResponse class.
        // This is used to generate HTTP redirect responses.
        $this->app->singleton('redirect', RedirectResponse::class);

        // Bind the 'abort' service to a singleton instance of the RequestAbortion class.
        // This is used to abort requests and return error responses.
        $this->app->singleton('abort', RequestAbortion::class);

        // Bind the 'mail' service to a singleton instance of the MailService class.
        // This handles sending emails.
        $this->app->singleton('mail', MailService::class);

        // Bind the 'url' service to a singleton instance of the UrlGenerator class.
        // This generates URLs for the application, using the base URL from the environment
        // or configuration.
        $this->app->singleton('url', function () {
            return new UrlGenerator(env('APP_URL') ?? config('app.url'));
        });

        // Bind the 'storage' service to a singleton instance of the Storage class.
        // This handles file uploads.
        $this->app->singleton('storage', StorageFileService::class);

        // Bind the 'log' service to a singleton instance of the Logger class.
        // This handles user define log.
        $this->app->singleton('log', LoggerService::class);

        // Bind the 'sanitize' service to a singleton instance of the Sanitizer class.
        // This handles user requested form data.
        $this->app->singleton('sanitize', function () {
            return new Sanitizer([], []);
        });

        // Bind the 'str' service to a singleton instance of the StringService class.
        // This handles string related helper jobs
        $this->app->singleton('str', StringService::class);

        // Bind the 'cookie' service to a singleton instance of the CookieJar class.
        // This handles cookie related jobs
        $this->app->singleton('cookie', CookieJar::class);

        // Bind the 'db' service to a singleton instance of the Database class.
        // This handles db related jobs
        $this->app->singleton('db', Database::class);

        // Bind the 'app' service to a singleton instance of the Application class.
        // This handles app related jobs
        $this->app->singleton('app', Application::class);

        // Bind the 'schema' service to a singleton instance of the Schema class.
        // This handles database schema related jobs
        $this->app->singleton('schema', Schema::class);

        // Bind the 'cache' service to a singleton instance of the CacheStore class.
        // This handles cache related jobs
        $this->app->singleton('cache', function () {
            $adapter = $this->createAdapter(config('cache.default', 'file'));
            return new CacheStore($adapter, config('cache.prefix'));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }


    /**
     * Create the cache adapter
     *
     * @param string $store
     * @return mixed
     */
    public function createAdapter(string $store): mixed
    {
        $storeConfig = config("cache.stores.{$store}", '');

        return match ($storeConfig['driver'] ?? null) {
            'file' => new FilesystemAdapter(
                config('cache.prefix'),
                0,
                $storeConfig['path'] ?? storage_path('framework/cache/data')
            ),
            'array' => new ArrayAdapter(
                0,
                $storeConfig['serialize'] ?? false
            ),
            'redis' => $this->createRedisAdapter($storeConfig),
            'apc' => new ApcuAdapter(config('cache.prefix')),
            default => $this->createCustomAdapter($store, $storeConfig)
        };
    }

    /**
     * Create Redis adapter
     *
     * @param array $config
     * @return RedisAdapter
     */
    protected function createRedisAdapter(array $config): RedisAdapter
    {
        $redis = new \Redis();

        $dsn = $config['connection'] ?? 'redis://127.0.0.1:6379';
        $parsed = parse_url($dsn);

        $host = $parsed['host'] ?? '127.0.0.1';
        $port = $parsed['port'] ?? 6379;
        $password = $parsed['pass'] ?? null;
        $database = isset($parsed['path']) ? (int) substr($parsed['path'], 1) : 0;

        if (!$redis->connect($host, $port, 2.5)) {
            throw new \RuntimeException("Could not connect to Redis at {$host}:{$port}");
        }

        if ($password !== null) {
            $redis->auth($password);
        }

        if ($database > 0) {
            $redis->select($database);
        }

        foreach ($config['options'] ?? [] as $name => $value) {
            $optionConstant = $this->getRedisOptionConstant($name);
            if ($optionConstant !== null) {
                $redis->setOption($optionConstant, $value);
            }
        }

        return new RedisAdapter(
            $redis,
            config('cache.prefix', ''),
            $config['ttl'] ?? 0
        );
    }

    /**
     * Map string option names to Redis constants
     *
     * @param string $name
     * @return int|null
     */
    protected function getRedisOptionConstant(string $name): ?int
    {
        $constants = [
            'serializer' => \Redis::OPT_SERIALIZER,
            'prefix' => \Redis::OPT_PREFIX,
            'read_timeout' => \Redis::OPT_READ_TIMEOUT,
            'scan' => \Redis::OPT_SCAN,
            'compression' => \Redis::OPT_COMPRESSION,
        ];

        return $constants[strtolower($name)] ?? null;
    }

    /**
     * Create custom adapter
     *
     * @param string $store
     * @param array $config
     * @return mixed
     */
    protected function createCustomAdapter(string $store, array $config): mixed
    {
        if (isset($this->customAdapters[$store])) {
            return $this->customAdapters[$store]($config);
        }

        throw new \RuntimeException("Cache store [{$store}] is not defined.");
    }

    /**
     * Generate custom adapter
     * @param string $store
     * @param \Closure $factory
     * @return void
     */
    public function extend(string $store, \Closure $factory): void
    {
        $this->customAdapters[$store] = $factory;
    }
}
