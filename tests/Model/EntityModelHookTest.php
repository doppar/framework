<?php

namespace Tests\Unit\Model;

use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Hooks\HookHandler;
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
            $property->setValue(null, $value);
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
        MockHook::$capturedKeyAtAfterCreated = null;

        // Creating a new record should trigger the [after_created] hook
        $created = MockHook::create(['name' => 'Hook Test']);

        $this->assertTrue(MockHook::$wasCalledAfterCreated, 'after_created hook should have fired');

        // The primary key must already be set on the model by the time
        // after_created hooks run, not just after they return.
        $this->assertNotNull(
            MockHook::$capturedKeyAtAfterCreated,
            'getKey() should be populated inside the after_created hook'
        );
        $this->assertSame((string) $created->getKey(), MockHook::$capturedKeyAtAfterCreated);
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

    public function testChainedWithoutHookUpdateOnlyTouchesThatRecord(): void
    {
        MockHook::$wasCalledAfterUpdated = false;

        $hook = MockHook::find(1);
        $hook->withoutHook()->update(['name' => 'Only Me']);

        $this->assertFalse(MockHook::$wasCalledAfterUpdated);
        $this->assertSame('Only Me', MockHook::find(1)->name);
        $this->assertSame('Jane Smith', MockHook::find(2)->name);
        $this->assertSame('Bob Wilson', MockHook::find(3)->name);
    }

    public function testChainedWithoutHookDeleteOnlyRemovesThatRecord(): void
    {
        MockHook::find(1)->withoutHook()->delete();

        $this->assertSame(2, MockHook::query()->count());
        $this->assertNull(MockHook::find(1));
    }

    public function testChainedWithoutHookSaveWorks(): void
    {
        MockHook::$wasCalledAfterUpdated = false;

        $hook = MockHook::find(1);
        $hook->name = 'Saved';
        $hook->withoutHook()->save();

        $this->assertFalse(MockHook::$wasCalledAfterUpdated);
        $this->assertSame('Saved', MockHook::find(1)->name);
    }

    public function testWithoutHookIsResetAfterOneWrite(): void
    {
        MockHook::$wasCalledAfterUpdated = false;

        $hook = MockHook::find(1);
        $hook->withoutHook()->update(['name' => 'Quiet']);
        $this->assertFalse(MockHook::$wasCalledAfterUpdated);

        $hook->name = 'Loud';
        $hook->save();
        $this->assertTrue(MockHook::$wasCalledAfterUpdated);
    }

    public function testWithoutHookIsResetWhenUpdateReturnsEarly(): void
    {
        MockHook::$wasCalledAfterUpdated = false;

        $hook = MockHook::find(1);
        $hook->withoutHook()->update([]); // nothing dirty, returns early

        $hook->name = 'Loud';
        $hook->save();

        $this->assertTrue(MockHook::$wasCalledAfterUpdated);
    }

    public function testWithoutHookDoesNotLeakToOtherModelOperations(): void
    {
        MockHook::$wasCalledAfterCreated = false;

        // A read through the builder must not switch hooks off globally
        MockHook::withoutHook()->where('id', 1)->get();

        MockHook::create(['name' => 'After Read']);

        $this->assertTrue(MockHook::$wasCalledAfterCreated);
    }

    public function testWithoutHookBuilderDoesNotLeakAfterBulkUpdate(): void
    {
        MockHook::$wasCalledAfterUpdated = false;

        MockHook::withoutHook()->where('id', 1)->update(['name' => 'Bulk']);

        $hook = MockHook::find(2);
        $hook->name = 'Normal';
        $hook->save();

        $this->assertTrue(MockHook::$wasCalledAfterUpdated);
    }

    public function testWithoutHookFindThenSaveSkipsHooksOnce(): void
    {
        MockHook::$wasCalledAfterUpdated = false;

        $hook = MockHook::withoutHook()->find(1);
        $hook->name = 'Imported';
        $hook->save();

        $this->assertFalse(MockHook::$wasCalledAfterUpdated);
    }

    public function testWithoutHookUpdateOrCreateAndFirstOrCreate(): void
    {
        MockHook::$wasCalledAfterCreated = false;
        MockHook::$wasCalledAfterUpdated = false;

        MockHook::withoutHook()->updateOrCreate(['name' => 'John Doe'], ['name' => 'John Doe']);
        MockHook::withoutHook()->updateOrCreate(['name' => 'Brand New']);
        MockHook::withoutHook()->firstOrCreate(['name' => 'Another New']);

        $this->assertFalse(MockHook::$wasCalledAfterCreated);
        $this->assertFalse(MockHook::$wasCalledAfterUpdated);
        $this->assertSame(5, MockHook::query()->count());
    }

    public function testBuilderUpdateWithoutWithHooksSkipsHooks(): void
    {
        MockHook::$wasCalledAfterUpdated = false;

        MockHook::query()->where('id', '>', 0)->update(['name' => 'Bulk']);

        $this->assertFalse(MockHook::$wasCalledAfterUpdated);
        $this->assertSame('Bulk', MockHook::find(3)->name);
    }

    public function testBuilderUpdateWithHooksFiresHooksPerModel(): void
    {
        $seen = [];
        HookHandler::register(MockHook::class, [
            'before_updated' => function ($model) use (&$seen) {
                $seen[] = $model->getKey();
            },
        ]);
        MockHook::$wasCalledAfterUpdated = false;

        try {
            $result = MockHook::query()
                ->where('id', '>', 1)
                ->withHooks()
                ->update(['name' => 'Hooked']);
        } finally {
            unset(HookHandler::$hooks[MockHook::class]);
        }

        $this->assertTrue($result);
        $this->assertTrue(MockHook::$wasCalledAfterUpdated);
        $this->assertEqualsCanonicalizing([2, 3], array_map('intval', $seen));
        $this->assertSame('John Doe', MockHook::find(1)->name);
        $this->assertSame('Hooked', MockHook::find(2)->name);
        $this->assertSame('Hooked', MockHook::find(3)->name);
    }

    public function testBuilderUpdateWithHooksReachesRowsThatLeaveTheWhereClause(): void
    {
        // The update changes the column the WHERE uses, so rows drop out of
        // the result set while it is being processed. None may be skipped.
        for ($i = 0; $i < 1200; $i++) {
            $this->pdo->exec("INSERT INTO hooks (name) VALUES ('pending')");
        }

        MockHook::query()->where('name', 'pending')->withHooks()->update(['name' => 'done']);

        $this->assertSame(0, MockHook::query()->where('name', 'pending')->count());
        $this->assertSame(1200, MockHook::query()->where('name', 'done')->count());
    }

    public function testBuilderUpdateWithHooksStopsWhereAHookThrows(): void
    {
        HookHandler::register(MockHook::class, [
            'before_updated' => function ($model) {
                if ((int) $model->getKey() === 2) {
                    throw new \RuntimeException('not this one');
                }
            },
        ]);

        try {
            MockHook::query()->withHooks()->update(['name' => 'Changed']);
            $this->fail('Expected the hook exception.');
        } catch (\RuntimeException $e) {
            $this->assertSame('not this one', $e->getMessage());
        } finally {
            unset(HookHandler::$hooks[MockHook::class]);
        }

        // Rows are written one by one and there is no implicit transaction:
        // row 1 was saved, row 2 threw, row 3 was never reached.
        $this->assertSame('Changed', MockHook::find(1)->name);
        $this->assertSame('Jane Smith', MockHook::find(2)->name);
        $this->assertSame('Bob Wilson', MockHook::find(3)->name);
    }

    public function testBuilderDeleteWithHooksFiresHooksPerModel(): void
    {
        $seen = [];
        HookHandler::register(MockHook::class, [
            'before_deleted' => function ($model) use (&$seen) {
                $seen[] = (int) $model->getKey();
            },
        ]);

        try {
            MockHook::query()->where('id', '<', 3)->withHooks()->delete();
        } finally {
            unset(HookHandler::$hooks[MockHook::class]);
        }

        $this->assertEqualsCanonicalizing([1, 2], $seen);
        $this->assertSame(1, MockHook::query()->count());
    }

    public function testBuilderDeleteWithoutWithHooksSkipsHooks(): void
    {
        $fired = false;
        HookHandler::register(MockHook::class, [
            'before_deleted' => function () use (&$fired) {
                $fired = true;
            },
        ]);

        try {
            MockHook::query()->where('id', 1)->delete();
        } finally {
            unset(HookHandler::$hooks[MockHook::class]);
        }

        $this->assertFalse($fired);
        $this->assertSame(2, MockHook::query()->count());
    }

    public function testWithHooksAndWithoutHookTogetherSkipHooks(): void
    {
        MockHook::$wasCalledAfterUpdated = false;

        MockHook::withoutHook()->withHooks()->update(['name' => 'Quiet']);

        $this->assertFalse(MockHook::$wasCalledAfterUpdated);
        $this->assertSame('Quiet', MockHook::find(1)->name);
        $this->assertSame('Quiet', MockHook::find(3)->name);
    }
}
