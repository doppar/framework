<?php

namespace Phaseolies\Providers;

trait CoreProviders
{
    /**
     * Loads the core service providers for the application.
     *
     * @return array
     */
    protected function loadCoreProviders()
    {
        return [
            \Phaseolies\Providers\EnvServiceProvider::class,
            \Phaseolies\Providers\FacadeServiceProvider::class,
            \Phaseolies\Providers\LanguageServiceProvider::class,
            \Phaseolies\Providers\SessionServiceProvider::class,
            \Phaseolies\Providers\RouteServiceProvider::class,
            \Phaseolies\Providers\CacheServiceProvider::class,
            \Phaseolies\Providers\RateLimiterServiceProvider::class,
        ];
    }
}
