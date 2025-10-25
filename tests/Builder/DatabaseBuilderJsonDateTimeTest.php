<?php

namespace Tests\Unit\Builder;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;

class DatabaseBuilderJsonDateTimeTest extends TestCase
{
    private $database;
    private $pdoMock;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->setStaticProperty(Database::class, 'connections', ['default' => $this->pdoMock]);
        $this->setStaticProperty(Database::class, 'transactions', []);
        $this->database = new Database('default');
    }

    protected function tearDown(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function setStaticProperty(string $className, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($className);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue(null, $value);
        $property->setAccessible(false);
    }

    private function createBuilder(): Builder
    {
        return new Builder($this->pdoMock, 'users', 'App\\Models\\User', 15);
    }

    private function setBuilderDriver(string $driver): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn($driver);
    }

    public function testJsonContainsMySQL()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();
        [$expr, $bindings] = $builder->jsonContains('meta', '$.status', 'active');
        $this->assertStringContainsString('JSON_CONTAINS(`meta`, CAST(? AS JSON),', $expr);
        $this->assertSame(['"active"'], $bindings);
    }

    public function testJsonContainsPostgreSQL()
    {
        $this->setBuilderDriver('pgsql');
        $builder = $this->createBuilder();
        [$expr, $bindings] = $builder->jsonContains('meta', '$.profile.status', 'active');
        $this->assertStringContainsString('meta::jsonb', $expr);
        $this->assertStringContainsString("->> 'profile'", $expr);
        $this->assertStringContainsString("->> 'status'", $expr);
        $this->assertStringEndsWith(' = ?', $expr);
        $this->assertSame(['active'], $bindings);
    }

    public function testJsonContainsSQLite()
    {
        $this->setBuilderDriver('sqlite');
        $builder = $this->createBuilder();
        [$expr, $bindings] = $builder->jsonContains('meta', '$.enabled', true);
        $this->assertSame('json_extract(meta, ?) = ?', $expr);
        $this->assertSame(['$.enabled', 1], $bindings);
    }

    public function testDateTimeHelpersPerDriver()
    {
        // MySQL
        $this->setBuilderDriver('mysql');
        $b = $this->createBuilder();
        $this->assertSame('MONTH(created_at)', $b->month('created_at'));
        $this->assertSame('YEAR(created_at)', $b->year('created_at'));
        $this->assertSame('DAY(created_at)', $b->day('created_at'));
        $this->assertSame('TIME(created_at)', $b->time('created_at'));
        $this->assertSame('HOUR(created_at)', $b->hour('created_at'));
        $this->assertSame('MINUTE(created_at)', $b->minute('created_at'));
        $this->assertSame('SECOND(created_at)', $b->second('created_at'));

        // PostgreSQL
        $this->setBuilderDriver('pgsql');
        $b = $this->createBuilder();
        $this->assertSame('EXTRACT(MONTH FROM created_at)', $b->month('created_at'));
        $this->assertSame('EXTRACT(YEAR FROM created_at)', $b->year('created_at'));
        $this->assertSame('EXTRACT(DAY FROM created_at)', $b->day('created_at'));
        $this->assertSame('created_at::time', $b->time('created_at'));
        $this->assertSame('EXTRACT(HOUR FROM created_at)', $b->hour('created_at'));
        $this->assertSame('EXTRACT(MINUTE FROM created_at)', $b->minute('created_at'));
        $this->assertSame('EXTRACT(SECOND FROM created_at)', $b->second('created_at'));

        // SQLite
        $this->setBuilderDriver('sqlite');
        $b = $this->createBuilder();
        $this->assertSame("CAST(strftime('%m', created_at) AS INTEGER)", $b->month('created_at'));
        $this->assertSame("CAST(strftime('%Y', created_at) AS INTEGER)", $b->year('created_at'));
        $this->assertSame("CAST(strftime('%d', created_at) AS INTEGER)", $b->day('created_at'));
        $this->assertSame('time(created_at)', $b->time('created_at'));
        $this->assertSame("CAST(strftime('%H', created_at) AS INTEGER)", $b->hour('created_at'));
        $this->assertSame("CAST(strftime('%M', created_at) AS INTEGER)", $b->minute('created_at'));
        $this->assertSame("CAST(strftime('%S', created_at) AS INTEGER)", $b->second('created_at'));
    }
}
