<?php

namespace Phaseolies;

use Phaseolies\Auth\ActorManager;
use Phaseolies\Support\Router;
use Phaseolies\Providers\GhostableProvider;
use Phaseolies\Providers\ServiceProvider;
use Phaseolies\Http\DispatchResult;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Error\ErrorHandler;
use Phaseolies\DI\Container;
use Phaseolies\Config\Config;
use Phaseolies\ApplicationBuilder;
use Dotenv\Dotenv;

class Application extends Container
{
    /**
     * The current version of the Doppar framework.
     */
    const VERSION = '3.26.6';

    /**
     * The base path of the application installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Indicates if the application has been bootstrapped.
     *
     * @var bool
     */
    protected $hasBeenBootstrapped = false;

    /**
     * Indicates if the application has been booted.
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * The path to the bootstrap directory.
     *
     * @var string
     */
    protected $bootstrapPath;

    /**
     * The path to the application resources.
     *
     * @var string
     */
    protected $resourcesPath;

    /**
     * The path to the client-side assets directory.
     *
     * @var string
     */
    protected $clientPath;

    /**
     * The path to the application directory.
     *
     * @var string
     */
    protected $appPath;

    /**
     * The path to the configuration files.
     *
     * @var string
     */
    protected $configPath;

    /**
     * The path to the database directory.
     *
     * @var string
     */
    protected $databasePath;

    /**
     * The path to the public directory.
     *
     * @var string
     */
    protected $publicPath;

    /**
     * The path to the storage directory.
     *
     * @var string
     */
    protected $storagePath;

    /**
     * The name of the environment file.
     *
     * @var string
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
     *
     * @var bool|null
     */
    protected $isRunningInConsole = null;

    /**
     * The registered service providers.
     *
     * @var array
     */
    protected $serviceProviders = [];

    /**
     * The queued ghost providers keyed by class name.
     *
     * @var array<class-string<ServiceProvider>, ServiceProvider>
     */
    protected array $ghostProviders = [];

    /**
     * Map of service identifiers to ghost provider classes.
     *
     * @var array<string, class-string<ServiceProvider>>
     */
    protected array $ghostServices = [];

    /**
     * Tracks ghost providers that have already been loaded.
     *
     * @var array<class-string<ServiceProvider>, true>
     */
    protected array $loadedGhostProviders = [];

    /**
     * Tracks ghost providers currently being loaded.
     *
     * @var array<class-string<ServiceProvider>, true>
     */
    protected array $loadingGhostProviders = [];

    /**
     * Indicates if the providers has been booted
     *
     * @var bool
     */
    protected $providersBooted = false;

    /**
     * Indicates if the application is currently booting eager providers.
     *
     * @var bool
     */
    protected bool $bootingProviders = false;

    /**
     * Tracks providers whose boot method has already run.
     *
     * @var array<class-string<ServiceProvider>, true>
     */
    protected array $bootedProviderClasses = [];

    /**
     * @var Router
     */
    public Router $router;

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
     * The paths listed below will bypass CSRF token verification.
     *
     * @var array<string>
     */
    protected $relaxablePaths = [];

    /**
     * Callbacks that should run after the request lifecycle has finished.
     *
     * @var array<int, array{callback: callable, parameters: array<int, \ReflectionParameter>}>
     */
    protected array $terminatingCallbacks = [];

    /**
     * Application constructor.
     *
     * Initializes the application by:
     * - Setting the application instance in the container.
     * - Loading environment variables from .env before anything else.
     * - Setting up exception handling.
     * - Loading configuration.
     * - Defining necessary folder paths.
     * - Registering and booting core service providers.
     */
    public function __construct()
    {
        parent::setInstance($this);
        $this->loadEnvironmentVariables();
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
        return $this->getPath($this->buildPathFragment('templates/lang', $path));
    }

    /**
     * Configures the application
     *
     * @param Application $app
     * @return \Phaseolies\ApplicationBuilder
     */
    public function configure(Application $app): ApplicationBuilder
    {
        return (new ApplicationBuilder($app))
            ->withTimezone()
            ->withMiddlewareStack();
    }

    /**
     * Set the application base path
     *
     * @param string $basePath
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
     * Load environment variables from .env file
     *
     * @return void
     */
    protected function loadEnvironmentVariables(): void
    {
        if (isset($_ENV['APP_ENV'])) {
            return;
        }

        $dotenv = Dotenv::createImmutable(base_path());
        $dotenv->safeLoad();
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

        $this->environment = config('app.env') ?? env('APP_ENV');

        return $this;
    }

    /**
     * Get the current application running environments
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment ?? config('app.env');
    }

    /**
     * Registers core service providers.
     *
     * @return self
     */
    protected function registerCoreProviders(): self
    {
        $providers = [...($this->loadCoreProviders()), ...(config('app.providers') ?? [])];

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
                if ($this->shouldQueueGhostProvider($providerInstance)) {
                    $this->queueGhostProvider($providerInstance);
                    continue;
                }

                $this->registerProviderInstance($providerInstance);
            }
        }
    }

    /**
     * Determine if the provider should be queued as a ghost provider
     *
     * @param ServiceProvider $providerInstance
     * @return bool
     */
    protected function shouldQueueGhostProvider(ServiceProvider $providerInstance): bool
    {
        return !$this->runningInConsole() && $providerInstance instanceof GhostableProvider;
    }

    /**
     * Register and track an eager provider instance.
     *
     * @param ServiceProvider $providerInstance
     * @return void
     */
    protected function registerProviderInstance(ServiceProvider $providerInstance): void
    {
        $providerInstance->register();

        $this->serviceProviders[] = $providerInstance;
    }

    /**
     * Queue a ghost provider until one of its services is requested.
     *
     * @param ServiceProvider $providerInstance
     * @return void
     */
    protected function queueGhostProvider(ServiceProvider $providerInstance): void
    {
        /** @var GhostableProvider $providerInstance */
        $providerClass = $providerInstance::class;

        $this->ghostProviders[$providerClass] = $providerInstance;

        foreach ($providerInstance->ghosts() as $ghost) {
            if (!is_string($ghost) || $ghost === '') {
                continue;
            }

            $this->ghostServices[$ghost] = $providerClass;
        }
    }

    /**
     * Boots core service providers.
     *
     * @return self
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
     *
     * @return void
     */
    protected function bootProviders(): void
    {
        $this->bootingProviders = true;

        try {
            foreach ($this->serviceProviders as $providerInstance) {
                $this->bootProviderInstance($providerInstance);
            }
        } finally {
            $this->bootingProviders = false;
        }

        $this->bootstrap();

        $this->bootServices();
    }

    /**
     * Sets necessary folder paths for the application.
     *
     * @return void
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
        $this->clientPath = $this->clientPath();
    }

    /**
     * Returns the path for a given folder name.
     *
     * @param string $folder
     * @return string
     */
    protected function getPath(string $folder): string
    {
        $normalizedFolder = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $folder), DIRECTORY_SEPARATOR);

        if (!isset($this->pathCache[$normalizedFolder])) {
            $this->pathCache[$normalizedFolder] = base_path($normalizedFolder);
        }

        return $this->pathCache[$normalizedFolder];
    }

    /**
     * Gets the resources path.
     *
     * @param string $path
     * @return string
     */
    public function resourcesPath($path = ''): string
    {
        return $this->resourcesPath = $this->getPath($this->buildPathFragment('templates', $path));
    }

    /**
     * Gets the client assets path.
     *
     * @param string $path
     * @return string
     */
    public function clientPath($path = ''): string
    {
        return $this->clientPath = $this->getPath($this->buildPathFragment('templates/client', $path));
    }

    /**
     * Gets the bootstrap path.
     *
     * @param string $path
     * @return string
     */
    public function bootstrapPath($path = ''): string
    {
        return $this->bootstrapPath = $this->getPath($this->buildPathFragment('runtime', $path));
    }

    /**
     * Gets the database path.
     *
     * @param string $path
     * @return string
     */
    public function databasePath($path = ''): string
    {
        return $this->databasePath = $this->getPath($this->buildPathFragment('schema', $path));
    }

    /**
     * Gets the public path.
     *
     * @param string $path
     * @return string
     */
    public function publicPath($path = ''): string
    {
        return $this->publicPath = $this->getPath($this->buildPathFragment('public', $path));
    }

    /**
     * Gets the storage path.
     *
     * @param string $path
     * @return string
     */
    public function storagePath($path = ''): string
    {
        return $this->storagePath = $this->getPath($this->buildPathFragment('storage', $path));
    }

    /**
     * Build a relative path fragment from a prefix and optional child path.
     *
     * @param string $prefix
     * @param string $path
     * @return string
     */
    protected function buildPathFragment(string $prefix, string $path = ''): string
    {
        $normalizedPrefix = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $prefix), DIRECTORY_SEPARATOR);
        $normalizedPath = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        return $normalizedPath === ''
            ? $normalizedPrefix
            : $normalizedPrefix . DIRECTORY_SEPARATOR . $normalizedPath;
    }

    /**
     * Gets the application path.
     *
     * @return string
     */
    public function appPath(): string
    {
        return $this->appPath = $this->getPath('src');
    }

    /**
     * Gets the base path of the application.
     *
     * @return string
     */
    public function basePath(): string
    {
        return $this->basePath = base_path();
    }

    /**
     * Gets the configuration path.
     *
     * @return string
     */
    public function configPath($path = ''): string
    {
        return $this->configPath = $this->getPath("runtime/config/{$path}");
    }

    /**
     * Determines if the application is running in the console.
     *
     * @return bool
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
     */
    public function hasBeenBootstrapped(): bool
    {
        return $this->hasBeenBootstrapped;
    }

    /**
     * Checks if the application has booted.
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Setting up necessary class aliases.
     *
     * @return void
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
     *
     * @return void
     */
    protected function bootServices(): void
    {
        if (!$this->booted) {
            $this->booted = true;
        }
    }

    /**
     * Resolve a class with its dependencies
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @param array $parameters
     * @return T|string
     */
    public function make($abstract, array $parameters = []): object|string
    {
        $object = parent::make($abstract, $parameters);

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
    public function setFallbackLocale($fallbackLocale): void
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
     * Bind application necessary paths
     *
     * @return void
     */
    public function bindApplicationNecessaryPath(): void
    {
        $this->singleton('path.lang', fn() => $this->langPath());
        $this->singleton('path.config', fn() => $this->configPath());
        $this->singleton('path.public', fn() => $this->publicPath());
        $this->singleton('path.storage', fn() => $this->storagePath());
        $this->singleton('path.resources', fn() => $this->resourcesPath());
        $this->singleton('path.client', fn() => $this->clientPath());
        $this->singleton('path.database', fn() => $this->databasePath());
    }

    /**
     * Bind all the application core singleton classes
     *
     * @return void
     */
    protected function bindSingletonClasses(): void
    {
        $this->bindApplicationNecessaryPath();
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
        $this->singleton(
            'migrator',
            fn() =>
            new \Phaseolies\Database\Migration\Migrator(
                new \Phaseolies\Database\Migration\MigrationRepository(),
                database_path('migrations')
            )
        );
    }

    /**
     * Loads the core service providers for the application.
     *
     * @return array
     */
    protected function loadCoreProviders(): array
    {
        return [
            \Phaseolies\Providers\FacadeServiceProvider::class,
            \Phaseolies\Providers\LanguageServiceProvider::class,
            \Phaseolies\Providers\SessionServiceProvider::class,
            \Phaseolies\Providers\RouteServiceProvider::class,
            \Phaseolies\Providers\CacheServiceProvider::class,
            \Phaseolies\Providers\RateLimiterServiceProvider::class,
        ];
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
     * Determine if the application has a binding or queued ghost for the given service.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return parent::has($key) || isset($this->ghostServices[$key]);
    }

    /**
     * Load a queued ghost provider when one of its services is requested.
     *
     * @param string $abstract
     * @return bool
     */
    public function loadGhostProvider(string $abstract): bool
    {
        $providerClass = $this->ghostServices[$abstract] ?? null;

        if ($providerClass === null) {
            return false;
        }

        if (isset($this->loadedGhostProviders[$providerClass]) || isset($this->loadingGhostProviders[$providerClass])) {
            return true;
        }

        $providerInstance = $this->ghostProviders[$providerClass] ?? null;

        if (!$providerInstance instanceof ServiceProvider) {
            return false;
        }

        $this->loadingGhostProviders[$providerClass] = true;

        foreach (($providerInstance instanceof GhostableProvider ? $providerInstance->ghosts() : []) as $ghost) {
            unset($this->ghostServices[$ghost]);
        }

        try {
            $this->registerProviderInstance($providerInstance);

            if ($this->providersBooted || $this->bootingProviders) {
                $this->bootProviderInstance($providerInstance);
            }

            $this->loadedGhostProviders[$providerClass] = true;

            return true;
        } finally {
            unset($this->loadingGhostProviders[$providerClass], $this->ghostProviders[$providerClass]);
        }
    }

    /**
     * Boot a provider instance once.
     *
     * @param ServiceProvider $providerInstance
     * @return void
     */
    protected function bootProviderInstance(ServiceProvider $providerInstance): void
    {
        $providerClass = $providerInstance::class;

        if (isset($this->bootedProviderClasses[$providerClass])) {
            return;
        }

        $providerInstance->boot();

        $this->bootedProviderClasses[$providerClass] = true;
    }

    /**
     * Determine if the application is in the given environment.
     *
     * @param string|array $environments
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
     * @param string|null $environment
     * @return string|bool
     */
    public function environmentIs($environment = null): string|bool
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
     * @param string $file
     * @return $this
     */
    public function loadEnvironmentFrom($file): self
    {
        $this->environmentFile = $file;

        return $this;
    }

    /**
     * The paths listed below will bypass CSRF token verification.
     *
     * @param array $relaxablePaths
     * @return self
     */
    public function setRelaxablePaths(array $relaxablePaths = []): self
    {
        $this->relaxablePaths = $relaxablePaths;

        return $this;
    }

    /**
     * Get the paths that will bypass CSRF token verification.
     *
     * @return array
     */
    public function getRelaxablePaths(): array
    {
        return $this->relaxablePaths;
    }

    /**
     * Register a callback to run after the response has been sent.
     *
     * Supported callback parameter names are: $request, $response, $exception.
     *
     * @param callable $callback
     * @return self
     */
    public function terminating(callable $callback): self
    {
        $this->terminatingCallbacks[] = [
            'callback' => $callback,
            'parameters' => $this->reflectCallable($callback)->getParameters(),
        ];

        return $this;
    }

    /**
     * Run all registered terminating callbacks for the completed request.
     *
     * @param Request $request
     * @param Response|null $response
     * @param \Throwable|null $exception
     * @return void
     */
    public function terminate(Request $request, ?Response $response = null, ?\Throwable $exception = null): void
    {
        try {
            foreach ($this->terminatingCallbacks as $callback) {
                $this->callTerminatingCallback($callback, $request, $response, $exception);
            }
        } finally {
            $this->cleanupRequestScopedServices();
        }
    }

    /**
     * Drop resolved request-scoped services while preserving their bindings.
     *
     * @return void
     */
    protected function cleanupRequestScopedServices(): void
    {
        if ($this->has('auth')) {
            $auth = $this->make('auth');

            if ($auth instanceof ActorManager) {
                $auth->forgetActors();
            }
        }

        foreach (['session', 'request', 'response', 'redirect'] as $abstract) {
            $this->forgetResolved($abstract);
        }
    }

    /**
     * Invoke a terminating callback with the current lifecycle context.
     *
     * @param array{callback: callable, parameters: array<int, \ReflectionParameter>} $callback
     * @param Request $request
     * @param Response|null $response
     * @param \Throwable|null $exception
     * @return void
     */
    protected function callTerminatingCallback(array $callback, Request $request, ?Response $response = null, ?\Throwable $exception = null): void
    {
        $namedContext = [
            'request' => $request,
            'response' => $response,
            'exception' => $exception,
        ];

        $arguments = [];

        foreach ($callback['parameters'] as $parameter) {
            $resolved = false;
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                if ($request instanceof $typeName) {
                    $arguments[] = $request;
                    $resolved = true;
                } elseif ($response instanceof $typeName) {
                    $arguments[] = $response;
                    $resolved = true;
                } elseif ($exception instanceof $typeName) {
                    $arguments[] = $exception;
                    $resolved = true;
                } elseif ($this->typeAllowsNull($type)) {
                    $arguments[] = null;
                    $resolved = true;
                }
            } elseif ($type instanceof \ReflectionUnionType && $this->typeAllowsNull($type)) {
                $arguments[] = null;
                $resolved = true;
            }

            if (!$resolved && array_key_exists($parameter->getName(), $namedContext)) {
                $arguments[] = $namedContext[$parameter->getName()];
                $resolved = true;
            }

            if (!$resolved && $parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                $resolved = true;
            }

            if (!$resolved) {
                throw new \RuntimeException(
                    "Unresolvable terminating callback parameter '\${$parameter->getName()}'."
                );
            }
        }

        call_user_func_array($callback['callback'], $arguments);
    }

    /**
     * Create a reflection instance for a generic PHP callable.
     *
     * @param callable $callback
     * @return \ReflectionFunctionAbstract
     */
    protected function reflectCallable(callable $callback): \ReflectionFunctionAbstract
    {
        if (is_array($callback)) {
            return new \ReflectionMethod($callback[0], $callback[1]);
        }

        if (is_object($callback) && method_exists($callback, '__invoke')) {
            return new \ReflectionMethod($callback, '__invoke');
        }

        return new \ReflectionFunction($callback);
    }

    /**
     * Determine whether a reflected parameter type explicitly allows null.
     *
     * @param \ReflectionType $type
     * @return bool
     */
    protected function typeAllowsNull(\ReflectionType $type): bool
    {
        if ($type instanceof \ReflectionNamedType) {
            return $type->allowsNull();
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $innerType) {
                if ($innerType instanceof \ReflectionNamedType && $innerType->getName() === 'null') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolve the incoming request into a response instance.
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $response = $this->router->resolve($this, $request);

        if (!$response instanceof Response) {
            $response = $response->setBody((string) $response);
        }

        return $response;
    }

    /**
     * Dispatches the application request.
     *
     * @param Request $request
     * @return DispatchResult
     */
    public function dispatch($request): DispatchResult
    {
        try {
            $this->instance('request', $request);

            $response = $this->handle($request);

            $response->prepare($request)->send();

            return new DispatchResult($this, $request, $response);
        } catch (HttpException $e) {
            Response::dispatchHttpException($e);

            return new DispatchResult($this, $request, null, $e);
        }
    }
}
