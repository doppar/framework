<?php

namespace Phaseolies\Support\Blade;

use RuntimeException;

trait BladeCache
{
    protected $cacheFolder;

    protected $publicSymlinkFolder;

    /**
     * Create cache folder.
     *
     * @return void
     */
    public function createCacheFolder(): void
    {
        $actual = base_path() . '/' . $this->cacheFolder;

        if (!is_dir($actual)) {
            if (!mkdir($actual, 0755, true) && !is_dir($actual)) {
                throw new RuntimeException('Unable to create view cache folder: ' . $actual);
            }
        }
    }

    /**
     * Create storage symlink folder that will be connected to public directory.
     *
     * @return void
     */
    public function createPublicSymlinkFolder(): void
    {
        $actual = base_path() . '/' . $this->publicSymlinkFolder;

        if (!is_dir($actual)) {
            if (!mkdir($actual, 0755, true) && !is_dir($actual)) {
                throw new RuntimeException('Unable to create app/public cache folder: ' . $actual);
            }
        }
    }

    /**
     * Clear cache folder.
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        $extension = ltrim($this->fileExtension, '.');
        $files = glob($this->cacheFolder . DIRECTORY_SEPARATOR . '*.' . $extension);
        $result = true;

        foreach ($files as $file) {
            if (is_file($file)) {
                $result = @unlink($file);
            }
        }

        return $result;
    }

    /**
     * Set cache folder location     *
     * @param string $path
     */
    public function setCacheFolder($path): void
    {
        $this->cacheFolder = str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Set public symlink folder
     * @param string $path
     */
    public function setSymlinkPathFolder($path): void
    {
        $this->publicSymlinkFolder = str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
