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
     *
     * Must be implemented by child classes to set up package-specific
     * services, configuration, or resource paths.
     */
    abstract protected function configurePackage();

    /**
     * Publish the given configuration file to the host application.
     *
     * @param string $path Path to the package's config file
     * @param string $configName Name under which the file should be published
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
     * @param array|string $paths Path(s) to the migration files
     * @param string $groupSuffix Optional suffix for the publish group
     */
    public function publishesMigrations(array|string $paths, string $groupSuffix = 'migrations'): void
    {
        $this->publishes($this->convertPathsToPublishArray($paths, 'migrations'), "{$this->packageName}-{$groupSuffix}");
    }

    /**
     * Publish view files to the host application's view folder.
     *
     * @param array|string $paths Path(s) to the view files
     * @param string $groupSuffix Optional suffix for the publish group
     */
    public function publishesViews(array|string $paths, string $groupSuffix = 'views'): void
    {
        $this->publishes($this->convertPathsToPublishArray($paths, 'views'), "{$this->packageName}-{$groupSuffix}");
    }

    /**
     * Publish translation files to the host application's language folder.
     *
     * @param array|string $paths Path(s) to the translation files
     * @param string $groupSuffix Optional suffix for the publish group
     */
    public function publishesTranslations(array|string $paths, string $groupSuffix = 'translations'): void
    {
        $this->publishes($this->convertPathsToPublishArray($paths, 'lang'), "{$this->packageName}-{$groupSuffix}");
    }

    /**
     * Convert paths to a standardized "from => to" array for publishing.
     *
     * Maps package file paths to destination paths in the host application
     * based on the resource type.
     *
     * @param array|string $paths Paths to the files to publish
     * @param string $type Type of resource ('migrations', 'views', 'lang')
     * @return array<string, string> Associative array of publish paths
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
