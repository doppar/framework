<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\LoggerService debug(mixed $message)
 * @method static \Phaseolies\Support\LoggerService info(mixed $message)
 * @method static \Phaseolies\Support\LoggerService notice(mixed $message)
 * @method static \Phaseolies\Support\LoggerService warning(mixed $message)
 * @method static \Phaseolies\Support\LoggerService error(mixed $message)
 * @method static \Phaseolies\Support\LoggerService critical(string $message)
 * @method static \Phaseolies\Support\LoggerService alert(string $message)
 * @method static \Phaseolies\Support\LoggerService emergency(string $message)
 * @see \Phaseolies\Support\LoggerService
 */

use Phaseolies\Facade\BaseFacade;

class Log extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'log';
    }
}
