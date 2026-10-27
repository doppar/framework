<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static void abort(int $code, string $message = '', array $headers = [])
 * @method static void abortIf(bool $condition, int $code, string $message = '', array $headers = [])
 *
 * @see \Phaseolies\Http\Contracts\RequestAbortionInterface
 */
class Abort extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'abort';
    }
}
