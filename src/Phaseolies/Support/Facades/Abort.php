<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Http\Support\RequestAbortion abort(int $code, string $message = '')
 * @method static \Phaseolies\Http\Support\RequestAbortion abortIf(bool $condition, int $code, string $message = '')
 * @see \Phaseolies\Http\Support\RequestAbortion
 */

use Phaseolies\Facade\BaseFacade;

class Abort extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'abort';
    }
}
