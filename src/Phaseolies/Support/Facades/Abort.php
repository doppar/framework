<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static void abort(int $code, string $message = '')
 * @method static void abortIf(bool $condition, int $code, string $message = '')
 *
 * @see \Phaseolies\Http\Support\RequestAbortion
 */
class Abort extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'abort';
    }
}
