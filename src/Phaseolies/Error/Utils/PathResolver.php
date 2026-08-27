<?php

namespace Phaseolies\Error\Utils;

class PathResolver
{
    /**
     * Cached PSR-4 prefix => directories map from Composer's autoloader.
     *
     * @var array<string, string[]>|null
     */
    private static ?array $psr4Map = null;

    /**
     * Convert an absolute file path into its namespace-style path
     * (e.g. "App/Http/Controllers/HomeController.php") by matching it
     * against the application's registered PSR-4 autoload prefixes.
     *
     * Falls back to a path relative to the application base path when
     * no PSR-4 prefix matches the given file.
     *
     * @param string $absoluteFile
     * @return string
     */
    public static function toDisplayPath(string $absoluteFile): string
    {
        $match = self::matchPsr4Prefix($absoluteFile);

        if ($match === null) {
            return str_replace(base_path() . '/', '', $absoluteFile);
        }

        [$prefix, $directory] = $match;

        $relative = substr($absoluteFile, strlen($directory));
        $namespacePath = str_replace('\\', '/', rtrim($prefix, '\\'));

        return $namespacePath . '/' . ltrim($relative, '/');
    }

    /**
     * Find the longest matching PSR-4 directory for the given file.
     *
     * @param string $absoluteFile
     * @return array{0: string, 1: string}|null
     */
    private static function matchPsr4Prefix(string $absoluteFile): ?array
    {
        $map = self::psr4Map();

        $bestPrefix = null;
        $bestDirectory = null;

        foreach ($map as $prefix => $directories) {
            foreach ($directories as $directory) {
                $directory = rtrim($directory, '/') . '/';

                if (
                    str_starts_with($absoluteFile, $directory)
                    && ($bestDirectory === null || strlen($directory) > strlen($bestDirectory))
                ) {
                    $bestPrefix = $prefix;
                    $bestDirectory = $directory;
                }
            }
        }

        return $bestPrefix === null ? null : [$bestPrefix, $bestDirectory];
    }

    /**
     * Load and cache Composer's PSR-4 autoload map.
     *
     * @return array<string, string[]>
     */
    private static function psr4Map(): array
    {
        if (self::$psr4Map !== null) {
            return self::$psr4Map;
        }

        $file = base_path('vendor/composer/autoload_psr4.php');

        try {
            self::$psr4Map = is_file($file) ? (require $file) : [];
        } catch (\Throwable) {
            self::$psr4Map = [];
        }

        return self::$psr4Map;
    }
}
