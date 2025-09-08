<?php

namespace Phaseolies\Translation;

use Phaseolies\Translation\FileLoader;

class Translator extends FileLoader
{
    /**
     * The file loader instance responsible for loading translation files.
     *
     * @var FileLoader
     */
    protected $loader;

    /**
     * The current locale being used for translations.
     *
     * @var string
     */
    protected $locale;

    /**
     * The fallback locale to use when a translation isn't found.
     *
     * @var string
     */
    protected $fallback;

    /**
     * Array of loaded translation lines grouped by namespace, group, and locale.
     *
     * @var array
     */
    protected $loaded = [];

    /**
     * Create a new translator instance.
     *
     * @param FileLoader $loader The file loader instance
     * @param string $locale The default locale
     */
    public function __construct(FileLoader $loader, $locale)
    {
        $this->loader = $loader;
        $this->locale = $locale;
        $this->fallback = config('app.fallback_locale', 'en');
    }

    /**
     * Get the translation for the given key.
     * Alias for the get() method.
     *
     * @param string $key The translation key
     * @param array $replace Placeholder replacements
     * @param string|null $locale The locale to use
     * @return string The translated string or the key if not found
     */
    public function trans($key, array $replace = [], $locale = null)
    {
        return $this->get($key, $replace, $locale);
    }

    /**
     * Get the translation for the given key.
     *
     * @param string $key The translation key
     * @param array $replace Placeholder replacements
     * @param string|null $locale The locale to use
     * @return string The translated string or the key if not found
     */
    public function get($key, array $replace = [], $locale = null)
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        $locale = $locale ?: $this->locale;

        if ($group === '*') {
            return $key;
        }

        $this->loadTranslations($namespace, $group, $locale);

        $line = $this->getLine(
            $namespace,
            $group,
            $locale,
            $item,
            $replace
        );

        if (!is_null($line)) {
            return $line;
        }

        if ($this->fallback && $locale !== $this->fallback) {
            return $this->get($key, $replace, $this->fallback);
        }

        return $key;
    }

    /**
     * Parse a key into namespace, group, and item.
     *
     * @param string $key The translation key to parse
     * @return array [namespace, group, item]
     */
    protected function parseKey($key)
    {
        // Handle package translations (namespace::group.item)
        if (strpos($key, '::') !== false) {
            $segments = explode('::', $key, 2);
            $namespace = $segments[0];
            $rest = $segments[1];

            if (strpos($rest, '.') !== false) {
                list($group, $item) = explode('.', $rest, 2);
                return [$namespace, $group, $item];
            }

            return [$namespace, '*', $rest];
        }

        // Handle regular translations (group.item)
        if (strpos($key, '.') !== false) {
            list($group, $item) = explode('.', $key, 2);
            return [null, $group, $item];
        }

        // Fallback for simple keys
        return [null, '*', $key];
    }

    /**
     * Parse a namespaced key into components.
     *
     * @param string $key The namespaced key to parse
     * @return array [namespace, group, item]
     */
    protected function parseNamespacedKey($key)
    {
        $segments = explode('::', $key);

        if (count($segments) !== 2) {
            return [null, '*', $key];
        }

        $item = $segments[1];

        if (strpos($item, '.') !== false) {
            list($group, $item) = explode('.', $item, 2);
            return [$segments[0], $group, $item];
        }

        return [$segments[0], null, $item];
    }

    /**
     * Get a translation line from the loaded translations.
     *
     * @param string|null $namespace The namespace
     * @param string|null $group The group
     * @param string $locale The locale
     * @param string $item The item key
     * @param array $replace Placeholder replacements
     * @return string|array|null The translated string, array of translations, or null if not found
     */
    protected function getLine($namespace, $group, $locale, $item, array $replace)
    {
        $this->loadTranslations($namespace, $group, $locale);

        $keys = explode('.', $item);
        $line = $this->loaded[$namespace][$group][$locale] ?? null;

        foreach ($keys as $key) {
            if (!is_array($line)) {
                return null;
            }
            $line = $line[$key] ?? null;
        }

        if (is_string($line)) {
            return $this->makeReplacements($line, $replace);
        } elseif (is_array($line) && count($line) > 0) {
            return $line;
        }

        return null;
    }

    /**
     * Make the place-holder replacements on a translation line.
     *
     * @param string $line The translation line
     * @param array $replace The replacements
     * @return string The line with replacements applied
     */
    public function makeReplacements($line, array $replace)
    {
        foreach ($replace as $key => $value) {
            $line = str_replace(
                [':' . $key, ':' . strtoupper($key), ':' . ucfirst($key)],
                [$value, strtoupper($value), ucfirst($value)],
                $line
            );
        }

        return $line;
    }

    /**
     * Load the specified language group.
     *
     * @param string|null $namespace The namespace
     * @param string|null $group The group
     * @param string $locale The locale
     * @return void
     */
    public function loadTranslations($namespace, $group, $locale)
    {
        if ($this->isLoaded($namespace, $group, $locale)) {
            return;
        }

        $lines = $this->loader->load($locale, $group, $namespace);

        $this->loaded[$namespace][$group][$locale] = $lines;
    }

    /**
     * Determine if the given group has been loaded.
     *
     * @param string|null $namespace The namespace
     * @param string|null $group The group
     * @param string $locale The locale
     * @return bool
     */
    protected function isLoaded($namespace, $group, $locale)
    {
        return isset($this->loaded[$namespace][$group][$locale]);
    }

    /**
     * Set the current locale.
     *
     * @param string $locale The locale to set
     * @return void
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * Get the current locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set the fallback locale.
     *
     * @param string $fallback The fallback locale
     * @return void
     */
    public function setFallback($fallback)
    {
        $this->fallback = $fallback;
    }

    /**
     * Get the fallback locale.
     *
     * @return string
     */
    public function getFallback()
    {
        return $this->fallback;
    }
}
