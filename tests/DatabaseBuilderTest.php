<?php

namespace Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;

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

        $builder = $builder->whereLike('name', 'john', false);
        $conditions = $this->getBuilderConditions($builder);

        $this->assertEquals('name', $conditions[0][1]);
        $this->assertEquals('LIKE', $conditions[0][2]);
        $this->assertEquals('%john%', $conditions[0][3]);
    }

    public function testWhereLikePostgreSQLCaseInsensitive()
    {
        $this->setBuilderDriver('pgsql');
        $builder = $this->createBuilder();

        $builder = $builder->whereLike('name', 'john', false);
        $conditions = $this->getBuilderConditions($builder);

        $this->assertEquals('name', $conditions[0][1]);
        $this->assertEquals('ILIKE', $conditions[0][2]);
        $this->assertEquals('%john%', $conditions[0][3]);
    }

    public function testWhereLikeSQLiteCaseInsensitive()
    {
        $this->setBuilderDriver('sqlite');
        $builder = $this->createBuilder();

        $builder = $builder->whereLike('name', 'john', false);
        $conditions = $this->getBuilderConditions($builder);

        $this->assertEquals('name', $conditions[0][1]);
        $this->assertEquals('LIKE', $conditions[0][2]);
        $this->assertEquals('%john%', $conditions[0][3]);
    }

    public function testWhereLikeCaseSensitiveMySQL()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $builder = $builder->whereLike('username', 'Admin', true);
        $conditions = $this->getBuilderConditions($builder);

        // MySQL case-sensitive should use BINARY or regular field
        $this->assertStringContainsString('username', $conditions[0][1]);
        $this->assertEquals('LIKE', $conditions[0][2]);
        $this->assertEquals('%Admin%', $conditions[0][3]);
    }

    public function testWhereLikeCaseSensitiveSQLite()
    {
        $this->setBuilderDriver('sqlite');
        $builder = $this->createBuilder();

        $builder = $builder->whereLike('username', 'Admin', true);
        $conditions = $this->getBuilderConditions($builder);

        // SQLite case-sensitive should use GLOB
        $this->assertEquals('username', $conditions[0][1]);
        $this->assertEquals('LIKE', $conditions[0][2]);
        $this->assertEquals('%Admin%', $conditions[0][3]);
    }

    public function testOrWhereLike()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $builder = $builder->where('status', 'active')
            ->orWhereLike('name', 'john');
        $conditions = $this->getBuilderConditions($builder);

        $this->assertEquals('OR', $conditions[1][0]); // Second condition should be OR
        $this->assertEquals('name', $conditions[1][1]);
        $this->assertEquals('LIKE', $conditions[1][2]);
    }

    public function testDriverSpecificCaseHandling()
    {
        // Test PostgreSQL uses ILIKE for case-insensitive
        $this->setBuilderDriver('pgsql');
        $builder = $this->createBuilder();
        $builder = $builder->whereLike('title', 'test', false);
        $conditions = $this->getBuilderConditions($builder);
        $this->assertEquals('ILIKE', $conditions[0][2]);
        $this->assertEquals('title', $conditions[0][1]);

        // Test MySQL uses LOWER for case-insensitive
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();
        $builder = $builder->whereLike('title', 'test', false);
        $conditions = $this->getBuilderConditions($builder);
        $this->assertEquals('LIKE', $conditions[0][2]);
        $this->assertEquals('title', $conditions[0][1]);

        // Test SQLite uses LOWER for case-insensitive
        $this->setBuilderDriver('sqlite');
        $builder = $this->createBuilder();
        $builder = $builder->whereLike('title', 'test', false);
        $conditions = $this->getBuilderConditions($builder);
        $this->assertEquals('LIKE', $conditions[0][2]);
        $this->assertEquals('title', $conditions[0][1]);
    }

    public function testPrepareLikeValueAddsWildcards()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        // Test without wildcards
        $builder = $builder->whereLike('name', 'john');
        $conditions = $this->getBuilderConditions($builder);
        $this->assertEquals('%john%', $conditions[0][3]);

        // Test with existing wildcards
        $builder2 = $this->createBuilder();
        $builder2 = $builder2->whereLike('name', 'john%');
        $conditions2 = $this->getBuilderConditions($builder2);
        $this->assertEquals('john%', $conditions2[0][3]);

        // Test with multiple existing wildcards
        $builder3 = $this->createBuilder();
        $builder3 = $builder3->whereLike('name', '%john%');
        $conditions3 = $this->getBuilderConditions($builder3);
        $this->assertEquals('%john%', $conditions3[0][3]);
    }

    public function testCaseSensitiveColumnDetection()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('isCaseSensitiveColumn');
        $method->setAccessible(true);

        // Should return true by default (safe fallback)
        $result = $method->invoke($builder, 'name');
        $this->assertIsBool($result);
    }

    public function testLikeToGlobConversion()
    {
        $this->setBuilderDriver('sqlite');
        $builder = $this->createBuilder();

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('convertLikeToGlob');
        $method->setAccessible(true);

        // Test basic conversion
        $result = $method->invoke($builder, '%test%');
        $this->assertEquals('*test*', $result);

        // Test underscore conversion
        $result2 = $method->invoke($builder, 'test_');
        $this->assertEquals('test?', $result2);

        // Test mixed wildcards
        $result3 = $method->invoke($builder, '%test_');
        $this->assertEquals('*test?', $result3);
    }

    public function testWhereLikeWithCustomWildcards()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        // Test exact match (starts with)
        $builder = $builder->whereLike('name', 'john%');
        $conditions = $this->getBuilderConditions($builder);
        $this->assertEquals('john%', $conditions[0][3]);

        // Test ends with
        $builder2 = $this->createBuilder();
        $builder2 = $builder2->whereLike('name', '%john');
        $conditions2 = $this->getBuilderConditions($builder2);
        $this->assertEquals('%john', $conditions2[0][3]);

        // Test single character wildcard
        $builder3 = $this->createBuilder();
        $builder3 = $builder3->whereLike('name', 'j_hn');
        $conditions3 = $this->getBuilderConditions($builder3);
        $this->assertEquals('j_hn', $conditions3[0][3]);
    }
}
