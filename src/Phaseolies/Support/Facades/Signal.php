<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static \Phaseolies\Signals\SignalBus on(string $signal, callable|array|string $listener, int $priority = 0)
 * @method static \Phaseolies\Signals\SignalBus forget(?string $signal = null)
 * @method static bool hasListeners(object|string $signal)
 * @method static void emit(object $signal)
 * @method static void emitNow(object $signal)
 * @method static bool emitOnSettle(object $signal, ?string $connection = null)
 * @method static \Phaseolies\Signals\SignalBus reportFailuresUsing(?callable $reporter)
 *
 * @see \Phaseolies\Signals\SignalBus
 */
class Signal extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'signal';
    }
}
