<?php

namespace Phaseolies\Providers;

use Phaseolies\Providers\ServiceProvider;
use Phaseolies\Cache\IncrementableCacheInterface;
use Phaseolies\Cache\RateLimiter;

class RateLimiterServiceProvider extends ServiceProvider
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
}
