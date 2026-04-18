<?php

namespace Tests\Unit\Builder;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class DatabaseBuilderDynamicWhereTest extends TestCase
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
        $property->setValue(null, $value);
    }

    private function createBuilder(): Builder
    {
        return new Builder($this->pdoMock, 'users', 'Tests\\Unit\\Builder\\DynamicUserArrayModel', 15);
    }

    private function getConditions(Builder $builder): array
    {
        $r = new \ReflectionClass($builder);
        $p = $r->getProperty('conditions');
        $c = $p->getValue($builder);
        return $c;
    }

    public function testDynamicWhereCamelToSnake()
    {
        $builder = $this->createBuilder();
        $builder = $builder->whereUserName('John');
        $conditions = $this->getConditions($builder);
        $this->assertEquals(['AND', 'user_name', '=', 'John'], $conditions[0]);
    }

    public function testDynamicWhereNullEqualsBecomesIsNull()
    {
        $builder = $this->createBuilder();
        $builder = $builder->whereDeletedAt();
        $conditions = $this->getConditions($builder);
        $this->assertEquals(['AND', 'deleted_at', 'IS NULL'], $conditions[0]);
    }

    public function testDynamicWhereNullNotEqualsBecomesIsNotNull()
    {
        $builder = $this->createBuilder();
        $builder = $builder->whereDeletedAt('!=', null);
        $conditions = $this->getConditions($builder);
        $this->assertEquals(['AND', 'deleted_at', 'IS NOT NULL'], $conditions[0]);
    }
}

// Minimal stub model to support Builder->first()/model creation if needed
class DynamicUserArrayModel
{
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $k => $v) {
            $this->$k = $v;
        }
    }
}
