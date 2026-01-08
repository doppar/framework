<?php

namespace Phaseolies\Providers;

abstract class PackageServiceProvider extends ServiceProvider
{
    /**
     * The package name.
     *
     * @var string
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
     * Publish the given configuration file to the host application.
     *
     * @param string $path
     * @param string $configName
     */
    public function publishesConfig(string $path, string $configName): void
    {
        $this->publishes([
            $path => config_path("{$configName}.php"),
        ], "{$this->packageName}-config");
    }

    /**
     * Publish migration files to the host application's database migrations folder.
     *
     * @param array|string $paths
     * @param string $groupSuffix
     */
    public function publishesMigrations(array|string $paths, string $groupSuffix = 'migrations'): void
    {
        $this->publishes($this->convertPathsToPublishArray($paths, 'migrations'), "{$this->packageName}-{$groupSuffix}");
    }

    /**
     * Publish view files to the host application's view folder.
     *
     * @param array|string $paths
     * @param string $groupSuffix
     */
    public function publishesViews(array|string $paths, string $groupSuffix = 'views'): void
    {
        $this->publishes($this->convertPathsToPublishArray($paths, 'views'), "{$this->packageName}-{$groupSuffix}");
    }

    /**
     * Publish translation files to the host application's language folder.
     *
     * @param array|string $paths
     * @param string $groupSuffix
     */
    public function publishesTranslations(array|string $paths, string $groupSuffix = 'translations'): void
    {
        $this->publishes($this->convertPathsToPublishArray($paths, 'lang'), "{$this->packageName}-{$groupSuffix}");
    }

    /**
     * Convert paths to a standardized "from => to" array for publishing.
     *
     * @param array|string $paths
     * @param string $type
     * @return array<string, string>
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
