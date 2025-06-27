<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Phaseolies\Support\TimezoneHandler;
use PHPUnit\Framework\TestCase;
use DateTimeZone;

class TimezoneHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        date_default_timezone_set('UTC');
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testConstructorWithCustomTimezone()
    {
        $timezone = 'America/New_York';
        $handler = new TimezoneHandler($timezone);

        $this->assertEquals($timezone, $handler->now()->timezoneName);
        $this->assertEquals($timezone, date_default_timezone_get());
    }

    public function testTimezoneIsAppliedToCarbon()
    {
        $timezone = 'Asia/Tokyo';
        $handler = new TimezoneHandler($timezone);

        $now = $handler->now();
        $this->assertEquals($timezone, $now->timezoneName);

        // Verify the time is correct for the timezone
        $utcNow = Carbon::now('UTC');
        $tokyoNow = $utcNow->copy()->setTimezone($timezone);
        $this->assertEquals($tokyoNow->format('H:i'), $now->format('H:i'));
    }

    public function testTimezoneAffectsDateFunctions()
    {
        $timezone = 'Australia/Sydney';
        $handler = new TimezoneHandler($timezone);

        // Verify that PHP's date functions are affected
        $carbonTime = $handler->now();
        $phpTime = date('H:i');

        $this->assertEquals($carbonTime->format('H:i'), $phpTime);
    }
}
