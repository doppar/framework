<?php

namespace Tests\Unit\Model\TemporalBranch;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Phaseolies\Database\Temporal\TemporalManager;

/**
 * Point-in-time strings are compared with `valid_from`. On PostgreSQL that is
 * a TIMESTAMPTZ written with an explicit offset, so reads must carry one too
 * or they are interpreted in the database session's time zone.
 */
final class TemporalDateTimeNormalizationTest extends TestCase
{
    private string $originalTimezone;

    protected function setUp(): void
    {
        $this->originalTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function partialDatetimes(): array
    {
        return [
            'date only'      => ['2026-02-01', '2026-02-01 23:59:59.999999'],
            'to the minute'  => ['2026-02-01 10:30', '2026-02-01 10:30:59.999999'],
            'to the second'  => ['2026-02-01 10:30:15', '2026-02-01 10:30:15.999999'],
            'with micros'    => ['2026-02-01 10:30:15.123456', '2026-02-01 10:30:15.123456'],
            'surrounding ws' => ['  2026-02-01  ', '2026-02-01 23:59:59.999999'],
        ];
    }

    #[DataProvider('partialDatetimes')]
    public function testExpandsPartialDatetimesOnDriversWithoutTimezones(string $input, string $expected): void
    {
        $this->assertSame($expected, TemporalManager::normalizeDateTime($input, 'mysql'));
        $this->assertSame($expected, TemporalManager::normalizeDateTime($input, 'sqlite'));
    }

    #[DataProvider('partialDatetimes')]
    public function testPgsqlAppendsTheApplicationOffset(string $input, string $expected): void
    {
        $this->assertSame($expected . '+00:00', TemporalManager::normalizeDateTime($input, 'pgsql'));
    }

    public function testPgsqlUsesTheApplicationTimezoneNotUtc(): void
    {
        date_default_timezone_set('Asia/Dhaka');
        Carbon::setTestNow();

        $this->assertSame(
            '2026-02-01 10:30:59.999999+06:00',
            TemporalManager::normalizeDateTime('2026-02-01 10:30', 'pgsql')
        );
    }

    public function testPgsqlAppliesTheOffsetInEffectOnThatDateAcrossDst(): void
    {
        date_default_timezone_set('America/New_York');

        $this->assertSame('2026-01-15 12:00:00.999999-05:00', TemporalManager::normalizeDateTime('2026-01-15 12:00:00', 'pgsql'));
        $this->assertSame('2026-07-15 12:00:00.999999-04:00', TemporalManager::normalizeDateTime('2026-07-15 12:00:00', 'pgsql'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function explicitOffsets(): array
    {
        return [
            'positive'     => ['2026-02-01 10:30:00.000000+06:00'],
            'negative'     => ['2026-02-01 10:30:00-05:00'],
            'short offset' => ['2026-02-01 10:30:00+06'],
            'compact'      => ['2026-02-01 10:30:00+0600'],
            'zulu'         => ['2026-02-01T10:30:00Z'],
        ];
    }

    #[DataProvider('explicitOffsets')]
    public function testExplicitOffsetsAreNeverRewritten(string $input): void
    {
        date_default_timezone_set('Asia/Dhaka');

        $this->assertSame($input, TemporalManager::normalizeDateTime($input, 'pgsql'));
    }

    public function testNormalizingTwiceIsStable(): void
    {
        $once = TemporalManager::normalizeDateTime('2026-02-01 10:30', 'pgsql');

        $this->assertSame($once, TemporalManager::normalizeDateTime($once, 'pgsql'));
    }

    public function testUnparseableInputIsReturnedUntouchedForTheDatabaseToReject(): void
    {
        $this->assertSame('not a date', TemporalManager::normalizeDateTime('not a date', 'pgsql'));
    }
}
