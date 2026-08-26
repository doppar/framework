<?php

namespace Tests\Support\Watches;

/**
 * A listener that intentionally omits the required handle() method.
 * Used to verify that WatchesHandler throws a RuntimeException when
 * the resolved listener does not expose handle().
 */
class NoHandleListener
{
    public function process(): void
    {
        // deliberately not named handle()
    }
}
