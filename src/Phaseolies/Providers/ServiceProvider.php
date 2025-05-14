<?php

namespace Phaseolies\Providers;

use Phaseolies\Database\Migration\Migration;
use Phaseolies\Application;

abstract class ServiceProvider
{
    /**
     * The paths that should be published.
     */
    protected array $publishes = [];

    /**
     * The paths that should be published by groups.
     */
    protected array $publishGroups = [];

    /**
     * Create a new service provider instance.
     */
    public function __construct(protected Application $app) {}

    /**
     * Register bindings into the container.
     */
    abstract public function register();

    /**
     * Bootstrap any application services.
     */
    abstract public function boot();

    /**
     * Register routes from the given path.
     *
     * @param string $path
     * @return void
     */
    public function loadRoutes(string $path): void
    {
        if (file_exists($path)) {
            require $path;
        }
    }

    /**
     * Register migrations from the given path.
     *
     * @param string $path
     * @return void
     */
    public function loadMigrations(string $path): void
    {
        $files = glob($path . '/*.php');
        foreach ($files as $file) {
            $migration = require $file;

            if ($migration instanceof \Closure) {
                $migration = $migration();
            }

            if ($migration instanceof Migration) {
                $this->app['migrator']->addMigration($file, $migration);
            }
        }
    }

    /**
     * Merge the given configuration with the existing configuration.
     *
     * @param string $path
     * @param string $key
     * @return void
     */
    public function mergeConfig(string $path, string $key): void
    {
        if (! $this->app->has('config')) {
            return;
        }

        $config = $this->app['config']->get($key, []);

        if (file_exists($path)) {
            $this->app['config']->set(
                $key,
                array_merge(require $path, $config)
            );
        }
    }

    /**
     * Register and publish package views.
     *
     * @param array $paths
     * @param string $group
     * @return void
     */
    public function publishesViews(array $paths, string $group = 'views'): void
    {
        $this->publishes($paths, $group);
    }

    /**
     * Register views from the given path with the specified namespace.
     */
    public function loadViews(string $path, string $namespace): void
    {
        if ($this->app->has('view')) {
            $this->app['view']->addNamespace($namespace, $path);
        }
    }

    /**
     * Register translation files from the given path.
     *
     * @param string $path
     * @param string $namespace
     * @return void
     */
    public function loadTranslations(string $path, string $namespace): void
    {
        if ($this->app->has('translator')) {
            $this->app['translator']->addNamespace($namespace, $path);
        }
    }

    /**
     * Register paths to be published by the publish command.
     */
    public function publishes(array $paths, mixed $groups = null): void
    {
        $this->ensurePublishArrayInitialized();

        if (is_null($groups)) {
            $this->publishes = array_merge($this->publishes, $paths);
        } else {
            foreach ((array) $groups as $group) {
                if (!isset($this->publishGroups[$group])) {
                    $this->publishGroups[$group] = [];
                }

                $this->publishGroups[$group] = array_merge(
                    $this->publishGroups[$group],
                    $paths
                );
            }
        }
    }

    /**
     * Ensure the publishes array is initialized.
     */
    protected function ensurePublishArrayInitialized(): void
    {
        if (!isset($this->publishes)) {
            $this->publishes = [];
        }

        if (!isset($this->publishGroups)) {
            $this->publishGroups = [];
        }
    }

    /**
     * Register package's migration paths to be published.
     */
    public function publishesMigrations(array|string $paths, string $groupSuffix = 'migrations'): void
    {
        $this->publishes($paths, $groupSuffix);
    }

    /**
     * Get the paths to publish.
     */
    public function pathsToPublish(string|null $provider = null, string|null $group = null): array
    {
        if (!is_null($paths = $this->pathsForProviderOrGroup($provider, $group))) {
            return $paths;
        }

        return array_merge($this->publishes, $this->publishGroups);
    }

    /**
     * Get the paths for the provider or group.
     */
    protected function pathsForProviderOrGroup(string|null $provider, string|null $group): ?array
    {
        if ($provider && $group) {
            return $this->publishGroups[$group] ?? null;
        } elseif ($group && isset($this->publishGroups[$group])) {
            return $this->publishGroups[$group];
        } elseif ($provider && isset($this->publishes[$provider])) {
            return $this->publishes[$provider];
        }

        return null;
    }

    /**
     * Register the package's custom pool commands.
     */
    public function commands(array|string $commands): void
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        if ($this->app->runningInConsole()) {
            $this->app['console']->addCommands($commands);
        }
    }
}
