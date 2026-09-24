<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static void set(string $key, mixed $value)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static array all()
 * @method static void clearCache()
 *
 * @see \Phaseolies\Config\Config
 */
class Config extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'config';
    }
}
