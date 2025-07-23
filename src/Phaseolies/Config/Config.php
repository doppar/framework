<?php

namespace Phaseolies\Config;

final class Config
{
    /**
     * Stores loaded configuration data.
     *
     * @var array<string, mixed>
     */
    protected static array $config = [];

    /**
     * Cache file path.
     *
     * @var string
     */
    protected static string $cacheFile;

    /**
     * Generate config cache key based on config files
     * @return string
     */
    protected static function getCacheKey(): string
    {
        $files = glob(base_path() . '/config/*.php');
        $fileHashes = [];

        foreach ($files as $file) {
            $fileHashes[] = md5_file($file);
        }

        return 'config_cache_' . md5(implode('', $fileHashes));
    }

    /**
     * Static method to initialize cacheFile path.
     */
    public static function initialize(): void
    {
        self::$cacheFile = storage_path('framework/cache/configs_' . self::getCacheKey() . '.php');
    }

    /**
     * Dynamically get a configuration value or return null if not found.
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get(string $name): mixed
    {
        return self::get($name);
    }

    /**
     * Load all configuration files from source.
     */
    public static function loadAll(): void
    {
        self::$config = [];
        foreach (glob(base_path() . '/config/*.php') as $file) {
            $fileName = basename($file, '.php');
            self::$config[$fileName] = include $file;
        }
    }

    /**
     * Load configuration from the cache if available and valid.
     */
    public static function loadFromCache(): void
    {
        $files = glob(storage_path('framework/cache/configs_*.php'));

        if (empty($files)) {
            self::loadAll();
            self::cacheConfig();
            return;
        }

        $cachePath = end($files);

        if (file_exists($cachePath)) {
            $cached = include $cachePath;

            if (strpos(basename($cachePath), self::getCacheKey()) !== false) {
                self::$config = $cached;
                return;
            }

            @unlink($cachePath);
        }

        self::loadAll();
        self::cacheConfig();
    }

    /**
     * Cache the configuration to a file.
     */
    protected static function cacheConfig(): void
    {
        $oldFiles = glob(storage_path('framework/cache/configs_*.php'));

        foreach ($oldFiles as $file) {
            if (file_exists($file)) {
                try {
                    @unlink($file);
                } catch (\Throwable $e) {
                    if (file_exists($file)) {
                        throw new \RuntimeException("Failed to unlink old config cache file: {$file}", 0, $e);
                    }
                }
            }
        }

        $cacheDir = dirname(self::$cacheFile);
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $cacheDir));
            }
        }

        file_put_contents(self::$cacheFile, '<?php return ' . var_export(self::$config, true) . ';');
    }

    /**
     * Get a configuration value by key.
     *
     * @param string $key The key to retrieve (dot notation).
     * @return mixed|null The configuration value or null if not found.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $file = array_shift($keys);

        if (!isset(self::$config[$file])) {
            self::loadFromCache();
        }

        $value = self::$config[$file] ?? null;
        foreach ($keys as $keyPart) {
            if (!is_array($value) || !array_key_exists($keyPart, $value)) {
                return $default;
            }
            $value = $value[$keyPart];
        }

        return $value ?? $default;
    }

    /**
     * Set a configuration value.
     *
     * @param string $key The key to set (dot notation).
     * @param mixed $value The value to set.
     */
    public static function set(string $key, mixed $value): void
    {
        try {
            $keys = explode('.', $key);
            $file = array_shift($keys);

            if (!isset(self::$config[$file])) {
                self::$config[$file] = [];
            }

            $current = &self::$config[$file];
            foreach ($keys as $keyPart) {
                if (!isset($current[$keyPart]) || !is_array($current[$keyPart])) {
                    $current[$keyPart] = [];
                }
                $current = &$current[$keyPart];
            }

            if (is_array($current) && is_array($value)) {
                $current = array_merge($current, $value);
            } else {
                $current = $value;
            }

            self::cacheConfig();
        } catch (\Throwable $th) {
            throw new \RuntimeException($th->getMessage());
        }
    }

    /**
     * Get all the configuration settings.
     *
     * @return array<string, mixed> All configurations.
     */
    public static function all(): array
    {
        if (empty(self::$config)) {
            self::loadFromCache();
        }

        return self::$config;
    }

    /**
     * Check if a configuration key exists.
     *
     * @param string $key The key to check (dot notation).
     * @return bool
     */
    public static function has(string $key): bool
    {
        $keys = explode('.', $key);
        $file = array_shift($keys);

        if (!isset(self::$config[$file])) {
            self::loadFromCache();
        }

        $value = self::$config[$file] ?? null;
        foreach ($keys as $keyPart) {
            if (!is_array($value) || !array_key_exists($keyPart, $value)) {
                return false;
            }
            $value = $value[$keyPart];
        }

        return $value !== null;
    }

    /**
     * Clear all cached configuration files.
     */
    public static function clearCache(): void
    {
        $files = glob(storage_path('framework/cache/configs_*.php'));
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        self::$config = [];
    }

    /**
     * Check if config cache is valid
     * @return bool
     */
    public static function isCacheValid(): bool
    {
        $files = glob(storage_path('framework/cache/configs_*.php'));
        if (empty($files)) {
            return false;
        }

        $cachePath = end($files);

        return strpos(basename($cachePath), self::getCacheKey()) !== false;
    }
}
