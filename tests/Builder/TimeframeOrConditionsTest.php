<?php

namespace Tests\Unit\Builder;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;

class TimeframeOrConditionsTest extends TestCase
{
    private $pdoMock;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn('mysql');

        $this->setStaticProperty(Database::class, 'connections', ['default' => $this->pdoMock]);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    protected function tearDown(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function setStaticProperty(string $class, string $property, $value): void
    {
        $ref = new \ReflectionClass($class);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue(null, $value);
        $prop->setAccessible(false);
    }

    private function builder(): Builder
    {
        return new Builder($this->pdoMock, 'events', 'App\\Models\\Event', 15);
    }

    public function testOrWhereDateAddsOrConditionAlone()
    {
        $sql = $this->builder()->orWhereDate('created_at', '2024-06-15')->toSql();

        $this->assertStringContainsString('DATE(created_at) = ?', $sql);
    }

    public function testOrWhereDateCombinesWithAndConditionAsOr()
    {
        $sql = $this->builder()
            ->where('status', 'active')
            ->orWhereDate('created_at', '2024-06-15')
            ->toSql();

        $this->assertStringContainsString('status = ?', $sql);
        $this->assertStringContainsString('OR', $sql);
        $this->assertStringContainsString('DATE(created_at) = ?', $sql);
    }

    public function testOrWhereDateWithExplicitOperator()
    {
        $sql = $this->builder()
            ->where('type', 'meeting')
            ->orWhereDate('scheduled_at', '>=', '2024-01-01')
            ->toSql();

        $this->assertStringContainsString('DATE(scheduled_at) >= ?', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereMonthAddsOrCondition()
    {
        $sql = $this->builder()
            ->where('status', 'published')
            ->orWhereMonth('created_at', 6)
            ->toSql();

        $this->assertStringContainsString('MONTH(created_at)', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereMonthWithExplicitOperator()
    {
        $sql = $this->builder()
            ->where('active', 1)
            ->orWhereMonth('published_at', '>=', 3)
            ->toSql();

        $this->assertStringContainsString('MONTH(published_at) >= ?', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereMonthAloneAddsCondition()
    {
        $sql = $this->builder()->orWhereMonth('created_at', 12)->toSql();

        $this->assertStringContainsString('MONTH(created_at)', $sql);
    }

    public function testOrWhereYearAddsOrCondition()
    {
        $sql = $this->builder()
            ->where('status', 'active')
            ->orWhereYear('created_at', 2023)
            ->toSql();

        $this->assertStringContainsString('YEAR(created_at)', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereYearWithExplicitOperator()
    {
        $sql = $this->builder()
            ->where('type', 'sale')
            ->orWhereYear('sold_at', '>', 2020)
            ->toSql();

        $this->assertStringContainsString('YEAR(sold_at) > ?', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereYearAloneAddsCondition()
    {
        $sql = $this->builder()->orWhereYear('created_at', 2024)->toSql();

        $this->assertStringContainsString('YEAR(created_at)', $sql);
    }

    public function testOrWhereDayAddsOrCondition()
    {
        $sql = $this->builder()
            ->where('status', 'confirmed')
            ->orWhereDay('event_date', 15)
            ->toSql();

        $this->assertStringContainsString('DAY(event_date)', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereDayWithExplicitOperator()
    {
        $sql = $this->builder()
            ->where('type', 'reminder')
            ->orWhereDay('remind_at', '<=', 10)
            ->toSql();

        $this->assertStringContainsString('DAY(remind_at) <= ?', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereDayAloneAddsCondition()
    {
        $sql = $this->builder()->orWhereDay('created_at', 1)->toSql();

        $this->assertStringContainsString('DAY(created_at)', $sql);
    }

    public function testOrWhereTimeAddsOrCondition()
    {
        $sql = $this->builder()
            ->where('category', 'morning')
            ->orWhereTime('starts_at', '09:00:00')
            ->toSql();

        $this->assertStringContainsString('TIME(starts_at)', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereTimeWithExplicitOperator()
    {
        $sql = $this->builder()
            ->where('active', 1)
            ->orWhereTime('created_at', '>=', '18:00:00')
            ->toSql();

        $this->assertStringContainsString('TIME(created_at) >= ?', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereTimeAloneAddsCondition()
    {
        $sql = $this->builder()->orWhereTime('starts_at', '=', '12:00:00')->toSql();

        $this->assertStringContainsString('TIME(starts_at) = ?', $sql);
    }

    public function testOrWhereYesterdayAddsOrDateCondition()
    {
        $sql = $this->builder()
            ->where('status', 'pending')
            ->orWhereYesterday('created_at')
            ->toSql();

        $this->assertStringContainsString('DATE(created_at) = ?', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereYesterdayAloneAddsDateCondition()
    {
        $sql = $this->builder()->orWhereYesterday('updated_at')->toSql();

        $this->assertStringContainsString('DATE(updated_at) = ?', $sql);
    }

    public function testOrWhereTimeForPostgresUsesExtract()
    {
        $pgPdo = $this->createMock(PDO::class);
        $pgPdo->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn('pgsql');

        $b = new Builder($pgPdo, 'events', 'App\\Models\\Event', 15);

        $sql = $b->where('active', 1)->orWhereTime('created_at', '09:00:00')->toSql();

        $this->assertStringContainsString('created_at::time', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testOrWhereMonthForSqliteUsesStrftime()
    {
        $sqPdo = $this->createMock(PDO::class);
        $sqPdo->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn('sqlite');

        $b = new Builder($sqPdo, 'events', 'App\\Models\\Event', 15);

        $sql = $b->where('active', 1)->orWhereMonth('created_at', 4)->toSql();

        $this->assertStringContainsString("strftime('%m'", $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testChainedOrWhereTimeframeConditions()
    {
        $sql = $this->builder()
            ->whereYear('created_at', 2023)
            ->orWhereMonth('created_at', 12)
            ->orWhereDay('created_at', 31)
            ->toSql();

        $this->assertStringContainsString('YEAR(created_at)', $sql);
        $this->assertStringContainsString('MONTH(created_at)', $sql);
        $this->assertStringContainsString('DAY(created_at)', $sql);
    }

    public function testFormatDateWithStringDateReturnsFirst10Chars()
    {
        $b = $this->builder();

        $this->assertEquals('2024-06-15', $b->formatDate('2024-06-15'));
        $this->assertEquals('2024-06-15', $b->formatDate('2024-06-15 12:30:45'));
    }

    public function testFormatDateWithDateTimeInterface()
    {
        $b = $this->builder();
        $dt = new \DateTime('2024-03-20 08:00:00');

        $this->assertEquals('2024-03-20', $b->formatDate($dt));
    }

    public function testFormatDateWithImmutableDateTime()
    {
        $b = $this->builder();
        $dt = new \DateTimeImmutable('2024-12-01 23:59:59');

        $this->assertEquals('2024-12-01', $b->formatDate($dt));
    }

    public function testFormatDateThrowsOnInvalidInput()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->formatDate(12345);
    }

    public function testFormatDateTimeWithFullDatetimeString()
    {
        $b = $this->builder();

        $this->assertEquals('2024-06-15 10:30:00', $b->formatDateTime('2024-06-15 10:30:00'));
    }

    public function testFormatDateTimeStartTypeAddsMidnight()
    {
        $b = $this->builder();

        $this->assertEquals('2024-06-15 00:00:00', $b->formatDateTime('2024-06-15', 'start'));
    }

    public function testFormatDateTimeEndTypeAdds235959()
    {
        $b = $this->builder();

        $result = $b->formatDateTime('2024-06-15', 'end');

        $this->assertEquals('2024-06-15 23:59:59', $result);
    }

    public function testFormatDateTimeWithDateTimeInterface()
    {
        $b = $this->builder();
        $dt = new \DateTime('2024-03-20 15:45:00');

        $this->assertEquals('2024-03-20 15:45:00', $b->formatDateTime($dt));
    }

    public function testFormatDateTimeThrowsOnInvalidInput()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->formatDateTime(99999);
    }
}
