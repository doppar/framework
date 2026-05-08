<?php

namespace Phaseolies\Providers;

use Phaseolies\Providers\ServiceProvider;
use Phaseolies\Providers\GhostableProvider;
use Phaseolies\Cache\IncrementableCacheInterface;
use Phaseolies\Cache\RateLimiter;

class RateLimiterServiceProvider extends ServiceProvider implements GhostableProvider
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
    public function boot()
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
