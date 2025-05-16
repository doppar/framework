<?php

namespace Phaseolies\Support;

use Carbon\Carbon;
use DateTimeZone;

class TimezoneHandler
{
    /**
     * @var string|null $currentTimezone
     */
    protected ?string $currentTimezone = 'UTC';

    /**
     * Constructor for the TimezoneHandler class.
     *
     * @param string|null $currentTimezone Optional timezone string.
     */
    public function __construct(?string $currentTimezone = null)
    {
        // Set the current timezone.  Use the provided timezone if available,
        // otherwise, use the value from the application's configuration.
        // If the configuration value is not set, default to 'UTC'.
        $this->currentTimezone = $currentTimezone ?? config('app.timezone', 'UTC');

        $this->initialize();
    }

    /**
     * Initializes the timezone settings.
     *
     * This method sets the default PHP timezone using `date_default_timezone_set()`
     * and calls `setTimezone()` to attempt to configure the Carbon timezone.
     *
     * @return void
     */
    protected function initialize(): void
    {
        // Set the default timezone for PHP.  This affects how PHP's date/time
        // functions (like `date()`) behave.
        date_default_timezone_set($this->currentTimezone);

        // Carbon configuration
        $this->setTimezone();
    }

    /**
     * Sets the timezone for Carbon.
     *
     * @return void
     * @throws \InvalidArgumentException If the provided timezone is invalid.
     */
    public function setTimezone(): void
    {
        if (!in_array($this->currentTimezone, DateTimeZone::listIdentifiers())) {
            throw new \InvalidArgumentException("Invalid timezone: {$this->currentTimezone}");
        }

        $dt = Carbon::now();
        $dt->setTimezone($this->currentTimezone);
    }

    /**
     * Returns a Carbon instance representing the current time.
     *
     * @return Carbon
     */
    public function now(): Carbon
    {
        return Carbon::now();
    }
}
