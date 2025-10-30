<?php

namespace Phaseolies\Translation;

use Phaseolies\Support\File;

class FileLoader
{
    /**
     * The File instance used for file operations.
     *
     * @var File
     */
    protected $file;

    /**
     * The base path for translation files.
     *
     * @var string
     */
    protected $path;

    /**
     * Array of namespace hints for package translations.
     *
     * @var array
     */
    protected $hints = [];

    /**
     * Create a new file loader instance.
     *
     * @param string $path
     */
    public function __construct($path)
    {
        $this->file = new File($path, false);
        $this->path = $path;
    }

    /**
     * Load the messages for the given locale, group, and namespace.
     *
     * @param string $locale
     * @param string $group
     * @param string|null $namespace
     * @return array
     */
    public function load($locale, $group, $namespace = null)
    {
        try {
            $lines = $this->loadPath($this->path, $locale, $group);
            return $lines;
        } catch (\RuntimeException $e) {
            // If not found in main app, try package locations
            if ($namespace) {
                // Try registered package path first
                if (isset($this->hints[$namespace])) {
                    try {
                        return $this->loadPath($this->hints[$namespace], $locale, $group);
                    } catch (\RuntimeException $e) {
                        // Continue to try vendor path
                    }
                }

                // Try published vendor path
                $vendorPath = $this->path . '/vendor/' . $namespace;
                if (file_exists($vendorPath)) {
                    try {
                        return $this->loadPath($vendorPath, $locale, $group);
                    } catch (\RuntimeException $e) {
                        // Continue to throw original exception
                    }
                }
            }

            // If all fails, rethrow the original exception
            throw $e;
        }
    }

    /**
     * Load a translation file from the given path.
     *
     * @param string $path
     * @param string $locale
     * @param string $group
     * @return array
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function loadPath($path, $locale, $group)
    {
        if (empty($group)) {
            throw new \InvalidArgumentException('Translation group name cannot be empty');
        }

        $fullPath = rtrim($path, '/') . '/' . $locale . '/' . $group . '.php';

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Translation file [{$fullPath}] not found");
        }

        return require $fullPath;
    }

    /**
     * Load any namespace override files and merge them with the base translations.
     *
     * @param array $lines
     * @param string $locale
     * @param string $group
     * @param string $namespace
     * @return array
     */
    protected function loadNamespaceOverrides(array $lines, $locale, $group, $namespace)
    {
        $file = "{$this->path}/vendor/{$namespace}/{$locale}/{$group}.php";

        if (file_exists($file)) {
            return array_replace_recursive($lines, require $file);
        }

        return $lines;
    }

    /**
     * Add a namespace hint to the loader.
     * Used to register package translation locations.
     *
     * @param string $namespace
     * @param string $hint
     * @return void
     */
    public function addNamespace($namespace, $hint)
    {
        $this->hints[$namespace] = $hint;
    }

    /**
     * Get all registered namespace hints.
     *
     * @return array
     */
    public function namespaces()
    {
        return $this->hints;
    }
}
