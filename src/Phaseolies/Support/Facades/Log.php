<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static void debug(mixed $message, array $context = [])
 * @method static void info(mixed $message, array $context = [])
 * @method static void notice(mixed $message, array $context = [])
 * @method static void warning(mixed $message, array $context = [])
 * @method static void error(mixed $message, array $context = [])
 * @method static void critical(mixed $message, array $context = [])
 * @method static void alert(mixed $message, array $context = [])
 * @method static void emergency(mixed $message, array $context = [])
 *
 * @see \Phaseolies\Support\LoggerService
 */
class Log extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'log';
    }
}
