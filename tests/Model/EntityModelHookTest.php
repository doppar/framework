<?php

namespace Tests\Unit\Model;

use Phaseolies\Database\Database;
use PHPUnit\Framework\TestCase;
use PDO;
use Tests\Support\Model\MockHook;

class EntityModelHookTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
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
            INSERT INTO hooks (name) VALUES
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

    public function testModelBootingHook(): void
    {
        MockHook::$wasCalledBeforeBooting = false;

        // Creating a new record should trigger the [booting] hook
        MockHook::create(['name' => 'Hook Test']);

        $this->assertTrue(MockHook::$wasCalledBeforeBooting, 'booting hook should have fired');
    }

    public function testAfterCreatedHook(): void
    {
        MockHook::$wasCalledAfterCreated = false;

        // Creating a new record should trigger the [after_created] hook
        MockHook::create(['name' => 'Hook Test']);

        $this->assertTrue(MockHook::$wasCalledAfterCreated, 'after_created hook should have fired');
    }

    public function testAfterUpdatedHook(): void
    {
        MockHook::$wasCalledAfterUpdated = false;

        // Updating a new record should trigger the [after_updated] hook
        $hook = MockHook::find(1);
        $hook->name = "Updated Hook";
        $hook->save();

        $this->assertTrue(MockHook::$wasCalledAfterUpdated, 'after_updated hook should have fired');
    }

    public function testAfterDeletedHook(): void
    {
        MockHook::$wasCalledAfterDeleted = false;

        // Deleting a new record should trigger the [after_deleted] hook
        MockHook::find(2)->delete();

        // But intentionally we set handler when as false,
        // So this hook [after_deleted] will not trigger
        // So we will get false value for this MockHook::$wasCalledAfterDeleted
        $this->assertFalse(MockHook::$wasCalledAfterDeleted, 'after_deleted hook should have fired');
    }

    public function testAfterCreateWithoutdHook(): void
    {
        MockHook::$wasCalledAfterCreated = false;

        // Disable hooks for this operation
        MockHook::withoutHook()->create(['name' => 'Hook Test']);

        $this->assertFalse(MockHook::$wasCalledAfterCreated, 'after_created hook should have fired');
    }

    public function testAfterUpdatedWithoutHook(): void
    {
        MockHook::$wasCalledAfterUpdated = false;

        $hook = MockHook::find(1);
        // Disable hooks for this operation
        $hook->withoutHook();
        $hook->name = "Updated Hook";
        $hook->save();

        $this->assertFalse(MockHook::$wasCalledAfterUpdated, 'after_updated hook should have fired');
    }
}
