<?php

namespace Tests\Unit\Model;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Hooks\HookHandler;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Phaseolies\Cache\CacheStore;
use PHPUnit\Framework\TestCase;
use PDO;

class EntityModelHookTest extends TestCase
{
    private CacheStore $cache;
    private ArrayAdapter $adapter;
    private $pdo;

    protected function setUp(): void
    {
        $this->adapter = new ArrayAdapter();
        $this->cache = new CacheStore($this->adapter, 'hook_test_');

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTestTables();
        $this->setupDatabaseConnections();
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->tearDownDatabaseConnections();
    }

    private function createTestTables(): void
    {
        // Create hooks table
        $this->pdo->exec("
            CREATE TABLE hooks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )
        ");

        $this->pdo->exec("
            INSERT INTO users (name) VALUES
            ('John Doe'),
            ('Jane Smith'),
            ('Bob Wilson')
        ");
    }

    private function setupDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);

        $this->setStaticProperty(Database::class, 'connections', [
            'default' => $this->pdo,
            'sqlite' => $this->pdo
        ]);
    }

    private function tearDownDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

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
}
