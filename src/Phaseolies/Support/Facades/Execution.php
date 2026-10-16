<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static \Phaseolies\Support\ExecutionContext|null current()
 * @method static bool running()
 * @method static string|null id()
 * @method static string|null type()
 * @method static mixed get(string $key, mixed $default = null)
 * @method static array all()
 * @method static \Phaseolies\Support\ExecutionContext stamp(array $metadata)
 * @method static mixed within(string $type, array $metadata, callable $callback)
 * @method static void beginning(callable $listener, ?string $type = null)
 * @method static void ending(callable $listener, ?string $type = null)
 *
 * @see \Phaseolies\Support\ExecutionService
 */
class Execution extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'execution';
    }
}
