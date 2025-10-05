<?php

namespace Phaseolies\Support;

use Carbon\Carbon;

class TimezoneHandler
{
    /**
     * Application current timezone
     *
     * @var string|null $currentTimezone
     */
    protected ?string $currentTimezone = 'UTC';

    /**
     * Constructor for the TimezoneHandler class.
     *
     * @param string|null $currentTimezone.
     */
    public function __construct(?string $currentTimezone = null)
    {
        $this->currentTimezone = $currentTimezone ?? config('app.timezone', 'UTC');

        $this->initialize();
    }

    /**
     * Initializes the timezone settings.
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
     * Sets the timezone for Carbon
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setTimezone(): void
    {
        if (!in_array($this->currentTimezone, \DateTimeZone::listIdentifiers())) {
            throw new \InvalidArgumentException("Invalid timezone: {$this->currentTimezone}");
        }

        $dt = Carbon::now();
        $dt->setTimezone($this->currentTimezone);
    }

    /**
     * Returns a Carbon instance representing the current time
     *
     * @return Carbon
     */
    public function now(): Carbon
    {
        return Carbon::now();
    }
}
