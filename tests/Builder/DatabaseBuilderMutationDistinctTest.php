<?php

namespace Tests\Unit\Builder;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;

class DatabaseBuilderMutationDistinctTest extends TestCase
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

    public function testIncrementAndDecrementReturnRowCount()
    {
        $this->setBuilderDriver('mysql');

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('bindValue');
        $stmtMock->method('rowCount')->willReturn(3);

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('prepare')->willReturn($stmtMock);

        $builder = $this->createBuilder();
        $affected = $builder->increment('views', 2);
        $this->assertSame(3, $affected);

        $affected2 = $builder->decrement('views', 1, ['status' => 'seen']);
        $this->assertSame(3, $affected2);
    }

    public function testDistinctInvalidColumnThrows()
    {
        $this->setBuilderDriver('mysql');

        $describeStmt = $this->createMock(PDOStatement::class);
        $describeStmt->method('fetchAll')
            ->with($this->anything())
            ->willReturn([ ['Field' => 'id'], ['Field' => 'name'] ]);

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('query')->willReturn($describeStmt);

        $this->expectException(\InvalidArgumentException::class);
        $builder = $this->createBuilder();
        $builder->distinct('status');
    }

    public function testDistinctReturnsCollection()
    {
        $this->setBuilderDriver('mysql');

        $describeStmt = $this->createMock(PDOStatement::class);
        $describeStmt->method('fetchAll')
            ->with($this->anything())
            ->willReturn([ ['Field' => 'id'], ['Field' => 'status'] ]);

        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->method('execute')->willReturn(true);
        $selectStmt->method('fetchAll')->with($this->anything(), $this->equalTo(0))->willReturn(['active', 'pending']);

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('query')->willReturn($describeStmt);
        $this->pdoMock->method('prepare')->willReturn($selectStmt);

        $builder = $this->createBuilder();
        $collection = $builder->distinct('status');
        $this->assertIsObject($collection);
    }
}
