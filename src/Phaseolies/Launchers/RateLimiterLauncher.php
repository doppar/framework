<?php

namespace Phaseolies\Launchers;

use Phaseolies\Launchers\ServiceLauncher;
use Phaseolies\Launchers\GhostableLauncher;
use Phaseolies\Cache\IncrementableCacheInterface;
use Phaseolies\Cache\RateLimiter;

class RateLimiterLauncher extends ServiceLauncher implements GhostableLauncher
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(RateLimiter::class, function ($app) {
            return new RateLimiter($app->make(IncrementableCacheInterface::class));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function launch()
    {
        //
    }

    /**
     * Get the services that should ghost-load this provider.
     *
     * @return array<int, string>
     */
    public function ghosts(): array
    {
        return [
            RateLimiter::class,
        ];
    }
}
