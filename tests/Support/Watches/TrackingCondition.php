<?php

namespace Tests\Support\Watches;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Watches\WatchConditionInterface;

/**
 * A condition whose return value is configurable at test time.
 * Also records the ($old, $new, $model) it received so tests can
 * assert that WatchesHandler passes the correct arguments.
 */
class TrackingCondition implements WatchConditionInterface
{
    public static bool   $result    = true;
    public static mixed  $lastOld   = null;
    public static mixed  $lastNew   = null;
    public static ?Model $lastModel = null;
    public static int    $callCount = 0;

    public static function reset(): void
    {
        self::$result    = true;
        self::$lastOld   = null;
        self::$lastNew   = null;
        self::$lastModel = null;
        self::$callCount = 0;
    }

    public function evaluate(mixed $old, mixed $new, Model $model): bool
    {
        self::$lastOld   = $old;
        self::$lastNew   = $new;
        self::$lastModel = $model;
        self::$callCount++;

        return self::$result;
    }
}
