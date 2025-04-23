<?php

namespace Phaseolies\Providers;

abstract class PackageServiceProvider extends ServiceProvider
{
    /**
     * The package name.
     */
    protected string $packageName;

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->configurePackage();
    }

    /**
     * Configure the package.
     */
    abstract protected function configurePackage();

    /**
     * Publish the given configuration file.
     */
    public function publishesConfig(string $path, string $configName): void
    {
        $this->publishes([
            $path => config_path("{$configName}.php"),
        ], "{$this->packageName}-config");
    }

    /**
     * Publish the given migration files.
     */
    public function publishesMigrations(array|string $paths, string $groupSuffix = 'migrations'): void
    {
        $this->publishes($this->convertPathsToPublishArray($paths, 'migrations'), "{$this->packageName}-{$groupSuffix}");
    }

    /**
     * Publish the given view files.
     */
    public function publishesViews(array|string $paths, string $groupSuffix = 'views'): void
    {
        $this->publishes($this->convertPathsToPublishArray($paths, 'views'), "{$this->packageName}-{$groupSuffix}");
    }

    /**
     * Publish the given translation files.
     */
    public function publishesTranslations(array|string $paths, string $groupSuffix = 'translations'): void
    {
        $this->publishes($this->convertPathsToPublishArray($paths, 'lang'), "{$this->packageName}-{$groupSuffix}");
    }

    /**
     * Convert paths to publish array.
     */
    protected function convertPathsToPublishArray(array|string $paths, string $type): array
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $publishPaths = [];

        foreach ($paths as $from) {
            $publishPaths[$from] = match ($type) {
                'migrations' => database_path('migrations/' . basename($from)),
                'views' => resource_path('views/vendor/' . $this->packageName),
                'lang' => lang_path('vendor/' . $this->packageName),
                default => $from,
            };
        }

        return $publishPaths;
    }
}
