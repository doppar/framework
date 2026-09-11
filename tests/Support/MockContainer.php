<?php

namespace Tests\Support;

use Phaseolies\DI\Container;

class MockContainer extends Container
{
    /**
     * Package root, matching what the old getcwd()-based base_path()
     * fallback resolved to when the suite is run from here.
     *
     * @var string
     */
    protected string $basePath = __DIR__ . '/../..';

    public function basePath(string $path = ''): string
    {
        if ($path === '') {
            return $this->basePath;
        }

        $normalizedPath = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        return rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $normalizedPath;
    }

    public function storagePath(string $path = ''): string
    {
        $base = sys_get_temp_dir() . '/phaseolies_storage';

        if (!is_dir($base)) {
            mkdir($base, 0777, true);
        }

        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }

    public function runningInConsole(): bool
    {
        return true;
    }
}
