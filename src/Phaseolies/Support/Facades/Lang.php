<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Translation\Translator trans($key, array $replace = [], $locale = null)
 * @method static \Phaseolies\Translation\Translator get($key, array $replace = [], $locale = null)
 * @method static \Phaseolies\Application makeReplacements($line, array $replace)
 * @method static \Phaseolies\Application load($namespace, $group, $locale)
 * @method static \Phaseolies\Application setLocale($locale)
 * @method static \Phaseolies\Application getLocale()
 * @method static \Phaseolies\Application setFallback($fallback)
 * @method static \Phaseolies\Application getFallback()
 * @see \Phaseolies\Translation\Translator
 */
use Phaseolies\Facade\BaseFacade;

class Lang extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'translator';
    }
}
