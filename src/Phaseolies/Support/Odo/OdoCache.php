<?php

namespace Phaseolies\Support\Odo;

use RuntimeException;

trait OdoCache
{
    /**
     * Path to the cache folder for compiled odo templates
     *
     * @var string|null
     */
    protected $cacheFolder;

    /**
     * Path to the public symlink folder linked to the public directory
     *
     * @var string|null
     */
    protected $publicSymlinkFolder;

    /**
     * Create cache folder.
     *
     * @return void
     */
    public function createCacheFolder(): void
    {
        $actual = base_path() . DIRECTORY_SEPARATOR . $this->cacheFolder;

        if (!is_dir($actual)) {
            if (!mkdir($actual, 0755, true) && !is_dir($actual)) {
                throw new RuntimeException('Unable to create view cache folder: ' . $actual);
            }
        }

        if (!is_writable($actual)) {
            throw new RuntimeException('View cache folder is not writable: ' . $actual);
        }
    }

    /**
     * Create storage symlink folder that will be connected to public directory.
     *
     * @return void
     */
    public function createPublicSymlinkFolder(): void
    {
        $actual = base_path() . DIRECTORY_SEPARATOR . $this->publicSymlinkFolder;

        if (!is_dir($actual)) {
            if (!mkdir($actual, 0755, true) && !is_dir($actual)) {
                throw new RuntimeException('Unable to create app/public cache folder: ' . $actual);
            }
        }

        if (!is_writable($actual)) {
            throw new RuntimeException('App/public folder is not writable: ' . $actual);
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

        $files = glob(str_replace('\\', '/', $this->cacheFolder) . '/*.' . $extension);
        $result = true;

        foreach ($files as $file) {
            if (is_file($file) && !@unlink($file)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Set cache folder location
     *
     * @param string $path
     */
    public function setCacheFolder($path): void
    {
        $this->cacheFolder = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    /**
     * Set public symlink folder
     *
     * @param string $path
     */
    public function setSymlinkPathFolder($path): void
    {
        $this->publicSymlinkFolder = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
