<?php

namespace Tests\Unit\Builder;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;

class DatabaseBuilderUpsertGroupTest extends TestCase
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

    public function testGroupByAutoAggregatesNonGroupedFields()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $sql = $builder->select('id', 'name', 'COUNT(id) AS c')
            ->groupBy('id')
            ->toSql();

        $this->assertStringStartsWith('SELECT ', $sql);
        $this->assertStringContainsString('id', $sql);
        $this->assertStringContainsString('MAX(name) AS name', $sql);
        $this->assertStringContainsString('COUNT(id) AS c', $sql);
        $this->assertStringContainsString(' GROUP BY id', $sql);
    }

    public function testGetUpsertSqlMySQL()
    {
        $this->setBuilderDriver('mysql');
        $builder = $this->createBuilder();

        $columnsStr = '`id`, `name`';
        $placeholders = ['(?, ?)'];
        $updateStatements = ['`name` = VALUES(`name`)'];
        $uniqueBy = ['id'];
        $updateColumns = [];

        $sql = $builder->getUpsertSql($columnsStr, $placeholders, $updateStatements, $uniqueBy, $updateColumns, false);
        $this->assertStringStartsWith('INSERT INTO `users` (`id`, `name`) VALUES (?, ?)', $sql);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)', $sql);
    }

    public function testGetUpsertSqlPostgreSQL()
    {
        $this->setBuilderDriver('pgsql');
        $builder = $this->createBuilder();

        $columnsStr = '"id", "name"';
        $placeholders = ['(?, ?)'];
        $updateStatements = [];
        $uniqueBy = ['id'];
        $updateColumns = ['name'];

        $sql = $builder->getUpsertSql($columnsStr, $placeholders, $updateStatements, $uniqueBy, $updateColumns, false);
        $this->assertStringStartsWith('INSERT INTO "users" ("id", "name") VALUES (?, ?)', $sql);
        $this->assertStringContainsString('ON CONFLICT("id") DO UPDATE SET "name" = EXCLUDED."name"', $sql);
    }

    public function testGetUpsertSqlSQLite()
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('sqlite');

        $versionStmt = $this->createMock(\PDOStatement::class);
        $versionStmt->method('fetchColumn')->willReturn('3.39.0');

        $this->pdoMock->method('query')
            ->with($this->equalTo('SELECT sqlite_version()'))
            ->willReturn($versionStmt);

        $builder = $this->createBuilder();

        $columnsStr = '`id`, `name`';
        $placeholders = ['(?, ?)'];
        $updateStatements = [];
        $uniqueBy = ['id'];
        $updateColumns = ['name'];

        $sql = $builder->getUpsertSql($columnsStr, $placeholders, $updateStatements, $uniqueBy, $updateColumns, false);
        $this->assertStringStartsWith('INSERT INTO `users` (`id`, `name`) VALUES (?, ?)', $sql);
        $this->assertStringContainsString('ON CONFLICT(`id`) DO UPDATE SET `name` = EXCLUDED.`name`', $sql);
    }
}
