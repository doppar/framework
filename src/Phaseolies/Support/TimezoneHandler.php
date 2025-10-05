<?php

namespace Phaseolies\Support;

use Carbon\Carbon;

class TimezoneHandler
{
    /**
     * Application current timezone
     *
     * @var string $currentTimezone
     */
    protected string $currentTimezone;

    /**
     * Constructor for the TimezoneHandler class.
     *
     * @param string $currentTimezone.
     */
    public function __construct(string $currentTimezone = 'UTC')
    {
        $this->currentTimezone = $currentTimezone;

        $this->initialize();
    }

    /**
     * Initializes the timezone settings.
     *
     * @return void
     */
    protected function initialize(): void
    {
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
     * Get the application current timezone
     *
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->currentTimezone;
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
