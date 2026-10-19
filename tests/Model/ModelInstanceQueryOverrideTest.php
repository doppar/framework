<?php

namespace Tests\Unit\Model;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Phaseolies\Support\StringService;
use Tests\Support\MockContainer;
use Tests\Support\Model\MockScopedUser;

class ModelInstanceQueryOverrideTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        $container = new MockContainer();
        $container->singleton('str', StringService::class);
        Container::setInstance($container);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE scoped_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, role TEXT)');
        $this->pdo->exec("CREATE TABLE other_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, role TEXT)");

        foreach (['a' => 'admin', 'b' => 'user', 'c' => 'admin', 'd' => 'user'] as $name => $role) {
            $this->pdo->exec("INSERT INTO scoped_users (name, role) VALUES ('$name', '$role')");
        }
        $this->pdo->exec("INSERT INTO other_users (name, role) VALUES ('x', 'admin'), ('y', 'admin'), ('z', 'admin')");

        $this->setStatic('connections', ['default' => $this->pdo, 'sqlite' => $this->pdo]);
        $this->setStatic('transactions', []);
    }

    protected function tearDown(): void
    {
        $this->setStatic('connections', []);
        $this->setStatic('transactions', []);
        $this->pdo = null;
    }

    /** Baseline: the static path applies the override */
    public function testStaticCallsApplyQueryOverride(): void
    {
        $this->assertSame(['c', 'd'], MockScopedUser::all()->pluck('name')->toArray());
        $this->assertSame('c', MockScopedUser::first()->name);
    }

    /** The reported bug: first() on an instance must honor the override */
    public function testInstanceFirstAppliesQueryOverride(): void
    {
        $this->assertSame('c', (new MockScopedUser())->first()->name);
    }

    /** Instance chain methods start from the overridden query */
    public function testInstanceBuilderMethodsApplyQueryOverride(): void
    {
        $names = (new MockScopedUser())->orderBy('id')->get()->pluck('name')->toArray();

        $this->assertSame(['c', 'd'], $names);
    }

    /** Instance and static calls must return identical results */
    public function testInstanceAndStaticCallsAreEquivalent(): void
    {
        $this->assertSame(
            MockScopedUser::where('role', 'user')->get()->pluck('name')->toArray(),
            (new MockScopedUser())->where('role', 'user')->get()->pluck('name')->toArray()
        );
        $this->assertSame(MockScopedUser::count(), (new MockScopedUser())->count());
    }

    /** Instance calls cannot reach rows the override hides */
    public function testInstanceCallCannotSeeHiddenRows(): void
    {
        $this->assertNull((new MockScopedUser())->where('name', 'a')->first());
    }

    /** Bind methods (__admin) still resolve on instance calls, combined with the override */
    public function testBindMethodResolvesOnInstanceAndKeepsOverride(): void
    {
        $names = (new MockScopedUser())->admin()->get()->pluck('name')->toArray();

        $this->assertSame(['c'], $names);
        $this->assertSame(['c'], MockScopedUser::admin()->get()->pluck('name')->toArray());
    }

    /** Bind methods work in a chain after an instance call */
    public function testBindMethodChainedAfterInstanceCall(): void
    {
        $result = (new MockScopedUser())->where('name', 'c')->admin('admin')->first();

        $this->assertSame('c', $result->name);
    }

    /** Internal writes use newQuery() and must NOT be scoped by the override */
    public function testInstanceWritesAreNotAffectedByOverride(): void
    {
        // newQuery() bypasses the override, so the hidden row can be loaded
        $model = (new MockScopedUser())->newQuery()->where('id', 1)->first();
        $this->assertNotNull($model);

        $model->name = 'renamed';
        $model->save();

        $this->assertSame(
            'renamed',
            $this->pdo->query("SELECT name FROM scoped_users WHERE id = 1")->fetchColumn()
        );
    }

    /** Unknown methods still throw instead of being swallowed */
    public function testUnknownMethodStillThrows(): void
    {
        $this->expectException(\BadMethodCallException::class);

        (new MockScopedUser())->definitelyNotAMethod();
    }

    /** withoutHook() is still intercepted and returns the instance */
    public function testWithoutHookReturnsInstance(): void
    {
        $model = new MockScopedUser();

        $this->assertSame($model, $model->withoutHook());
    }

    /**
     * Documented trade-off: a runtime setTable() on the instance is ignored by
     * dynamic calls because query() builds a fresh model. Locks the behavior in.
     */
    public function testRuntimeSetTableIsIgnoredByDynamicCalls(): void
    {
        $model = new MockScopedUser();
        $model->setTable('other_users');

        // Queries scoped_users (ids 3, 4), not other_users (ids 1-3)
        $this->assertSame(['c', 'd'], $model->orderBy('id')->get()->pluck('name')->toArray());
    }

    private function setStatic(string $property, mixed $value): void
    {
        (new \ReflectionClass(Database::class))->getProperty($property)->setValue(null, $value);
    }
}
