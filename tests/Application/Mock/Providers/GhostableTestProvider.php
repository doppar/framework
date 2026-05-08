<?php

namespace Tests\Application\Mock\Providers;

use Phaseolies\Providers\ServiceProvider;
use Phaseolies\Providers\GhostableProvider;

class GhostableTestProvider extends ServiceProvider implements GhostableProvider
{
    public static int $registerCount = 0;
    public static int $bootCount = 0;

    public static function resetState(): void
    {
        self::$registerCount = 0;
        self::$bootCount = 0;
    }

    public function register(): void
    {
        self::$registerCount++;

        $this->app->singleton('ghost.service', fn() => 'ghost-value');
    }

    public function boot(): void
    {
        self::$bootCount++;

        $this->app->singleton('ghost.booted', fn() => 'booted');
    }

    public function ghosts(): array
    {
        return [
            'ghost.service',
        ];
    }
}
