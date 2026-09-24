<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static string langPath($path = '')
 * @method static string templatesPath($path = '')
 * @method static string bootstrapPath($path = '')
 * @method static string schemaPath($path = '')
 * @method static string publicPath($path = '')
 * @method static string storagePath($path = '')
 * @method static string appPath()
 * @method static string basePath()
 * @method static string configPath($path = '')
 * @method static bool runningInConsole()
 * @method static bool hasBeenBootstrapped()
 * @method static bool isBooted()
 * @method static object|string make($abstract, array $parameters = [])
 * @method static string getLocale()
 * @method static string currentLocale()
 * @method static string getFallbackLocale()
 * @method static void setLocale($locale)
 * @method static void setFallbackLocale($fallbackLocale)
 * @method static bool isLocale($locale)
 *
 * @see \Phaseolies\Application
 */
class App extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'app';
    }
}
