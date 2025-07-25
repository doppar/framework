<?php

namespace Phaseolies;

use Phaseolies\Support\Router;
use Phaseolies\Providers\ServiceProvider;
use Phaseolies\Providers\CoreProviders;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Error\ErrorHandler;
use Phaseolies\Database\Migration\Migrator;
use Phaseolies\Database\Migration\MigrationRepository;
use Phaseolies\DI\Container;
use Phaseolies\Config\Config;
use Phaseolies\ApplicationBuilder;

class Application extends Container
{
    use CoreProviders;

    /**
     * The current version of the Doppar framework.
     */
    const VERSION = '2.8.7.7';

    /**
     * The base path of the application installation.
     */
    protected $basePath;

    /**
     * Indicates if the application has been bootstrapped.
     */
    protected $hasBeenBootstrapped = false;

    /**
     * Indicates if the application has been booted.
     */
    protected $booted = false;

    /**
     * The path to the bootstrap directory.
     */
    protected $bootstrapPath;

    /**
     * The path to the application resources.
     */
    protected $resourcesPath;

    /**
     * The path to the application directory.
     */
    protected $appPath;

    /**
     * The path to the configuration files.
     */
    protected $configPath;

    /**
     * The path to the database directory.
     */
    protected $databasePath;

    /**
     * The path to the public directory.
     */
    protected $publicPath;

    /**
     * The path to the storage directory.
     */
    protected $storagePath;

    /**
     * The name of the environment file.
     */
    protected $environmentFile = '.env';

    /**
     * The environment name.
     *
     * @var string|null
     */
    protected $environment;

    /**
     * Indicates if the application is running in the console.
     */
    protected $isRunningInConsole = null;

    /**
     * The registered service providers.
     */
    protected $serviceProviders = [];

    /**
     * @var bool
     */
    protected $providersBooted = false;

    /**
     * @var Router
     */
    public Router $router;

    /**
     * All of the global resolving callbacks.
     *
     * @var array
     */
    protected $globalResolvingCallbacks = [];

    /**
     * All of the resolving callbacks by class type.
     *
     * @var array
     */
    protected $resolvingCallbacks = [];

    /**
     * Holds the cached configuration flag.
     *
     * @var null
     */
    protected $cachedConfig = null;

    /**
     * Stores cached paths to avoid redundant file system lookups.
     *
     * @var array<string>
     */
    protected $pathCache = [];

    /**
     * Application constructor.
     *
     * Initializes the application by:
     * - Setting the application instance in the container.
     * - Loading configuration.
     * - Registering and booting core service providers.
     * - Setting up exception handling.
     * - Defining necessary folder paths.
     * - Detecting if the application is running in the console.
     */
    public function __construct()
    {
        parent::setInstance($this);
        $this->withExceptionHandler();
        $this->withConfiguration();
        $this->bindSingletonClasses();
        $this->registerCoreProviders();
        $this->bootCoreProviders();
    }

    /**
     * Gets the language path.
     *
     * @return string
     */
    public function langPath($path = ''): string
    {
        return $this->getPath("lang/{$path}");
    }

    /**
     * Configures the application instance.
     *
     * @return \Phaseolies\ApplicationBuilder
     *   Returns an ApplicationBuilder instance for further configuration.
     */
    public function configure($app)
    {
        return (new ApplicationBuilder($app))->withMiddlewareStack();
    }

    /**
     * Set the application base path
     *
     * @return self
     */
    public function withBasePath(string $basePath): self
    {
        $this->basePath = $basePath;
        $this->setNecessaryFolderPath();

        return $this;
    }

    /**
     * Registers the exception handler for the application.
     *
     * @return self
     */
    public function withExceptionHandler(): self
    {
        ErrorHandler::handle();

        return $this;
    }

    /**
     * Registers the application configuration
     *
     * @return self
     */
    public function withConfiguration(): self
    {
        if ($this->cachedConfig === null) {
            Config::initialize();
            $this->cachedConfig = true;
        }

        $this->environment = $this->cachedConfig['app.env'] ?? env('APP_ENV');

        return $this;
    }

    /**
     * Get the current application running environments
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment ?? config('app.env');
    }

    /**
     * Registers core service providers.
     * If the application is running in the console, it skips registration.
     * @return self
     */
    protected function registerCoreProviders(): self
    {
        $providers = array_merge(
            $this->loadCoreProviders() ?? [],
            config('app.providers')
        );

        $this->registerProviders($providers);

        return $this;
    }

    /**
     * Registers a list of service providers.
     *
     * @param array $providers
     */
    protected function registerProviders(array $providers = []): void
    {
        foreach ($providers as $provider) {
            $providerInstance = new $provider($this);
            if ($providerInstance instanceof ServiceProvider) {
                $providerInstance->register();
                $this->serviceProviders[] = $providerInstance;
            }
        }
    }

    /**
     * Boots core service providers.
     *
     * If the application is running in the console, it skips booting.
     *
     * @return self
     *   Returns the current instance for method chaining.
     */
    protected function bootCoreProviders(): self
    {
        $this->bootProviders();
        if (!$this->providersBooted) {
            $this->providersBooted = true;
        }

        return $this;
    }

    /**
     * Boots a list of service providers.
     */
    protected function bootProviders(): void
    {
        foreach ($this->serviceProviders as $providerInstance) {
            $providerInstance->boot();
        }
        $this->bootstrap();
        $this->bootServices();
    }

    /**
     * Register a callback to run after a service is resolved.
     *
     * @param string $abstract
     * @param \Closure $callback
     * @return void
     */
    public function afterResolving($abstract, \Closure $callback): void
    {
        $this->resolving($abstract, function ($object, $app) use ($callback) {
            $callback($object, $app);
        });
    }

    /**
     * Register a callback to run when a type is being resolved.
     *
     * @param string $abstract
     * @param \Closure|null $callback
     * @return void
     */
    public function resolving($abstract, ?\Closure $callback = null): void
    {
        if (is_string($abstract)) {
            $abstract = $this->normalize($abstract);
        }

        if ($abstract instanceof \Closure) {
            $this->globalResolvingCallbacks[] = $abstract;
        } else {
            $this->resolvingCallbacks[$abstract][] = $callback;
        }
    }

    /**
     * Fire the resolving callbacks
     *
     * @param mixed $abstract
     * @param mixed $object
     * @return void
     */
    protected function fireResolvingCallbacks($abstract, $object): void
    {
        foreach ($this->globalResolvingCallbacks as $callback) {
            $callback($object, $this);
        }

        if (isset($this->resolvingCallbacks[$abstract])) {
            foreach ($this->resolvingCallbacks[$abstract] as $callback) {
                $callback($object, $this);
            }
        }
    }

    /**
     * Gets the base path of the application.
     *
     * @return string
     *   The base path of the application.
     */
    public function getBasePath(): string
    {
        return $this->basePath = base_path();
    }

    /**
     * Sets necessary folder paths for the application.
     */
    protected function setNecessaryFolderPath(): void
    {
        $this->basePath = $this->basePath();
        $this->configPath = $this->configPath();
        $this->appPath = $this->appPath();
        $this->bootstrapPath = $this->bootstrapPath();
        $this->databasePath = $this->databasePath();
        $this->publicPath = $this->publicPath();
        $this->storagePath = $this->storagePath();
        $this->resourcesPath = $this->resourcesPath();
    }

    /**
     * Returns the path for a given folder name.
     *
     * @param string $folder
     * @return string
     */
    protected function getPath(string $folder): string
    {
        if (!isset($this->pathCache[$folder])) {
            $this->pathCache[$folder] = base_path($folder);
        }

        return $this->pathCache[$folder];
    }

    /**
     * Gets the resources path.
     *
     * @return string
     *   The path to the resources folder.
     */
    public function resourcesPath($path = ''): string
    {
        return $this->resourcesPath = $this->getPath("resources/{$path}");
    }

    /**
     * Gets the bootstrap path.
     *
     * @return string
     *   The path to the bootstrap folder.
     */
    public function bootstrapPath($path = ''): string
    {
        return $this->bootstrapPath = $this->getPath("bootstrap/{$path}");
    }

    /**
     * Gets the database path.
     *
     * @return string
     *   The path to the database folder.
     */
    public function databasePath($path = ''): string
    {
        return $this->databasePath = $this->getPath("database/{$path}");
    }

    /**
     * Gets the public path.
     *
     * @return string
     *   The path to the public folder.
     */
    public function publicPath($path = ''): string
    {
        return $this->publicPath = $this->getPath("public/{$path}");
    }

    /**
     * Gets the storage path.
     *
     * @return string
     *   The path to the storage folder.
     */
    public function storagePath($path = ''): string
    {
        return $this->storagePath = $this->getPath("storage/{$path}");
    }

    /**
     * Gets the application path.
     *
     * @return string
     *   The path to the application folder.
     */
    public function appPath(): string
    {
        return $this->appPath = $this->basePath();
    }

    /**
     * Gets the base path of the application.
     *
     * @return string
     *   The base path of the application.
     */
    public function basePath(): string
    {
        return $this->basePath = $this->getBasePath();
    }

    /**
     * Gets the configuration path.
     *
     * @return string
     *   The path to the configuration folder.
     */
    public function configPath($path = ''): string
    {
        return $this->configPath = $this->getPath("config/{$path}");
    }

    /**
     * Determines if the application is running in the console.
     *
     * @return bool
     *   True if running in the console, false otherwise.
     */
    public function runningInConsole(): bool
    {
        if ($this->isRunningInConsole === null) {
            $this->isRunningInConsole = \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';
        }

        return $this->isRunningInConsole;
    }

    /**
     * Checks if the application has been bootstrapped.
     *
     * @return bool
     *   True if bootstrapped, false otherwise.
     */
    public function hasBeenBootstrapped(): bool
    {
        return $this->hasBeenBootstrapped;
    }

    /**
     * Checks if the application has booted.
     *
     * @return bool
     *   True if booted, false otherwise.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Bootstraps the application.
     */
    protected function bootstrap(): void
    {
        if (!$this->hasBeenBootstrapped) {
            $this->hasBeenBootstrapped = true;
            foreach (config('app.aliases') as $alias => $facade) {
                if (!class_exists($alias)) {
                    class_alias($facade, $alias);
                }
            }
        }
    }

    /**
     * Boots the application services.
     */
    protected function bootServices(): void
    {
        if (!$this->booted) {
            $this->booted = true;
        }
    }

    /**
     * Resolves a service from the container.
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     */
    public function make($abstract, array $parameters = [])
    {
        $object = parent::make($abstract, $parameters);

        $this->fireResolvingCallbacks($abstract, $object);

        return $object;
    }

    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this['config']->get('app.locale');
    }

    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function currentLocale(): string
    {
        return $this->getLocale();
    }

    /**
     * Get the current application fallback locale.
     *
     * @return string
     */
    public function getFallbackLocale(): string
    {
        return $this['config']->get('app.fallback_locale');
    }

    /**
     * Set the current application locale.
     *
     * @param string $locale
     * @return void
     */
    public function setLocale($locale): void
    {
        $this['config']->set('app.locale', $locale);

        $this['translator']->setLocale($locale);
    }

    /**
     * Set the current application fallback locale.
     *
     * @param string $fallbackLocale
     * @return void
     */
    public function setFallbackLocale($fallbackLocale)
    {
        $this['config']->set('app.fallback_locale', $fallbackLocale);

        $this['translator']->setFallback($fallbackLocale);
    }

    /**
     * Determine if the application locale is the given locale.
     *
     * @param string $locale
     * @return bool
     */
    public function isLocale($locale): bool
    {
        return $this->getLocale() == $locale;
    }

    /**
     * Bind all the application core singleton classes
     * @return void
     */
    protected function bindSingletonClasses(): void
    {
        $this->singleton('path.lang', fn() => $this->langPath());
        $this->singleton('path.config', fn() => $this->configPath());
        $this->singleton('path.public', fn() => $this->publicPath());
        $this->singleton('path.storage', fn() => $this->storagePath());
        $this->singleton('path.resources', fn() => $this->resourcesPath());
        $this->singleton('path.database', fn() => $this->databasePath());
        $this->singleton('request', Request::class);

        $this->singleton('route', Router::class);
        $this->router = app('route');

        $this->singleton(
            'console',
            fn($app) => new \Phaseolies\Console\Console(
                app: $app,
                version: 'Doppar Framework',
                name: Application::VERSION
            )
        );

        $this->singleton('view', \Phaseolies\Support\View\Factory::class);
        $this->singleton('migrator', function () {
            return new Migrator(
                new MigrationRepository(),
                \database_path('migrations')
            );
        });
    }

    /**
     * Get all registered service providers
     *
     * @return array
     */
    public function getProviders(): array
    {
        return $this->serviceProviders;
    }

    /**
     * Get a specific provider by class name
     *
     * @param string $provider
     * @return ServiceProvider|null
     */
    public function getProvider(string $provider): ?ServiceProvider
    {
        foreach ($this->serviceProviders as $serviceProvider) {
            if (get_class($serviceProvider) === $provider) {
                return $serviceProvider;
            }
        }
        return null;
    }

    /**
     * Determine if the application is in the given environment.
     *
     * @param  string|array  $environments
     * @return bool
     */
    public function environment(...$environments): bool
    {
        if (count($environments) === 1 && is_array($environments[0])) {
            $environments = $environments[0];
        }

        $current = $this['config']->get('app.env', 'production');

        foreach ($environments as $environment) {
            if ($environment === $current || strtolower($environment) === strtolower($current)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get or check the current application environment.
     *
     * @param  string|null  $environment
     * @return string|bool
     */
    public function environmentIs($environment = null)
    {
        if (is_null($environment)) {
            return $this['config']->get('app.env', 'production');
        }

        return $this->environment($environment);
    }

    /**
     * Determine if the application is running in production.
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->environment('production');
    }

    /**
     * Determine if the application is running in development.
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this->environment('development', 'local');
    }

    /**
     * Determines if the application is running UNIT TEST
     *
     * @return bool
     */
    public function isRunningUnitTests(): bool
    {
        return strpos($_SERVER['argv'][0] ?? '', 'phpunit') !== false;
    }

    /**
     * Get the environment file the application is using.
     *
     * @return string
     */
    public function environmentFile(): string
    {
        return $this->environmentFile;
    }

    /**
     * Set the environment file to be loaded during bootstrapping.
     *
     * @param  string  $file
     * @return $this
     */
    public function loadEnvironmentFrom($file)
    {
        $this->environmentFile = $file;

        return $this;
    }

    /**
     * Dispatches the application request.
     *
     * Resolves the request using the router and sends the response.
     * Handles any HTTP exceptions that may occur during the process.
     */
    public function dispatch($request): void
    {
        try {
            $response = $this->router->resolve($this, $request);
            if (!$response instanceof Response) {
                $response = $response->setBody((string) $response);
            }

            $response->prepare($request)->send();
        } catch (HttpException $e) {
            app('response')->setException($e->getMessage());
            Response::dispatchHttpException($e);
        }
    }
}
