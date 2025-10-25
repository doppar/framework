<?php

namespace Tests\Unit\Builder;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;

class DatabaseBuilderJoinsFromOmitTest extends TestCase
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

    public function testJoinClausesInToSql()
    {
        $builder = $this->createBuilder();

        $sql = $builder
            ->select('users.id', 'profiles.bio')
            ->join('profiles', 'profiles.user_id', '=', 'users.id', 'left')
            ->join('countries', 'countries.id', '=', 'users.country_id', 'inner')
            ->toSql();

        $this->assertStringContainsString('LEFT JOIN profiles ON profiles.user_id = users.id', $sql);
        $this->assertStringContainsString('INNER JOIN countries ON countries.id = users.country_id', $sql);
    }

    public function testFromChangesBaseTable()
    {
        $builder = $this->createBuilder();

        $sql = $builder->from('admins')->select('id')->toSql();
        $this->assertStringStartsWith('SELECT id FROM admins', $sql);
    }

    public function testOmitExcludesColumnsFromSelect()
    {
        // DESCRIBE users -> columns id,name,email
        $describeStmt = $this->createMock(PDOStatement::class);
        $describeStmt->method('fetchAll')->with($this->anything())->willReturn([
            ['Field' => 'id'],
            ['Field' => 'name'],
            ['Field' => 'email'],
        ]);

        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $this->pdoMock->method('query')->willReturn($describeStmt);

        $builder = $this->createBuilder();

        $sql = $builder->omit('email')->toSql();

        $this->assertStringStartsWith('SELECT ', $sql);
        $this->assertStringContainsString('id', $sql);
        $this->assertStringContainsString('name', $sql);
        $this->assertStringNotContainsString('email', $sql);
    }
}
