<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static \Phaseolies\Signals\Contracts\SignalBusInterface on(string $signal, callable|array|string $listener, int $priority = 0)
 * @method static \Phaseolies\Signals\Contracts\SignalBusInterface forget(?string $signal = null)
 * @method static bool hasListeners(object|string $signal)
 * @method static void emit(object $signal)
 * @method static void emitNow(object $signal)
 * @method static bool emitOnSettle(object $signal, ?string $connection = null)
 * @method static \Phaseolies\Signals\Contracts\SignalBusInterface reportFailuresUsing(?callable $reporter)
 *
 * @see \Phaseolies\Signals\Contracts\SignalBusInterface
 */
class Signal extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'signal';
    }
}
