<?php

namespace Phaseolies\Support\Router;

trait InteractsWithDynamicControllerBinding
{
    /**
     * Get Composer's ClassLoader instance
     *
     * @return object|null
     */
    protected function getComposerClassLoader(): ?object
    {
        $vendorDir = base_path('/vendor');
        $autoloadFile = $vendorDir . '/autoload.php';

        if (!file_exists($autoloadFile)) {
            return null;
        }

        foreach (spl_autoload_functions() as $autoloader) {
            if (!is_array($autoloader)) {
                continue;
            }

            $loader = $autoloader[0] ?? null;

            if (!is_object($loader)) {
                continue;
            }

            $class = get_class($loader);
            if ($class === 'Composer\\Autoload\\ClassLoader') {
                return $loader;
            }
        }

        return null;
    }

    /**
     * Find all PHP files in a directory recursively
     *
     * @param string $path
     * @return array
     */
    protected function findPhpFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        } catch (\Exception $e) {
        }

        return $files;
    }

    /**
     * Convert file path to fully qualified class name
     *
     * @param string $filePath
     * @param string $basePath
     * @param string $namespace
     * @return string|null
     */
    protected function convertFileToClassName(string $filePath, string $basePath, string $namespace): ?string
    {
        // Normalize paths
        $filePath = str_replace('\\', '/', realpath($filePath));
        $basePath = str_replace('\\', '/', realpath($basePath));

        if (!str_starts_with($filePath, $basePath)) {
            return null;
        }

        // Get relative path from base
        $relativePath = substr($filePath, strlen($basePath));
        $relativePath = ltrim($relativePath, '/');

        // Remove .php extension
        $relativePath = substr($relativePath, 0, -4);

        // Convert path to namespace
        $classPath = str_replace('/', '\\', $relativePath);

        // Combine with namespace prefix
        $className = rtrim($namespace, '\\') . '\\' . $classPath;

        return $className;
    }

    /**
     * Check if a class is a controller
     *
     * @param string $class
     * @return bool
     */
    protected function isControllerClass(string $class): bool
    {
        try {
            $reflection = new \ReflectionClass($class);

            if (
                $reflection->isAbstract() ||
                $reflection->isInterface() ||
                $reflection->isTrait()
            ) {
                return false;
            }

            if (str_ends_with($reflection->getShortName(), 'Controller')) {
                return true;
            }

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (!empty($method->getAttributes(
                    \Phaseolies\Support\Router\Attributes\Route::class
                ))) {
                    return true;
                }
            }

            if ($reflection->isSubclassOf(\App\Http\Controllers\Controller::class)) {
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Fallback: scan default controller directory
     *
     * @return array
     */
    protected function scanDefaultControllerDirectory(): array
    {
        $controllerPath = base_path('src/Http/Controllers');
        $controllers = [];

        if (!is_dir($controllerPath)) {
            return $controllers;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllerPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($controllerPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
            $className = 'App\\Http\\Controllers\\' . str_replace('.php', '', $relativePath);

            if (class_exists($className)) {
                $controllers[] = $className;
            }
        }

        return $controllers;
    }
}
