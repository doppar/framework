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

    /**
     * Hook table of MockHook as it was before a test added temporary hooks.
     * MockHook registers its own hooks once per process, so restoring must
     * put this back instead of removing the entry.
     */
    private ?array $savedHooks = null;

    private function tempHooks(array $hooks): void
    {
        new MockHook(); // make sure the class has booted and registered its own hooks

        $this->savedHooks ??= HookHandler::$hooks[MockHook::class] ?? [];

        HookHandler::register(MockHook::class, $hooks);
    }

    private function restoreHooks(): void
    {
        if ($this->savedHooks !== null) {
            HookHandler::$hooks[MockHook::class] = $this->savedHooks;
            $this->savedHooks = null;
        }
    }

    protected function tearDown(): void
    {
        $this->restoreHooks();

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
        $this->tempHooks([
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
            $this->restoreHooks();
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
        $this->tempHooks([
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
            $this->restoreHooks();
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
        $this->tempHooks([
            'before_deleted' => function ($model) use (&$seen) {
                $seen[] = (int) $model->getKey();
            },
        ]);

        try {
            MockHook::query()->where('id', '<', 3)->withHooks()->delete();
        } finally {
            $this->restoreHooks();
        }

        $this->assertEqualsCanonicalizing([1, 2], $seen);
        $this->assertSame(1, MockHook::query()->count());
    }

    public function testBuilderDeleteWithoutWithHooksSkipsHooks(): void
    {
        $fired = false;
        $this->tempHooks([
            'before_deleted' => function () use (&$fired) {
                $fired = true;
            },
        ]);

        try {
            MockHook::query()->where('id', 1)->delete();
        } finally {
            $this->restoreHooks();
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

    /**
     * Register an after_updated spy on MockHook that records how many times it
     * ran and what getOriginal('name') was while it ran.
     *
     * @return \stdClass
     */
    private function spyAfterUpdated(): \stdClass
    {
        $spy = new \stdClass();
        $spy->calls = 0;
        $spy->originals = [];

        $this->tempHooks([
            'after_updated' => function ($model) use ($spy) {
                $spy->calls++;
                $spy->originals[] = $model->getOriginal('name');
            },
        ]);

        return $spy;
    }

    public function testQuietSaveLeavesTheModelClean(): void
    {
        $hook = MockHook::find(1);
        $hook->name = 'Quiet';
        $hook->withoutHook()->save();

        $this->assertSame([], $hook->getDirtyAttributes());
        $this->assertFalse($hook->isDirtyAttr('name'));
        $this->assertSame('Quiet', $hook->getOriginal('name'));
    }

    public function testQuietSaveStillPersistsTheChange(): void
    {
        $hook = MockHook::find(1);
        $hook->name = 'Quiet';

        $this->assertTrue($hook->withoutHook()->save());
        $this->assertSame('Quiet', MockHook::find(1)->name);
    }

    public function testQuietSaveDoesNotFireAfterUpdated(): void
    {
        $spy = $this->spyAfterUpdated();

        $hook = MockHook::find(1);
        $hook->name = 'Quiet';
        $hook->withoutHook()->save();

        $this->assertSame(0, $spy->calls);
    }

    public function testPlainSaveAfterQuietSaveDoesNotRefireTheHook(): void
    {
        $spy = $this->spyAfterUpdated();

        $hook = MockHook::find(1);
        $hook->name = 'Quiet';
        $hook->withoutHook()->save();

        $this->assertTrue($hook->save()); // nothing new changed

        $this->assertSame(0, $spy->calls, 'a change that is already persisted must not fire after_updated later');
    }

    public function testPlainSaveAfterQuietSaveDoesNotRewriteTheRow(): void
    {
        $hook = MockHook::find(1);
        $hook->name = 'Quiet';
        $hook->withoutHook()->save();

        // Change the row behind the model's back. A stale-dirty model would
        // overwrite it again on the next save().
        $this->pdo->exec("UPDATE hooks SET name = 'Changed elsewhere' WHERE id = 1");

        $hook->save();

        $this->assertSame('Changed elsewhere', MockHook::find(1)->name);
    }

    public function testChangeAfterQuietSaveIsTheOnlyDirtyAttribute(): void
    {
        $spy = $this->spyAfterUpdated();

        $hook = MockHook::find(1);
        $hook->name = 'Quiet';
        $hook->withoutHook()->save();

        $hook->name = 'Loud';
        $this->assertSame(['name' => 'Loud'], $hook->getDirtyAttributes());

        $hook->save();

        $this->assertSame(1, $spy->calls);
        $this->assertSame(['Quiet'], $spy->originals, 'the hook must see the quiet value as the original');
        $this->assertSame('Loud', MockHook::find(1)->name);
    }

    public function testQuietSaveAppliesToOneWriteOnly(): void
    {
        $spy = $this->spyAfterUpdated();

        $hook = MockHook::find(1);
        $hook->name = 'Quiet';
        $hook->withoutHook()->save();

        $hook->name = 'Loud';
        $hook->save();

        $hook->name = 'Louder';
        $hook->save();

        $this->assertSame(2, $spy->calls);
    }

    public function testRepeatedQuietSavesStayClean(): void
    {
        $hook = MockHook::find(1);

        foreach (['One', 'Two', 'Three'] as $name) {
            $hook->name = $name;
            $hook->withoutHook()->save();

            $this->assertSame([], $hook->getDirtyAttributes());
        }

        $this->assertSame('Three', MockHook::find(1)->name);
    }

    public function testQuietSaveWithNothingDirtyDoesNotLeakSuppressionIntoTheNextSave(): void
    {
        $spy = $this->spyAfterUpdated();

        $hook = MockHook::find(1);
        $hook->withoutHook()->save(); // nothing dirty, returns early

        $hook->name = 'Loud';
        $hook->save();

        $this->assertSame(1, $spy->calls);
    }

    public function testNormalSaveStillExposesTheOldValueInsideAfterUpdated(): void
    {
        $spy = $this->spyAfterUpdated();

        $hook = MockHook::find(1);
        $hook->name = 'Loud';
        $hook->save();

        $this->assertSame(1, $spy->calls);
        $this->assertSame(['John Doe'], $spy->originals, 'originals must only be synced after the hook has run');
        $this->assertSame([], $hook->getDirtyAttributes());
        $this->assertSame('Loud', $hook->getOriginal('name'));
    }

    public function testNormalSaveFollowedByAnotherSaveDoesNotRefireTheHook(): void
    {
        $spy = $this->spyAfterUpdated();

        $hook = MockHook::find(1);
        $hook->name = 'Loud';
        $hook->save();
        $hook->save();

        $this->assertSame(1, $spy->calls);
    }

    public function testQuietSaveOnOneInstanceDoesNotAffectAnotherInstanceOfTheSameRow(): void
    {
        $spy = $this->spyAfterUpdated();

        $quiet = MockHook::find(1);
        $other = MockHook::find(1);

        $quiet->name = 'Quiet';
        $quiet->withoutHook()->save();

        $other->name = 'Loud';
        $other->save();

        $this->assertSame(1, $spy->calls);
        $this->assertSame([], $quiet->getDirtyAttributes());
        $this->assertSame([], $other->getDirtyAttributes());
    }

    public function testQuietUpdateAndQuietSaveBehaveTheSame(): void
    {
        $viaSave = MockHook::find(1);
        $viaSave->name = 'Same';
        $viaSave->withoutHook()->save();

        $viaUpdate = MockHook::find(2);
        $viaUpdate->withoutHook()->update(['name' => 'Same']);

        $this->assertSame($viaUpdate->getDirtyAttributes(), $viaSave->getDirtyAttributes());
        $this->assertSame($viaUpdate->getOriginal('name'), $viaSave->getOriginal('name'));
    }

    /**
     * Register an after_created spy on MockHook that records what the model
     * looked like from inside the hook.
     *
     * @return \stdClass
     */
    private function spyAfterCreated(): \stdClass
    {
        $spy = new \stdClass();
        $spy->calls = 0;
        $spy->keys = [];
        $spy->names = [];

        $this->tempHooks([
            'after_created' => function ($model) use ($spy) {
                $spy->calls++;
                $spy->keys[] = $model->getKey();
                $spy->names[] = $model->name;
            },
        ]);

        return $spy;
    }

    public function testInsertViaSaveLeavesTheModelClean(): void
    {
        $hook = new MockHook();
        $hook->name = 'Fresh';
        $hook->save();

        $this->assertNotNull($hook->getKey());
        $this->assertSame([], $hook->getDirtyAttributes());
        $this->assertFalse($hook->isDirtyAttr('name'));
        $this->assertFalse($hook->isDirtyAttr('id'));
        $this->assertSame('Fresh', $hook->getOriginal('name'));
        $this->assertEquals($hook->getKey(), $hook->getOriginal('id'));
    }

    public function testCreateLeavesTheModelClean(): void
    {
        $hook = MockHook::create(['name' => 'Fresh']);

        $this->assertSame([], $hook->getDirtyAttributes());
        $this->assertSame('Fresh', $hook->getOriginal('name'));
    }

    public function testQuietCreateLeavesTheModelClean(): void
    {
        MockHook::$wasCalledAfterCreated = false;

        $hook = MockHook::withoutHook()->create(['name' => 'Fresh']);

        $this->assertFalse(MockHook::$wasCalledAfterCreated);
        $this->assertSame([], $hook->getDirtyAttributes());
        $this->assertSame('Fresh', $hook->getOriginal('name'));
    }

    public function testQuietInsertViaSaveLeavesTheModelClean(): void
    {
        $hook = new MockHook();
        $hook->name = 'Fresh';
        $hook->withoutHook()->save();

        $this->assertSame([], $hook->getDirtyAttributes());
    }

    public function testSaveRightAfterInsertDoesNotFireAfterUpdated(): void
    {
        $spy = $this->spyAfterUpdated();

        $hook = MockHook::create(['name' => 'Fresh']);

        $this->assertTrue($hook->save()); // nothing changed since the insert
        $this->assertSame(0, $spy->calls);
    }

    public function testSaveRightAfterInsertDoesNotRewriteTheRow(): void
    {
        $hook = MockHook::create(['name' => 'Fresh']);

        // A stale-dirty model would overwrite this on the next save().
        $this->pdo->exec("UPDATE hooks SET name = 'Changed elsewhere' WHERE id = " . (int) $hook->getKey());

        $hook->save();

        $this->assertSame('Changed elsewhere', MockHook::find($hook->getKey())->name);
    }

    public function testChangeAfterInsertIsTheOnlyDirtyAttribute(): void
    {
        $spy = $this->spyAfterUpdated();

        $hook = MockHook::create(['name' => 'Fresh']);
        $hook->name = 'Edited';

        $this->assertSame(['name' => 'Edited'], $hook->getDirtyAttributes());

        $hook->save();

        $this->assertSame(1, $spy->calls);
        $this->assertSame(['Fresh'], $spy->originals, 'the hook must see the inserted value as the original');
        $this->assertSame('Edited', MockHook::find($hook->getKey())->name);
    }

    public function testAfterCreatedStillSeesTheKeyAndAttributes(): void
    {
        $spy = $this->spyAfterCreated();

        $hook = MockHook::create(['name' => 'Fresh']);

        $this->assertSame(1, $spy->calls);
        $this->assertEquals($hook->getKey(), $spy->keys[0]);
        $this->assertSame('Fresh', $spy->names[0]);
    }

    public function testAfterCreatedFiresOnlyOnceAcrossLaterSaves(): void
    {
        $spy = $this->spyAfterCreated();

        $hook = MockHook::create(['name' => 'Fresh']);
        $hook->save();
        $hook->name = 'Edited';
        $hook->save();

        $this->assertSame(1, $spy->calls);
    }

    public function testFirstOrCreateReturnsACleanModelWhenItInserts(): void
    {
        $hook = MockHook::firstOrCreate(['name' => 'Brand New']);

        $this->assertSame([], $hook->getDirtyAttributes());
    }

    public function testUpdateOrCreateReturnsACleanModelWhenItInserts(): void
    {
        $hook = MockHook::updateOrCreate(['name' => 'Brand New'], []);

        $this->assertSame([], $hook->getDirtyAttributes());
    }

    public function testInsertedModelsDoNotShareStateWithEachOther(): void
    {
        $first = MockHook::create(['name' => 'First']);
        $second = MockHook::create(['name' => 'Second']);

        $second->name = 'Second edited';

        $this->assertSame([], $first->getDirtyAttributes());
        $this->assertSame(['name' => 'Second edited'], $second->getDirtyAttributes());
        $this->assertSame('First', $first->getOriginal('name'));
    }

    public function testInsertThenQuietSaveThenSaveStaysConsistent(): void
    {
        $spy = $this->spyAfterUpdated();

        $hook = MockHook::create(['name' => 'Fresh']);

        $hook->name = 'Quiet';
        $hook->withoutHook()->save();
        $this->assertSame([], $hook->getDirtyAttributes());

        $hook->name = 'Loud';
        $hook->save();

        $this->assertSame(1, $spy->calls);
        $this->assertSame(['Quiet'], $spy->originals);
        $this->assertSame('Loud', MockHook::find($hook->getKey())->name);
    }
}
