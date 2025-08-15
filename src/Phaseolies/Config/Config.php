<?php

namespace Phaseolies\Config;

use RuntimeException;

final class Config
{
    /**
     * Loaded configuration data.
     *
     * @var array<string, mixed>
     */
    protected static array $config = [];

    /**
     * Path to the configuration cache file.
     *
     * @var string|null
     */
    protected static ?string $cacheFile = null;

    /**
     * Flag indicating if the configuration was loaded from cache.
     *
     * @var bool
     */
    protected static bool $loadedFromCache = false;

    /**
     * Stores hashes of configuration files used to detect changes.
     *
     * @var array<string, string>
     */
    protected static array $fileHashes = [];

    /**
     * Initialize the configuration system.
     * Loads the configuration from cache if available, otherwise loads from source files.
     *
     * @return void
     */
    public static function initialize(): void
    {
        if (self::$cacheFile === null) {
            self::$cacheFile = storage_path('framework/cache/config.php');
            self::loadFromCache();
        }
    }

    /**
     * Generate a unique cache key based on all configuration files.
     * Includes both file content (md5) and last modification time.
     *
     * @return string
     */
    protected static function getCacheKey(): string
    {
        static $cacheKey = null;

        if ($cacheKey === null) {
            $files = glob(base_path('config/*.php'));
            $hashes = [];
            foreach ($files as $file) {
                $hashes[] = md5_file($file) . '|' . filemtime($file);
            }
            $cacheKey = 'config_' . md5(implode('', $hashes));
        }

        return $cacheKey;
    }

    /**
     * Load all configuration files from the source directory.
     * Automatically caches the loaded configuration.
     *
     * @return void
     */
    public static function loadAll(): void
    {
        self::$config = [];

        foreach (glob(base_path('config/*.php')) as $file) {
            $key = basename($file, '.php');
            self::$config[$key] = include $file;
        }

        self::cacheConfig();
    }

    /**
     * Load configuration from the cache if valid; otherwise, reload from source.
     *
     * @return void
     */
    public static function loadFromCache(): void
    {
        if (!file_exists(self::$cacheFile) || !self::isCacheValid()) {
            self::$loadedFromCache = false;
            self::loadAll();
            return;
        }

        $cached = include self::$cacheFile;
        self::$config = $cached['data'] ?? [];
        self::$fileHashes = $cached['_meta']['file_hashes'] ?? [];
        self::$loadedFromCache = true;
    }

    /**
     * Write the current configuration to cache.
     * Uses a temporary file and rename to avoid race conditions.
     *
     * @return void
     */
    public static function cacheConfig(): void
    {
        if (self::$loadedFromCache && !self::configWasModified()) {
            return;
        }

        $cacheDir = dirname(self::$cacheFile);
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            throw new RuntimeException("Failed to create cache directory: {$cacheDir}");
        }

        $files = glob(base_path('config/*.php'));

        $hashes = [];
        foreach ($files as $file) {
            $hashes[basename($file)] = md5_file($file) . '|' . filemtime($file);
        }

        $cacheContent = [
            '_meta' => [
                'cache_key' => self::getCacheKey(),
                'file_hashes' => $hashes,
                'created_at' => time(),
            ],
            'data' => self::$config,
        ];

        $tempFile = self::$cacheFile . '.tmp';
        file_put_contents($tempFile, '<?php return ' . var_export($cacheContent, true) . ';', LOCK_EX);
        rename($tempFile, self::$cacheFile);

        self::$loadedFromCache = true;
        self::$fileHashes = $hashes;
    }

    /**
     * Check if the configuration data has been modified compared to cache.
     *
     * @return bool
     */
    protected static function configWasModified(): bool
    {
        if (!file_exists(self::$cacheFile)) return true;

        $cached = include self::$cacheFile;

        return $cached['data'] !== self::$config;
    }

    /**
     * Get a configuration value using dot notation.
     *
     * @param string $key Dot notation key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::initialize();

        $keys = explode('.', $key);
        $file = array_shift($keys);

        if (!isset(self::$config[$file])) return $default;

        $value = self::$config[$file];

        foreach ($keys as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) return $default;
            $value = $value[$part];
        }

        return $value ?? $default;
    }

    /**
     * Set a configuration value using dot notation.
     *
     * @param string $key Dot notation key
     * @param mixed $value Value to set
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::initialize();

        $keys = explode('.', $key);
        $file = array_shift($keys);

        if (!isset(self::$config[$file])) self::$config[$file] = [];

        $current = &self::$config[$file];

        foreach ($keys as $part) {
            if (!isset($current[$part]) || !is_array($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }

        if (is_array($current) && is_array($value)) {
            $current = array_replace_recursive($current, $value);
        } else {
            $current = $value;
        }

        self::cacheConfig();
    }

    /**
     * Get all loaded configuration data.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (empty(self::$config)) self::loadFromCache();

        return self::$config;
    }

    /**
     * Check if a configuration key exists.
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::get($key, null) !== null;
    }

    /**
     * Clear the configuration cache and reset in-memory config.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        if (file_exists(self::$cacheFile)) @unlink(self::$cacheFile);

        self::$config = [];

        self::$fileHashes = [];
    }

    /**
     * Check if the cached configuration is valid.
     *
     * @return bool
     */
    public static function isCacheValid(): bool
    {
        static $cacheValid = null;

        if ($cacheValid === null) {
            if (!file_exists(self::$cacheFile)) {
                $cacheValid = false;
            } else {
                $cached = include self::$cacheFile;
                $cacheValid = isset($cached['_meta']['cache_key']) &&
                    $cached['_meta']['cache_key'] === self::getCacheKey();
            }
        }

        return $cacheValid;
    }
}
