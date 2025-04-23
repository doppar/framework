<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Config\Config set(string key, mixed $value)
 * @method static \Phaseolies\Config\Config get(string key)
 * @method static \Phaseolies\Config\Config all()
 * @method static \Phaseolies\Config\Config clearCache()
 * @see \Phaseolies\Config\Config
 */
use Phaseolies\Facade\BaseFacade;

class Config extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'config';
    }
}
