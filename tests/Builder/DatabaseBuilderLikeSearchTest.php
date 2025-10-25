<?php

namespace Tests\Unit\Builder;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;

class DatabaseBuilderLikeSearchTest extends TestCase
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
        $this->pdoMock->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn($driver);
    }

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

        $this->assertEquals('LOWER(name)', $conditions[0][1]);
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

        $this->assertEquals('LOWER(name)', $conditions[0][1]);
        $this->assertEquals('LIKE', $conditions[0][2]);
        $this->assertEquals('%john%', $conditions[0][3]);
    }

    public function testWhereLikeCaseSensitiveMySQL()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $builder = $builder->whereLike('username', 'Admin', true);
        $conditions = $this->getBuilderConditions($builder);

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

        $this->assertEquals('username', $conditions[0][1]);
        $this->assertEquals('GLOB', $conditions[0][2]);
        $this->assertEquals('*Admin*', $conditions[0][3]);
    }

    public function testOrWhereLike()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $builder = $builder->where('status', 'active')
            ->orWhereLike('name', 'john');
        $conditions = $this->getBuilderConditions($builder);

        $this->assertEquals('OR', $conditions[1][0]);
        $this->assertEquals('LOWER(name)', $conditions[1][1]);
        $this->assertEquals('LIKE', $conditions[1][2]);
    }

    public function testDriverSpecificCaseHandling()
    {
        $this->setBuilderDriver('pgsql');
        $builder = $this->createBuilder();
        $builder = $builder->whereLike('title', 'test', false);
        $conditions = $this->getBuilderConditions($builder);
        $this->assertEquals('ILIKE', $conditions[0][2]);
        $this->assertEquals('title', $conditions[0][1]);

        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();
        $builder = $builder->whereLike('title', 'test', false);
        $conditions = $this->getBuilderConditions($builder);
        $this->assertEquals('LIKE', $conditions[0][2]);
        $this->assertEquals('LOWER(title)', $conditions[0][1]);

        $this->setBuilderDriver('sqlite');
        $builder = $this->createBuilder();
        $builder = $builder->whereLike('title', 'test', false);
        $conditions = $this->getBuilderConditions($builder);
        $this->assertEquals('LIKE', $conditions[0][2]);
        $this->assertEquals('LOWER(title)', $conditions[0][1]);
    }

    public function testPrepareLikeValueAddsWildcards()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $builder = $builder->whereLike('name', 'john');
        $conditions = $this->getBuilderConditions($builder);
        $this->assertEquals('%john%', $conditions[0][3]);

        $builder2 = $this->createBuilder();
        $builder2 = $builder2->whereLike('name', 'john%');
        $conditions2 = $this->getBuilderConditions($builder2);
        $this->assertEquals('john%', $conditions2[0][3]);

        $builder3 = $this->createBuilder();
        $builder3 = $builder3->whereLike('name', '%john%');
        $conditions3 = $this->getBuilderConditions($builder3);
        $this->assertEquals('%john%', $conditions3[0][3]);
    }

    public function testWhereLikeWithCustomWildcards()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $builder = $builder->whereLike('name', 'john%');
        $conditions = $this->getBuilderConditions($builder);
        $this->assertEquals('john%', $conditions[0][3]);

        $builder2 = $this->createBuilder();
        $builder2 = $builder2->whereLike('name', '%john');
        $conditions2 = $this->getBuilderConditions($builder2);
        $this->assertEquals('%john', $conditions2[0][3]);

        $builder3 = $this->createBuilder();
        $builder3 = $builder3->whereLike('name', 'j_hn');
        $conditions3 = $this->getBuilderConditions($builder3);
        $this->assertEquals('j_hn', $conditions3[0][3]);
    }

    public function testWhereLikePostgreSQLCaseSensitive()
    {
        $this->setBuilderDriver('pgsql');
        $builder = $this->createBuilder();

        $builder = $builder->whereLike('username', 'Admin', true);
        $conditions = $this->getBuilderConditions($builder);

        $this->assertEquals('username', $conditions[0][1]);
        $this->assertEquals('LIKE', $conditions[0][2]);
        $this->assertEquals('%Admin%', $conditions[0][3]);
    }

    public function testOrWhereLikePostgreSQLCaseInsensitive()
    {
        $this->setBuilderDriver('pgsql');
        $builder = $this->createBuilder();

        $builder = $builder->where('status', 'active')
            ->orWhereLike('name', 'john', false);
        $conditions = $this->getBuilderConditions($builder);

        $this->assertEquals('OR', $conditions[1][0]);
        $this->assertEquals('name', $conditions[1][1]);
        $this->assertEquals('ILIKE', $conditions[1][2]);
        $this->assertEquals('%john%', $conditions[1][3]);
    }

    public function testWhereLikeSQLiteCaseSensitiveSingleCharWildcard()
    {
        $this->setBuilderDriver('sqlite');
        $builder = $this->createBuilder();

        $builder = $builder->whereLike('username', 'A_min_', true);
        $conditions = $this->getBuilderConditions($builder);

        $this->assertEquals('username', $conditions[0][1]);
        $this->assertEquals('GLOB', $conditions[0][2]);
        $this->assertEquals('A?min?', $conditions[0][3]);
    }

    public function testSearchBuildsNestedOrWhereLikeConditions()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $builder = $builder->search(['name', 'username'], 'john', false);

        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('conditions');
        $property->setAccessible(true);
        $conditions = $property->getValue($builder);
        $property->setAccessible(false);

        $this->assertCount(1, $conditions);
        $this->assertEquals('NESTED', $conditions[0]['type']);
        $nested = $conditions[0]['query'];

        $nestedReflection = new \ReflectionClass($nested);
        $nestedProp = $nestedReflection->getProperty('conditions');
        $nestedProp->setAccessible(true);
        $nestedConds = $nestedProp->getValue($nested);
        $nestedProp->setAccessible(false);

        $this->assertGreaterThanOrEqual(2, count($nestedConds));
        $this->assertEquals('OR', $nestedConds[0][0]);
        $this->assertEquals('LOWER(name)', $nestedConds[0][1]);
        $this->assertEquals('LIKE', $nestedConds[0][2]);
        $this->assertEquals('OR', $nestedConds[1][0]);
        $this->assertEquals('LOWER(username)', $nestedConds[1][1]);
        $this->assertEquals('LIKE', $nestedConds[1][2]);
    }

    public function testToSqlWithWhereLikeMySQL()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $sql = $builder->whereLike('name', 'John Doe', false)->toSql();

        $this->assertStringStartsWith('SELECT * FROM users WHERE', $sql);
        $this->assertStringContainsString('LOWER(name) LIKE ?', $sql);
        $this->assertStringNotContainsString('ILIKE', $sql);
        $this->assertStringNotContainsString('GLOB', $sql);
    }

    public function testCaseSensitiveColumnDetection()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('isCaseSensitiveColumn');
        $method->setAccessible(true);

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

        $result = $method->invoke($builder, '%test%');
        $this->assertEquals('*test*', $result);

        $result2 = $method->invoke($builder, 'test_');
        $this->assertEquals('test?', $result2);

        $result3 = $method->invoke($builder, '%test_');
        $this->assertEquals('*test?', $result3);
    }
}
