<?php

namespace Tests\Unit\Builder;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;

class DatabaseBuilderInRawOrderTest extends TestCase
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

    public function testWhereInGeneratesPlaceholdersAndToSql()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $sql = $builder->whereIn('id', [1, 2, 3])->toSql();
        $this->assertStringContainsString('WHERE users.id IN (?,?,?)', $sql);
    }

    public function testOrWhereInAppendedAfterWhere()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $sql = $builder->where('status', 'active')
            ->orWhereIn('id', [5, 6])
            ->toSql();

        $this->assertStringContainsString('status = ? OR id IN (?,?)', str_replace('users.', '', $sql));
    }

    public function testWhereRawOrderByRawGroupByRawIncludedInSql()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $sql = $builder
            ->select('id')
            ->whereRaw('score > ?', [10])
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('FIELD(status, "active","pending")')
            ->toSql();

        $this->assertStringContainsString('WHERE score > ?', $sql);
        $this->assertStringContainsString('GROUP BY DATE(created_at)', $sql);
        $this->assertStringContainsString('ORDER BY FIELD(status, "active","pending")', $sql);
    }

    public function testSelectRawIsIncluded()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $sql = $builder->selectRaw('COUNT(*) AS c')->toSql();
        $this->assertStringStartsWith('SELECT COUNT(*) AS c FROM users', $sql);
    }

    public function testNewestAndOldestAffectOrderBy()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $sql = $builder->newest('created_at')->oldest('id')->toSql();
        $this->assertStringContainsString('ORDER BY created_at DESC, id ASC', $sql);
    }

    public function testResetClearsBuilderState()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $sqlBefore = $builder
            ->select('id', 'name')
            ->where('status', 'active')
            ->groupBy('id')
            ->orderBy('name')
            ->limit(5)
            ->offset(10)
            ->reset()
            ->toSql();

        $this->assertSame('SELECT * FROM users', $sqlBefore);
    }
}
