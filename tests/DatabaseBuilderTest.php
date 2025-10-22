<?php

namespace Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Eloquent\Builder;

class DatabaseBuilderTest extends TestCase
{
    private $database;
    private $pdoMock;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        
        $this->pdoMock->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn('mysql');

        $this->setStaticProperty(Database::class, 'connections', ['default' => $this->pdoMock]);
        $this->setStaticProperty(Database::class, 'transactions', []);

        $this->database = new Database('default');
    }

    protected function tearDown(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    /**
     * Helper method to set static properties
     */
    private function setStaticProperty(string $className, string $propertyName, $value): void
    {
        try {
            $reflection = new \ReflectionClass($className);
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue(null, $value);
            $property->setAccessible(false);
        } catch (\ReflectionException $e) {
            $this->fail("Failed to set static property {$propertyName}: " . $e->getMessage());
        }
    }

    /**
     * Helper to create a new builder with the current driver settings
     */
    private function createBuilder(): Builder
    {
        return new Builder($this->pdoMock, 'users', 'App\\Models\\User', 15);
    }

    /**
     * Helper to set driver for builder
     */
    private function setBuilderDriver(string $driver): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn($driver);
    }

    /**
     * Helper to get builder conditions for assertion
     */
    private function getBuilderConditions(Builder $builder): array
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('conditions');
        $property->setAccessible(true);
        $conditions = $property->getValue($builder);
        $property->setAccessible(false);
        return $conditions;
    }

    public function testWhereLikeMySQLCaseInsensitive()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();
        
        $builder = $builder->whereLike('name', 'john');
        $sql = $builder->toSql();
        $conditions = $this->getBuilderConditions($builder);
        
        $this->assertStringContainsString('LOWER(name) LIKE ?', $sql);
        $this->assertEquals('john', $conditions[0][3]); // value should be lowercase
        $this->assertEquals('LIKE', $conditions[0][2]);
    }

    public function testWhereLikePostgreSQLCaseInsensitive()
    {
        $this->setBuilderDriver('pgsql');
        $builder = $this->createBuilder();
        
        $builder = $builder->whereLike('name', 'john');
        $sql = $builder->toSql();
        $conditions = $this->getBuilderConditions($builder);
        
        // PostgreSQL should use ILIKE, not LOWER
        $this->assertStringContainsString('name ILIKE ?', $sql);
        $this->assertEquals('john', $conditions[0][3]); // value remains as-is
        $this->assertEquals('ILIKE', $conditions[0][2]);
    }

    public function testWhereLikeSQLiteCaseInsensitive()
    {
        $this->setBuilderDriver('sqlite');
        $builder = $this->createBuilder();
        
        $builder = $builder->whereLike('name', 'john');
        $sql = $builder->toSql();
        $conditions = $this->getBuilderConditions($builder);
        
        $this->assertStringContainsString('LOWER(name) LIKE ?', $sql);
        $this->assertEquals('john', $conditions[0][3]); // value should be lowercase
        $this->assertEquals('LIKE', $conditions[0][2]);
    }

    public function testWhereLikeCaseSensitive()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();
        
        $builder = $builder->whereLike('username', 'Admin', true);
        $sql = $builder->toSql();
        $conditions = $this->getBuilderConditions($builder);
        
        $this->assertStringContainsString('username LIKE ?', $sql);
        $this->assertStringNotContainsString('LOWER', $sql);
        $this->assertEquals('Admin', $conditions[0][3]);
    }

    public function testOrWhereLike()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();
        
        $builder = $builder->where('status', 'active')
                          ->orWhereLike('name', 'john');
        $sql = $builder->toSql();
        $conditions = $this->getBuilderConditions($builder);
        
        $this->assertStringContainsString('OR', $sql);
        $this->assertEquals('OR', $conditions[1][0]); // Second condition should be OR
    }

    public function testDriverSpecificCaseHandling()
    {
        // Test PostgreSQL uses ILIKE
        $this->setBuilderDriver('pgsql');
        $builder = $this->createBuilder();
        $builder = $builder->whereLike('title', 'test');
        $sql = $builder->toSql();
        $this->assertStringContainsString('ILIKE', $sql);
        $this->assertStringNotContainsString('LOWER', $sql);

        // Test MySQL uses LOWER
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();
        $builder = $builder->whereLike('title', 'test');
        $sql = $builder->toSql();
        $this->assertStringContainsString('LOWER', $sql);
        $this->assertStringNotContainsString('ILIKE', $sql);

        // Test SQLite uses LOWER
        $this->setBuilderDriver('sqlite');
        $builder = $this->createBuilder();
        $builder = $builder->whereLike('title', 'test');
        $sql = $builder->toSql();
        $this->assertStringContainsString('LOWER', $sql);
        $this->assertStringNotContainsString('ILIKE', $sql);
    }
}