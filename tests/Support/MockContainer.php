<?php

namespace Tests\Support;

use Phaseolies\DI\Container;

class MockContainer extends Container
{
    public function storagePath(string $path = ''): string
    {
        $base = sys_get_temp_dir() . '/phaseolies_storage';

        if (!is_dir($base)) {
            mkdir($base, 0777, true);
        }

        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }

    public function runningInConsole(): bool
    {
        return true;
    }
}
