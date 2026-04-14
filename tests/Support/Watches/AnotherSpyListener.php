<?php

namespace Tests\Support\Watches;

use Phaseolies\Database\Entity\Model;

/**
 * A second independent spy listener used to verify fan-out:
 * multiple watches on the same property all fire independently.
 */
class AnotherSpyListener
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
