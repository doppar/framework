<?php

namespace Tests\Unit\Builder;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;

class DatabaseBuilderWriteOpsTest extends TestCase
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
        return new Builder($this->pdoMock, 'users', __NAMESPACE__ . '\\WriteOpsModelStub', 15);
    }

    public function testInsertReturnsLastInsertId()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('prepare')->willReturn($stmt);
        $this->pdoMock->method('lastInsertId')->willReturn('42');

        $builder = $this->createBuilder();
        $id = $builder->insert(['name' => 'Alice', 'age' => 30]);
        $this->assertSame(42, $id);
    }

    public function testInsertManyEmptyReturnsZero()
    {
        $builder = $this->createBuilder();
        $this->assertSame(0, $builder->insertMany([]));
    }

    public function testInsertManyThrowsOnMismatchedColumns()
    {
        $this->expectException(\InvalidArgumentException::class);
        $builder = $this->createBuilder();
        $builder->insertMany([
            ['name' => 'A', 'age' => 1],
            ['name' => 'B'],
        ]);
    }

    public function testInsertManyBindsAndChunks()
    {
        // Prepare stmt mock with rowCount() aggregation
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('bindValue');
        $stmt->method('rowCount')->willReturn(2);

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('prepare')->willReturn($stmt);

        $builder = $this->createBuilder();
        $affected = $builder->insertMany([
            ['name' => 'A', 'age' => 1],
            ['name' => 'B', 'age' => 2],
            ['name' => 'C', 'age' => 3],
        ], 2);

        // Two chunks -> two executes, rowCount mocked to 2 each so total 4
        $this->assertSame(4, $affected);
    }

    public function testUpdateBuildsSetAndBinds()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('bindValue');

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('prepare')->willReturn($stmt);

        $builder = $this->createBuilder();
        $builder->where('id', 5);
        $ok = $builder->update(['name' => 'Bob', 'age' => 33]);
        $this->assertTrue($ok);
    }

    public function testDeleteBindsConditions()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('bindValue');

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('prepare')->willReturn($stmt);

        $builder = $this->createBuilder();
        $builder->where('status', 'active');
        $ok = $builder->delete();
        $this->assertTrue($ok);
    }
}
class WriteOpsModelStub
{
    public function usesTimestamps(): bool
    {
        return false;
    }
}
