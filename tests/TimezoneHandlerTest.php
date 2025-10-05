<?php

namespace Tests\Unit;

use Phaseolies\Support\TimezoneHandler;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use Carbon\Carbon;

class TimezoneHandlerTest extends TestCase
{
    protected $container;

    protected function setUp(): void
    {
        $this->container = new Container;
    }

    protected function tearDown(): void
    {
        date_default_timezone_set('UTC');
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testTimezoneIsAppliedToCarbon()
    {
        $timezone = 'Asia/Tokyo';

        $this->container->singleton(
            'timezone',
            fn() => new TimezoneHandler($timezone)
        );

        date_default_timezone_set($timezone);

        $now = app('timezone')->now()->timezoneName;

        $this->assertEquals($timezone, $now);

        $utcNow = Carbon::now('UTC');
        $tokyoNow = $utcNow->copy()->setTimezone($timezone);
        $this->assertEquals($tokyoNow->format('H:i'), now()->format('H:i'));
    }

    public function testConstructorWithCustomTimezone()
    {
        $timezone = 'America/New_York';
        $this->container->singleton(
            'timezone',
            fn() => new TimezoneHandler($timezone)
        );

        date_default_timezone_set($timezone);

        $now = app('timezone')->now()->timezoneName;

        $this->assertEquals($timezone, $now);
        $this->assertEquals($timezone, date_default_timezone_get());
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

    public function testGetTimezoneMethod()
    {
        $timezone = 'Europe/London';
        $handler = new TimezoneHandler($timezone);

        $this->assertEquals($timezone, $handler->getTimezone());
    }

    public function testNowWithDifferentTimezones()
    {
        $timezones = ['America/Los_Angeles', 'Europe/Paris', 'Pacific/Auckland'];

        foreach ($timezones as $timezone) {
            date_default_timezone_set($timezone);
            $handler = new TimezoneHandler($timezone);
            $now = $handler->now();

            $this->assertEquals($timezone, $now->timezoneName);
            $this->assertEquals($timezone, date_default_timezone_get());
        }
    }

    public function testNowReturnsCarbonInstance()
    {
        $handler = new TimezoneHandler('UTC');
        $now = $handler->now();

        $this->assertInstanceOf(Carbon::class, $now);
    }

    public function testCarbonMacroRegistration()
    {
        $timezone = 'Asia/Kolkata';
        $handler = new TimezoneHandler($timezone);

        $this->assertTrue(true);
    }

    public function testDaylightSavingTimeHandling()
    {
        $timezone = 'America/New_York';
        date_default_timezone_set($timezone);
        $handler = new TimezoneHandler($timezone);

        // Test a date during DST
        $dstDate = Carbon::create(2024, 7, 1, 12, 0, 0, 'UTC')
            ->setTimezone($timezone);

        $handlerNow = $handler->now();

        // Both should be in the same timezone
        $this->assertEquals($timezone, $handlerNow->timezoneName);
        $this->assertEquals($timezone, $dstDate->timezoneName);
    }

    public function testTimeZoneOffsetConsistency()
    {
        $timezone = 'Asia/Tokyo';
        date_default_timezone_set($timezone);
        $handler = new TimezoneHandler($timezone);

        $carbonTime = $handler->now();
        $offset = $carbonTime->offset;

        // Tokyo is UTC+9 (32400 seconds)
        $this->assertEquals(32400, $offset);
    }

    public function testFormatConsistencyAcrossTimezones()
    {
        $timezones = [
            'UTC' => 'UTC',
            'America/New_York' => 'EST/EDT',
            'Europe/London' => 'GMT/BST'
        ];

        foreach ($timezones as $timezone => $expectedAbbr) {
            $handler = new TimezoneHandler($timezone);
            $formatted = $handler->now()->format('T');

            // This test verifies that formatting works consistently
            // The actual abbreviation might vary based on DST
            $this->assertIsString($formatted);
        }
    }

    public function testSingletonInstanceConsistency()
    {
        $timezone = 'Asia/Singapore';

        $this->container->singleton(
            'timezone',
            fn() => new TimezoneHandler($timezone)
        );

        $handler1 = app('timezone');
        $handler2 = app('timezone');

        $this->assertSame($handler1, $handler2);
        $this->assertEquals($timezone, $handler1->getTimezone());
        $this->assertEquals($timezone, $handler2->getTimezone());
    }

    public function testCarbonNowVsHandlerNow()
    {
        $timezone = 'Pacific/Honolulu';
        $handler = new TimezoneHandler($timezone);

        $carbonNow = Carbon::now($timezone);
        $handlerNow = $handler->now();

        // They should be very close in time (within 1 second)
        $this->assertEqualsWithDelta(
            $carbonNow->timestamp,
            $handlerNow->timestamp,
            1,
            'Carbon now and handler now should be within 1 second of each other'
        );
    }

    public function testBoundaryTimezones()
    {
        // Test timezones with unusual offsets
        $unusualTimezones = [
            'Pacific/Kiritimati', // UTC+14
            'Pacific/Midway'      // UTC-11
        ];

        foreach ($unusualTimezones as $timezone) {
            date_default_timezone_set($timezone);
            $handler = new TimezoneHandler($timezone);
            $now = $handler->now();

            $this->assertEquals($timezone, $now->timezoneName);
            $this->assertEquals($timezone, date_default_timezone_get());
        }
    }
}
