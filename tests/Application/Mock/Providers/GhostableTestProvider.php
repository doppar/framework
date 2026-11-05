<?php

namespace Tests\Application\Mock\Providers;

use Phaseolies\Launchers\ServiceLauncher;
use Phaseolies\Launchers\GhostableLauncher;

class GhostableTestProvider extends ServiceLauncher implements GhostableLauncher
{
    public static int $registerCount = 0;
    public static int $bootCount = 0;
    public static bool $resolveDuringRegister = false;

    public static function resetState(): void
    {
        self::$registerCount = 0;
        self::$bootCount = 0;
        self::$resolveDuringRegister = false;
    }

    public function register(): void
    {
        self::$registerCount++;

        $this->app->singleton('ghost.service', fn() => 'ghost-value');

        if (self::$resolveDuringRegister) {
            $this->app->make('ghost.service');
        }
    }

    public function launch(): void
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
