<?php

namespace Phaseolies;

use Phaseolies\Support\TimezoneHandler;

class ApplicationBuilder
{
    /**
     * @param Application $app
     */
    public function __construct(protected Application $app) {}

    /**
     * Set the application timezone
     *
     * @return self
     */
    public function withTimezone(): self
    {
        $timezone = $this->app['config']->get('app.timezone', 'UTC');

        date_default_timezone_set($timezone);

        $this->app->singleton('timezone', fn() => new TimezoneHandler($timezone));

        return $this;
    }

    /**
     * Finalizes the builder process and returns the configured application
     *
     * @return Application
     */
    public function build(): Application
    {
        return $this->app;
    }
}
