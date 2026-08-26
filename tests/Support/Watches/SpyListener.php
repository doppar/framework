<?php

namespace Tests\Support\Watches;

use Phaseolies\Database\Entity\Model;

/**
 * A test listener that records every invocation so tests can assert
 * on whether it was called, how many times, and with what values.
 */
class SpyListener
{
    public static bool  $called    = false;
    public static int   $callCount = 0;
    public static mixed $lastOld   = null;
    public static mixed $lastNew   = null;
    public static ?Model $lastModel = null;

    public static function reset(): void
    {
        self::$called    = false;
        self::$callCount = 0;
        self::$lastOld   = null;
        self::$lastNew   = null;
        self::$lastModel = null;
    }

    public function handle(mixed $old, mixed $new, Model $model): void
    {
        self::$called = true;
        self::$callCount++;
        self::$lastOld   = $old;
        self::$lastNew   = $new;
        self::$lastModel = $model;
    }
}
