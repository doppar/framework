<?php

namespace Phaseolies\Providers;

use Psr\SimpleCache\CacheInterface;
use Phaseolies\Providers\ServiceProvider;
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
            return new RateLimiter($app->make(CacheInterface::class));
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
