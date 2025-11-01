<?php

namespace Tests\Unit\Builder;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;

class DatabaseBuilderBetweenNullCountTest extends TestCase
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

    public function testWhereBetweenAndNotBetweenSql()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $sql = $builder->whereBetween('age', [18, 30])->toSql();
        $this->assertStringContainsString('WHERE age BETWEEN ? AND ?', $sql);

        $sql2 = $this->createBuilder()->whereNotBetween('age', [50, 60])->toSql();
        $this->assertStringContainsString('WHERE age NOT BETWEEN ? AND ?', $sql2);
    }

    public function testOrWhereBetweenAndOrWhereNotBetweenSql()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $sql = $builder->where('status', 'active')
            ->orWhereBetween('age', [18, 30])
            ->toSql();
        $this->assertStringContainsString('status = ? OR age BETWEEN ? AND ?', $sql);

        $sql2 = $this->createBuilder()->where('status', 'active')
            ->orWhereNotBetween('age', [50, 60])
            ->toSql();
        $this->assertStringContainsString('status = ? OR age NOT BETWEEN ? AND ?', $sql2);
    }

    public function testWhereNullVariantsSql()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $sql = $builder->whereNull('deleted_at')->toSql();
        $this->assertStringContainsString('WHERE deleted_at IS NULL', $sql);

        $sql2 = $this->createBuilder()->whereNotNull('deleted_at')->toSql();
        $this->assertStringContainsString('WHERE deleted_at IS NOT NULL', $sql2);

        $sql3 = $this->createBuilder()->where('status', 'active')->orWhereNull('deleted_at')->toSql();
        $this->assertStringContainsString('status = ? OR deleted_at IS NULL', $sql3);

        $sql4 = $this->createBuilder()->where('status', 'active')->orWhereNotNull('deleted_at')->toSql();
        $this->assertStringContainsString('status = ? OR deleted_at IS NOT NULL', $sql4);
    }

    public function testCountWithGroupByUsesSubquery()
    {
        $this->setBuilderDriver('mysql');

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->with($this->anything())->willReturn(['aggregate' => 5]);

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $builder = $this->createBuilder();
        $builder->select('id')->groupBy('id');
        $count = $builder->count();
        $this->assertSame(5, $count);
    }
}
