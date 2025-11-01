<?php

namespace Tests\Unit\Builder;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;

class DatabaseBuilderCountFetchTest extends TestCase
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
        // Use a local stub model for first()/get()
        return new Builder($this->pdoMock, 'users', __NAMESPACE__ . '\\UserArrayModel', 15);
    }

    public function testCountWithoutGroupReturnsAggregate()
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->with($this->anything())->willReturn(['aggregate' => 7]);

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('prepare')->willReturn($stmt);

        $builder = $this->createBuilder();
        // add decorations that should be cleared by count()
        $builder->orderBy('name')->limit(5)->offset(10);
        $count = $builder->count();
        $this->assertSame(7, $count);
    }

    public function testFirstReturnsModelAndNullWhenNoRows()
    {
        // First call: one row
        $stmt1 = $this->createMock(PDOStatement::class);
        $stmt1->method('execute')->willReturn(true);
        $stmt1->method('fetch')->with($this->anything())->willReturn(['id' => 1, 'name' => 'Alice']);

        // Second call: no rows
        $stmt2 = $this->createMock(PDOStatement::class);
        $stmt2->method('execute')->willReturn(true);
        $stmt2->method('fetch')->with($this->anything())->willReturn(false);

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('prepare')->willReturnOnConsecutiveCalls($stmt1, $stmt2);

        $builder = $this->createBuilder();
        $model = $builder->first();
        $this->assertInstanceOf(__NAMESPACE__ . '\\UserArrayModel', $model);
        $this->assertSame('Alice', $model->name);

        $model2 = $builder->first();
        $this->assertNull($model2);
    }

    public function testExistsTrueAndFalse()
    {
        // True: fetch returns row
        $stmt1 = $this->createMock(PDOStatement::class);
        $stmt1->method('execute')->willReturn(true);
        $stmt1->method('fetch')->with($this->anything())->willReturn(['id' => 1]);

        // False: fetch returns false
        $stmt2 = $this->createMock(PDOStatement::class);
        $stmt2->method('execute')->willReturn(true);
        $stmt2->method('fetch')->with($this->anything())->willReturn(false);

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('prepare')->willReturnOnConsecutiveCalls($stmt1, $stmt2);

        $builder = $this->createBuilder();
        $this->assertTrue($builder->exists());
        $this->assertFalse($builder->exists());
    }
}

#[\AllowDynamicProperties]
class UserArrayModel
{
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $k => $v) {
            $this->$k = $v;
        }
    }

    public function getKeyName(): string
    {
        return 'id';
    }
}
