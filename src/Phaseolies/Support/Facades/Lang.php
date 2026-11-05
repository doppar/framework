<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static string trans($key, array $replace = [], $locale = null)
 * @method static string get($key, array $replace = [], $locale = null)
 * @method static string makeReplacements($line, array $replace)
 * @method static array load(string $locale, string $group, ?string $namespace = null)
 * @method static void setLocale($locale)
 * @method static string getLocale()
 * @method static void setFallback($fallback)
 * @method static string getFallback()
 *
 * @see \Phaseolies\Translation\Translator
 */
class Lang extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'translator';
    }
}
