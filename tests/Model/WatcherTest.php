<?php

namespace Tests\Unit\Model;

use PDO;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Attributes\Watches;
use Phaseolies\Database\Entity\Hooks\HookHandler;
use Phaseolies\Database\Entity\Watches\WatchesHandler;
use Phaseolies\Database\Entity\Watches\WatchConditionInterface;
use Phaseolies\DI\Container;
use Tests\Support\MockContainer;
use Tests\Support\Model\MockWatchableOrder;
use Tests\Support\Watches\AnotherSpyListener;
use Tests\Support\Watches\FailCondition;
use Tests\Support\Watches\NoHandleListener;
use Tests\Support\Watches\NotAConditionClass;
use Tests\Support\Watches\PassCondition;
use Tests\Support\Watches\SpyListener;
use Tests\Support\Watches\TrackingCondition;

class WatcherTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTestTables();
        $this->setupDatabaseConnections();

        WatchesHandler::flush();
        HookHandler::$hooks = [];
        Model::resetWatchesCache();
        Model::resetAttributeHookCache();

        SpyListener::reset();
        AnotherSpyListener::reset();
        TrackingCondition::reset();

        WatchesHandler::register(MockWatchableOrder::class, 'status', SpyListener::class);
        WatchesHandler::register(MockWatchableOrder::class, 'total', SpyListener::class, 'isFraudRisk');
        WatchesHandler::register(MockWatchableOrder::class, 'total', AnotherSpyListener::class);
        WatchesHandler::register(MockWatchableOrder::class, 'notes', SpyListener::class, FailCondition::class);
    }

    protected function tearDown(): void
    {
        WatchesHandler::flush();
        HookHandler::$hooks = [];
        Model::resetWatchesCache();
        Model::resetAttributeHookCache();

        SpyListener::reset();
        AnotherSpyListener::reset();
        TrackingCondition::reset();

        $this->pdo = null;
        $this->tearDownDatabaseConnections();
    }

    /** Attribute stores the watcher class string correctly */
    public function testWatchesAttributeStoresListenerClass(): void
    {
        $ref      = new \ReflectionClass(MockWatchableOrder::class);
        $property = $ref->getProperty('status');
        $attrs    = $property->getAttributes(Watches::class);

        $this->assertCount(1, $attrs);

        /** @var Watches $watches */
        $watches = $attrs[0]->newInstance();

        $this->assertSame(SpyListener::class, $watches->watcher);
    }

    /** When 'when' is omitted the attribute stores null */
    public function testWatchesAttributeDefaultsWhenToNull(): void
    {
        $ref      = new \ReflectionClass(MockWatchableOrder::class);
        $property = $ref->getProperty('status');

        /** @var Watches $watches */
        $watches = $property->getAttributes(Watches::class)[0]->newInstance();

        $this->assertNull($watches->when);
    }

    /** Attribute stores the method-name condition string */
    public function testWatchesAttributeStoresWhenMethodName(): void
    {
        $ref      = new \ReflectionClass(MockWatchableOrder::class);
        $property = $ref->getProperty('total');

        // First attribute on $total has when: 'isFraudRisk'
        /** @var Watches $watches */
        $watches = $property->getAttributes(Watches::class)[0]->newInstance();

        $this->assertSame('isFraudRisk', $watches->when);
    }

    /** Attribute stores the condition-class string when passed as when */
    public function testWatchesAttributeStoresWhenConditionClass(): void
    {
        $ref      = new \ReflectionClass(MockWatchableOrder::class);
        $property = $ref->getProperty('notes');

        /** @var Watches $watches */
        $watches = $property->getAttributes(Watches::class)[0]->newInstance();

        $this->assertSame(FailCondition::class, $watches->when);
    }

    /** #[Watches] is repeatable — multiple instances on the same property */
    public function testWatchesAttributeIsRepeatableOnSameProperty(): void
    {
        $ref      = new \ReflectionClass(MockWatchableOrder::class);
        $property = $ref->getProperty('total');
        $attrs    = $property->getAttributes(Watches::class);

        // $total has two #[Watches] declarations
        $this->assertCount(2, $attrs);
    }

    /** watcher is a readonly property — cannot be mutated after construction */
    public function testWatchesAttributeListenerPropertyIsReadonly(): void
    {
        $ref      = new \ReflectionClass(Watches::class);
        $property = $ref->getProperty('watcher');

        $this->assertTrue($property->isReadOnly());
    }

    /** when is a readonly property — cannot be mutated after construction */
    public function testWatchesAttributeWhenPropertyIsReadonly(): void
    {
        $ref      = new \ReflectionClass(Watches::class);
        $property = $ref->getProperty('when');

        $this->assertTrue($property->isReadOnly());
    }

    /** Registering a watch adds it to the static registry */
    public function testRegisterAddsWatchEntry(): void
    {
        WatchesHandler::flush(); // clean slate beyond setUp watches

        WatchesHandler::register('Fake\Model', 'email', SpyListener::class);

        $this->assertArrayHasKey('Fake\Model', WatchesHandler::$watches);
        $this->assertArrayHasKey('email', WatchesHandler::$watches['Fake\Model']);
        $this->assertCount(1, WatchesHandler::$watches['Fake\Model']['email']);
    }

    /** The registered entry stores the watcher and when correctly */
    public function testRegisterStoresListenerAndWhen(): void
    {
        WatchesHandler::flush();

        WatchesHandler::register('Fake\Model', 'status', SpyListener::class, 'myCondition');

        $entry = WatchesHandler::$watches['Fake\Model']['status'][0];

        $this->assertSame(SpyListener::class, $entry['watcher']);
        $this->assertSame('myCondition', $entry['when']);
    }

    /** When 'when' is omitted the entry stores null */
    public function testRegisterStoresNullWhenOmitted(): void
    {
        WatchesHandler::flush();

        WatchesHandler::register('Fake\Model', 'status', SpyListener::class);

        $entry = WatchesHandler::$watches['Fake\Model']['status'][0];

        $this->assertNull($entry['when']);
    }

    /** Registering the same watcher+when combination twice is a no-op */
    public function testRegisterPreventsDuplicateEntries(): void
    {
        WatchesHandler::flush();

        WatchesHandler::register('Fake\Model', 'status', SpyListener::class);
        WatchesHandler::register('Fake\Model', 'status', SpyListener::class); // duplicate

        $this->assertCount(1, WatchesHandler::$watches['Fake\Model']['status']);
    }

    /** Different watchers on the same property are each registered */
    public function testRegisterAllowsDifferentListenersOnSameProperty(): void
    {
        WatchesHandler::flush();

        WatchesHandler::register('Fake\Model', 'status', SpyListener::class);
        WatchesHandler::register('Fake\Model', 'status', AnotherSpyListener::class);

        $this->assertCount(2, WatchesHandler::$watches['Fake\Model']['status']);
    }

    /** Same watcher with different 'when' values counts as distinct entries */
    public function testRegisterAllowsDifferentWhenOnSameListener(): void
    {
        WatchesHandler::flush();

        WatchesHandler::register('Fake\Model', 'total', SpyListener::class, 'conditionA');
        WatchesHandler::register('Fake\Model', 'total', SpyListener::class, 'conditionB');

        $this->assertCount(2, WatchesHandler::$watches['Fake\Model']['total']);
    }

    /** flush(ClassName) removes only that model's watches */
    public function testFlushClearsWatchesForSpecificModel(): void
    {
        WatchesHandler::register('Model\A', 'name', SpyListener::class);
        WatchesHandler::register('Model\B', 'name', SpyListener::class);

        WatchesHandler::flush('Model\A');

        $this->assertArrayNotHasKey('Model\A', WatchesHandler::$watches);
        $this->assertArrayHasKey('Model\B', WatchesHandler::$watches);
    }

    /** flush() with no argument empties the entire registry */
    public function testFlushWithNoArgumentClearsAllWatches(): void
    {
        WatchesHandler::register('Model\A', 'name', SpyListener::class);
        WatchesHandler::register('Model\B', 'name', SpyListener::class);

        WatchesHandler::flush();

        $this->assertEmpty(WatchesHandler::$watches);
    }

    /** flush() on a non-existent model does not throw */
    public function testFlushNonExistentModelDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();

        WatchesHandler::flush('Model\DoesNotExist');
    }

    private function blankModel(): Model
    {
        return new class extends Model {
            protected $table = 'stub';
        };
    }

    /** fireForDirty invokes the registered watcher when the property is dirty */
    public function testFireForDirtyCallsListenerForDirtyProperty(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        WatchesHandler::register(get_class($model), 'status', SpyListener::class);

        $model->setOriginalAttributes(['status' => 'pending']);

        WatchesHandler::fireForDirty($model, ['status' => 'shipped']);

        $this->assertTrue(SpyListener::$called);
    }

    /** The old value comes from getOriginalAttributes() */
    public function testFireForDirtyPassesOldValueFromOriginalAttributes(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['status' => 'pending']);

        WatchesHandler::register(get_class($model), 'status', SpyListener::class);
        WatchesHandler::fireForDirty($model, ['status' => 'shipped']);

        $this->assertSame('pending', SpyListener::$lastOld);
    }

    /** The new value comes from the dirty array passed to fireForDirty */
    public function testFireForDirtyPassesNewValueFromDirtyArray(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['status' => 'pending']);

        WatchesHandler::register(get_class($model), 'status', SpyListener::class);
        WatchesHandler::fireForDirty($model, ['status' => 'shipped']);

        $this->assertSame('shipped', SpyListener::$lastNew);
    }

    /** The model instance itself is forwarded to the watcher */
    public function testFireForDirtyPassesModelInstance(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['status' => 'pending']);

        WatchesHandler::register(get_class($model), 'status', SpyListener::class);
        WatchesHandler::fireForDirty($model, ['status' => 'shipped']);

        $this->assertSame($model, SpyListener::$lastModel);
    }

    /** Passing an empty dirty array fires nothing */
    public function testFireForDirtyDoesNothingWhenDirtyArrayIsEmpty(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        WatchesHandler::register(get_class($model), 'status', SpyListener::class);

        WatchesHandler::fireForDirty($model, []);

        $this->assertFalse(SpyListener::$called);
    }

    /** A dirty property with no registered watch causes no watcher call */
    public function testFireForDirtySkipsPropertyWithNoRegisteredWatch(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        // Register a watch on 'email', but make 'status' dirty — no match
        WatchesHandler::register(get_class($model), 'email', SpyListener::class);

        WatchesHandler::fireForDirty($model, ['status' => 'shipped']);

        $this->assertFalse(SpyListener::$called);
    }

    /** When nothing is registered for the model, fireForDirty is a no-op */
    public function testFireForDirtyDoesNothingWhenNoWatchesRegisteredForModel(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();

        WatchesHandler::fireForDirty($model, ['status' => 'shipped']);

        $this->assertFalse(SpyListener::$called);
    }

    private function modelWithMethodCondition(): Model
    {
        $class = 'MethodCondStub_' . spl_object_id($this);

        if (!class_exists($class)) {
            eval("
                use Phaseolies\\Database\\Entity\\Model;
                class {$class} extends Model {
                    protected \$table = 'stub';
                    public function isAboveThreshold(mixed \$old, mixed \$new): bool {
                        return (float) \$new > 100;
                    }
                    public function alwaysFalse(mixed \$old, mixed \$new): bool {
                        return false;
                    }
                    public function returnsNonBool(mixed \$old, mixed \$new): string {
                        return 'yes';
                    }
                }
            ");
        }

        return new $class();
    }

    /** Listener fires when the method condition returns true */
    public function testMethodConditionFiresListenerWhenReturnsTrue(): void
    {
        WatchesHandler::flush();

        $model = $this->modelWithMethodCondition();
        $model->setOriginalAttributes(['total' => '50']);

        WatchesHandler::register(get_class($model), 'total', SpyListener::class, 'isAboveThreshold');
        WatchesHandler::fireForDirty($model, ['total' => '200']);

        $this->assertTrue(SpyListener::$called);
    }

    /** Listener is skipped when the method condition returns false */
    public function testMethodConditionSkipsListenerWhenReturnsFalse(): void
    {
        WatchesHandler::flush();

        $model = $this->modelWithMethodCondition();
        $model->setOriginalAttributes(['total' => '50']);

        WatchesHandler::register(get_class($model), 'total', SpyListener::class, 'alwaysFalse');
        WatchesHandler::fireForDirty($model, ['total' => '200']);

        $this->assertFalse(SpyListener::$called);
    }

    /** The old value is forwarded to the method condition */
    public function testMethodConditionReceivesOldValue(): void
    {
        WatchesHandler::flush();

        $model = $this->modelWithMethodCondition();
        $model->setOriginalAttributes(['total' => '99']);

        // Use SpyListener only so we can verify condition ran (watcher fires = condition ran with true)
        WatchesHandler::register(get_class($model), 'total', SpyListener::class, 'isAboveThreshold');
        WatchesHandler::fireForDirty($model, ['total' => '200']);

        // isAboveThreshold receives $old=99, $new=200 → 200 > 100 → true → watcher fires
        $this->assertTrue(SpyListener::$called);
    }

    /** The new value is forwarded to the method condition */
    public function testMethodConditionReceivesNewValue(): void
    {
        WatchesHandler::flush();

        $model = $this->modelWithMethodCondition();
        $model->setOriginalAttributes(['total' => '999']);

        WatchesHandler::register(get_class($model), 'total', SpyListener::class, 'isAboveThreshold');
        // $new = 50 → 50 > 100 is false → watcher must NOT fire
        WatchesHandler::fireForDirty($model, ['total' => '50']);

        $this->assertFalse(SpyListener::$called);
    }

    /** Missing condition method throws RuntimeException */
    public function testMethodConditionThrowsForMissingMethod(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not exist on/');

        WatchesHandler::flush();

        $model = $this->modelWithMethodCondition();
        $model->setOriginalAttributes(['x' => '1']);

        WatchesHandler::register(get_class($model), 'x', SpyListener::class, 'nonExistentMethod');
        WatchesHandler::fireForDirty($model, ['x' => '2']);
    }

    /** Condition method returning a non-bool throws RuntimeException */
    public function testMethodConditionThrowsForNonBoolReturn(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must return bool/');

        WatchesHandler::flush();

        $model = $this->modelWithMethodCondition();
        $model->setOriginalAttributes(['x' => '1']);

        WatchesHandler::register(get_class($model), 'x', SpyListener::class, 'returnsNonBool');
        WatchesHandler::fireForDirty($model, ['x' => '2']);
    }


    /** Listener fires when the condition class evaluate() returns true */
    public function testClassConditionFiresListenerWhenEvaluateReturnsTrue(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['status' => 'a']);

        WatchesHandler::register(get_class($model), 'status', SpyListener::class, PassCondition::class);
        WatchesHandler::fireForDirty($model, ['status' => 'b']);

        $this->assertTrue(SpyListener::$called);
    }

    /** Listener is skipped when the condition class evaluate() returns false */
    public function testClassConditionSkipsListenerWhenEvaluateReturnsFalse(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['status' => 'a']);

        WatchesHandler::register(get_class($model), 'status', SpyListener::class, FailCondition::class);
        WatchesHandler::fireForDirty($model, ['status' => 'b']);

        $this->assertFalse(SpyListener::$called);
    }

    /** TrackingCondition receives the correct old value from originalAttributes */
    public function testClassConditionReceivesOldValue(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['price' => '100']);

        WatchesHandler::register(get_class($model), 'price', SpyListener::class, TrackingCondition::class);
        WatchesHandler::fireForDirty($model, ['price' => '200']);

        $this->assertSame('100', TrackingCondition::$lastOld);
    }

    /** TrackingCondition receives the correct new value from the dirty array */
    public function testClassConditionReceivesNewValue(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['price' => '100']);

        WatchesHandler::register(get_class($model), 'price', SpyListener::class, TrackingCondition::class);
        WatchesHandler::fireForDirty($model, ['price' => '999']);

        $this->assertSame('999', TrackingCondition::$lastNew);
    }

    /** TrackingCondition receives the model instance */
    public function testClassConditionReceivesModelInstance(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['price' => '100']);

        WatchesHandler::register(get_class($model), 'price', SpyListener::class, TrackingCondition::class);
        WatchesHandler::fireForDirty($model, ['price' => '200']);

        $this->assertSame($model, TrackingCondition::$lastModel);
    }

    /** A class that exists but does not implement WatchConditionInterface throws */
    public function testClassConditionThrowsForClassNotImplementingInterface(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must implement/');

        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['x' => '1']);

        WatchesHandler::register(get_class($model), 'x', SpyListener::class, NotAConditionClass::class);
        WatchesHandler::fireForDirty($model, ['x' => '2']);
    }


    /** All watches stacked on a property fire when it is dirty */
    public function testMultipleWatchesOnPropertyAllFire(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['total' => '50']);

        WatchesHandler::register(get_class($model), 'total', SpyListener::class);
        WatchesHandler::register(get_class($model), 'total', AnotherSpyListener::class);

        WatchesHandler::fireForDirty($model, ['total' => '200']);

        $this->assertTrue(SpyListener::$called);
        $this->assertTrue(AnotherSpyListener::$called);
    }

    /** A condition failing on one watch does not block subsequent watches */
    public function testMultipleWatchesConditionFailureDoesNotBlockOthers(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['total' => '50']);

        // First watch: FailCondition → will not fire SpyListener
        WatchesHandler::register(get_class($model), 'total', SpyListener::class, FailCondition::class);
        // Second watch: no condition → AnotherSpyListener always fires
        WatchesHandler::register(get_class($model), 'total', AnotherSpyListener::class);

        WatchesHandler::fireForDirty($model, ['total' => '200']);

        $this->assertFalse(SpyListener::$called,      'SpyListener should not fire (condition failed)');
        $this->assertTrue(AnotherSpyListener::$called, 'AnotherSpyListener should still fire');
    }

    /** Watches fire in the order they were registered */
    public function testMultipleWatchesFireInRegistrationOrder(): void
    {
        WatchesHandler::flush();

        $order  = [];
        $model  = $this->blankModel();
        $model->setOriginalAttributes(['x' => 'a']);

        WatchesHandler::register(get_class($model), 'x', SpyListener::class);
        WatchesHandler::register(get_class($model), 'x', AnotherSpyListener::class);

        // Override static tracking to record order
        SpyListener::reset();
        AnotherSpyListener::reset();

        WatchesHandler::fireForDirty($model, ['x' => 'b']);

        // Both fired — verify each fired exactly once
        $this->assertSame(1, SpyListener::$callCount);
        $this->assertSame(1, AnotherSpyListener::$callCount);
    }

    /** Each dirty watched property fires its own watches independently */
    public function testEachDirtyWatchedPropertyFiresItsOwnWatches(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['status' => 'a', 'email' => 'x@x.com']);

        WatchesHandler::register(get_class($model), 'status', SpyListener::class);
        WatchesHandler::register(get_class($model), 'email', AnotherSpyListener::class);

        WatchesHandler::fireForDirty($model, ['status' => 'b', 'email' => 'y@y.com']);

        $this->assertTrue(SpyListener::$called);
        $this->assertTrue(AnotherSpyListener::$called);
    }

    /** Only the watches for the actually dirty property are invoked */
    public function testUnchangedPropertyDoesNotFireItsWatch(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['status' => 'a', 'email' => 'x@x.com']);

        WatchesHandler::register(get_class($model), 'status', SpyListener::class);
        WatchesHandler::register(get_class($model), 'email', AnotherSpyListener::class);

        // Only email is in the dirty array
        WatchesHandler::fireForDirty($model, ['email' => 'y@y.com']);

        $this->assertFalse(SpyListener::$called,       'status watch must not fire');
        $this->assertTrue(AnotherSpyListener::$called,  'email watch must fire');
    }

    /** call count accumulates correctly across multiple dirty properties */
    public function testCallCountAccumulatesAcrossMultipleDirtyProperties(): void
    {
        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['a' => '1', 'b' => '2']);

        // SpyListener watches BOTH 'a' and 'b'
        WatchesHandler::register(get_class($model), 'a', SpyListener::class);
        WatchesHandler::register(get_class($model), 'b', SpyListener::class);

        WatchesHandler::fireForDirty($model, ['a' => '10', 'b' => '20']);

        $this->assertSame(2, SpyListener::$callCount);
    }

    private function evalWatchableModel(): Model
    {
        $class        = 'WatchableStub_' . spl_object_id($this);
        $spyFqcn      = addslashes(SpyListener::class);
        $anotherFqcn  = addslashes(AnotherSpyListener::class);
        $failFqcn     = addslashes(FailCondition::class);

        if (!class_exists($class)) {
            eval("
                use Phaseolies\\Database\\Entity\\Model;
                use Phaseolies\\Database\\Entity\\Attributes\\Watches;
                class {$class} extends Model {
                    protected \$table = 'stub';
                    #[Watches('{$spyFqcn}')]
                    protected \$status;
                    #[Watches('{$spyFqcn}', when: 'isHigh')]
                    #[Watches('{$anotherFqcn}')]
                    protected \$total;
                    #[Watches('{$spyFqcn}', when: '{$failFqcn}')]
                    protected \$notes;
                    public function isHigh(mixed \$old, mixed \$new): bool {
                        return (float) \$new > 1000;
                    }
                }
            ");
        }

        return new $class();
    }

    /** Instantiating a model with #[Watches] registers entries in WatchesHandler */
    public function testRegisterWatchesAttributesScansAndRegistersFromReflection(): void
    {
        WatchesHandler::flush();

        $model = $this->evalWatchableModel();
        $class = get_class($model);

        $this->assertArrayHasKey($class, WatchesHandler::$watches);
        $this->assertArrayHasKey('status', WatchesHandler::$watches[$class]);
        $this->assertArrayHasKey('total', WatchesHandler::$watches[$class]);
        $this->assertArrayHasKey('notes', WatchesHandler::$watches[$class]);
    }

    /** $total gets two watch entries (two stacked #[Watches]) */
    public function testRegisterWatchesAttributesRegistersAllStackedWatches(): void
    {
        WatchesHandler::flush();

        $model = $this->evalWatchableModel();
        $class = get_class($model);

        $this->assertCount(2, WatchesHandler::$watches[$class]['total']);
    }

    /** The when condition string is preserved in the registry */
    public function testRegisterWatchesAttributesPreservesWhenString(): void
    {
        WatchesHandler::flush();

        $model = $this->evalWatchableModel();
        $class = get_class($model);

        $entry = WatchesHandler::$watches[$class]['total'][0];
        $this->assertSame('isHigh', $entry['when']);
    }

    public function testResetWatchesCacheClearsSpecificClass(): void
    {
        WatchesHandler::flush();

        $model = $this->evalWatchableModel();
        $class = get_class($model);

        // Watches are registered during construction
        $this->assertArrayHasKey($class, WatchesHandler::$watches);

        // Wipe the watch registry and the reflection cache for this class
        WatchesHandler::flush($class);
        $model::resetWatchesCache($class);

        // The model boot cache prevents re-boot, so manually invoke
        // registerWatchesAttributes() as the boot path would
        \Closure::bind(
            fn() => $this->registerWatchesAttributes(),
            $model,
            get_class($model)
        )();

        // Watches should be restored from fresh reflection
        $this->assertArrayHasKey($class, WatchesHandler::$watches);
    }

    /** resetWatchesCache() with null clears all class caches */
    public function testResetWatchesCacheClearsAll(): void
    {
        $this->expectNotToPerformAssertions();

        // Should not throw or leave stale cache
        Model::resetWatchesCache();
        Model::resetWatchesCache(null);
    }

    /** firePropertyWatches() is a no-op when dirty array is empty */
    public function testFirePropertyWatchesDoesNothingForEmptyDirtyArray(): void
    {
        $model = new MockWatchableOrder();

        // $watches are already registered via setUp
        $model->firePropertyWatches([]);

        $this->assertFalse(SpyListener::$called);
    }

    /** firePropertyWatches() with a watched dirty property calls the watcher */
    public function testFirePropertyWatchesDelegatesToWatchesHandler(): void
    {
        $model = new MockWatchableOrder();
        $model->setOriginalAttributes(['status' => 'pending']);

        $model->firePropertyWatches(['status' => 'shipped']);

        $this->assertTrue(SpyListener::$called);
    }

    /** save() on an updated model fires the watch for the changed property */
    public function testWatchFiresAfterSuccessfulSave(): void
    {
        $id = $this->insertOrder('pending', 100, 'test');

        $order = MockWatchableOrder::find($id);
        $order->status = 'shipped';
        $order->save();

        $this->assertTrue(SpyListener::$called);
    }

    /** create() does NOT fire watches (only update path does) */
    public function testWatchDoesNotFireOnCreate(): void
    {
        MockWatchableOrder::create(['status' => 'pending', 'total' => 50, 'notes' => 'x']);

        $this->assertFalse(SpyListener::$called, 'Watches must not fire on create');
    }

    /** An unchanged property (not in dirty set) does not trigger its watch */
    public function testWatchDoesNotFireWhenPropertyNotDirty(): void
    {
        $id    = $this->insertOrder('pending', 100, 'old notes');
        $order = MockWatchableOrder::find($id);

        // Change a field that has no watch registered (status is not modified)
        $order->notes = 'updated notes';
        $order->save(); // notes watch uses FailCondition → never fires anyway

        // SpyListener watches $status (not dirty) → must not fire
        $this->assertFalse(SpyListener::$called);
    }

    /** save() passes the original pre-save value as $old to the watcher */
    public function testSavePassesCorrectOldValueToListener(): void
    {
        $id    = $this->insertOrder('pending', 100, 'test');
        $order = MockWatchableOrder::find($id);

        $order->status = 'shipped';
        $order->save();

        $this->assertSame('pending', SpyListener::$lastOld);
    }

    /** save() passes the new value to the watcher */
    public function testSavePassesCorrectNewValueToListener(): void
    {
        $id    = $this->insertOrder('pending', 100, 'test');
        $order = MockWatchableOrder::find($id);

        $order->status = 'shipped';
        $order->save();

        $this->assertSame('shipped', SpyListener::$lastNew);
    }

    /** The watcher receives the full model instance */
    public function testSavePassesModelInstanceToListener(): void
    {
        $id    = $this->insertOrder('pending', 100, 'test');
        $order = MockWatchableOrder::find($id);

        $order->status = 'shipped';
        $order->save();

        $this->assertInstanceOf(MockWatchableOrder::class, SpyListener::$lastModel);
        $this->assertSame($id, (int) SpyListener::$lastModel->id);
    }

    /**
     * After save() the originalAttributes is reset — but the watcher must have
     * been called while the original values were still accessible on the model.
     */
    public function testListenerReceivesOldValuesBeforeOriginalAttributesReset(): void
    {
        $id    = $this->insertOrder('pending', 100, 'test');
        $order = MockWatchableOrder::find($id);

        $order->status = 'shipped';
        $order->save();

        // SpyListener captured the model reference at fire time.
        // After save(), originalAttributes == attributes (both 'shipped').
        // But $lastOld was captured from the dirty map BEFORE the reset.
        $this->assertSame('pending', SpyListener::$lastOld);
        $this->assertSame('shipped', SpyListener::$lastNew);
    }

    /** Changing two watched properties in one save fires watches for both */
    public function testMultipleDirtyWatchedPropertiesFireMultipleWatches(): void
    {
        $id    = $this->insertOrder('pending', 100, 'test');
        $order = MockWatchableOrder::find($id);

        // $status  → SpyListener (always fires)
        // $total   → SpyListener (condition: isFraudRisk > 10000 → true) + AnotherSpyListener (always)
        $order->status = 'shipped';
        $order->total  = 15_000;
        $order->save();

        // SpyListener fires for status AND for total (condition passes) = 2 calls
        $this->assertSame(2, SpyListener::$callCount);
        // AnotherSpyListener fires for total only = 1 call
        $this->assertSame(1, AnotherSpyListener::$callCount);
    }

    /** Method condition controls whether the watch fires */
    public function testMethodConditionPreventsFiringWhenReturnsFalse(): void
    {
        $id    = $this->insertOrder('pending', 100, 'test');
        $order = MockWatchableOrder::find($id);

        // $total below 10 000 → isFraudRisk returns false → SpyListener must not fire for total
        $order->total = 5_000;
        $order->save();

        // AnotherSpyListener still fires (no condition on it)
        $this->assertTrue(AnotherSpyListener::$called, 'AnotherSpyListener should fire');

        // SpyListener must NOT have fired for total because condition failed
        // (status is not dirty so that watch doesn't fire either)
        $this->assertFalse(SpyListener::$called, 'SpyListener must not fire (condition failed)');
    }

    /** Class-based condition prevents firing when evaluate() returns false */
    public function testClassConditionPreventsFiringWhenReturnsFalse(): void
    {
        $id    = $this->insertOrder('pending', 100, 'old');
        $order = MockWatchableOrder::find($id);

        // $notes is watched with FailCondition — should never fire SpyListener
        $order->notes = 'new notes';
        $order->save();

        $this->assertFalse(SpyListener::$called);
    }

    /** Model::update() also fires watches for changed properties */
    public function testWatchFiresAfterUpdateMethod(): void
    {
        $id    = $this->insertOrder('pending', 100, 'test');
        $order = MockWatchableOrder::find($id);

        $order->update(['status' => 'processing']);

        $this->assertTrue(SpyListener::$called);
    }

    /** update() passes the correct old and new values */
    public function testUpdateMethodPassesCorrectOldAndNew(): void
    {
        $id    = $this->insertOrder('pending', 100, 'test');
        $order = MockWatchableOrder::find($id);

        $order->update(['status' => 'delivered']);

        $this->assertSame('pending',   SpyListener::$lastOld);
        $this->assertSame('delivered', SpyListener::$lastNew);
    }

    /** update() with a non-watched field does not fire watches */
    public function testUpdateOfNonWatchedFieldDoesNotFireWatch(): void
    {
        $id    = $this->insertOrder('pending', 100, 'original');
        $order = MockWatchableOrder::find($id);

        // notes watch uses FailCondition → never fires; status not dirty
        $order->update(['notes' => 'changed']);

        $this->assertFalse(SpyListener::$called);
    }

    /** withoutHook() prevents watches from firing on save() */
    public function testWithoutHookDisablesWatchesOnSave(): void
    {
        $id    = $this->insertOrder('pending', 100, 'test');
        $order = MockWatchableOrder::find($id);

        $order->withoutHook();
        $order->status = 'cancelled';
        $order->save();

        $this->assertFalse(SpyListener::$called, 'withoutHook() must suppress watches');
    }

    /** withoutHook() prevents watches from firing on update() */
    public function testWithoutHookDisablesWatchesOnUpdate(): void
    {
        $id    = $this->insertOrder('pending', 100, 'test');
        $order = MockWatchableOrder::find($id);

        $order->withoutHook();
        $order->update(['status' => 'cancelled']);

        $this->assertFalse(SpyListener::$called, 'withoutHook() must suppress watches');
    }

    /** Listener class without a handle() method throws RuntimeException */
    public function testListenerWithoutHandleMethodThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must expose a handle/i');

        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['x' => '1']);

        WatchesHandler::register(get_class($model), 'x', NoHandleListener::class);
        WatchesHandler::fireForDirty($model, ['x' => '2']);
    }

    /** Class-based condition not implementing interface throws RuntimeException */
    public function testConditionClassNotImplementingInterfaceThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must implement/');

        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['x' => '1']);

        WatchesHandler::register(get_class($model), 'x', SpyListener::class, NotAConditionClass::class);
        WatchesHandler::fireForDirty($model, ['x' => '2']);
    }

    /** Missing condition method (registered programmatically) throws RuntimeException */
    public function testConditionMethodNotOnModelThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not exist on/');

        WatchesHandler::flush();

        $model = $this->blankModel();
        $model->setOriginalAttributes(['x' => '1']);

        WatchesHandler::register(get_class($model), 'x', SpyListener::class, 'ghostMethod');
        WatchesHandler::fireForDirty($model, ['x' => '2']);
    }

    private function insertOrder(string $status, float $total, string $notes): int
    {
        $this->pdo->exec(
            "INSERT INTO orders (status, total, notes) VALUES ('{$status}', {$total}, '{$notes}')"
        );

        return (int) $this->pdo->lastInsertId();
    }

    private function createTestTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE orders (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                status TEXT    NOT NULL DEFAULT 'pending',
                total  REAL    NOT NULL DEFAULT 0,
                notes  TEXT
            )
        ");
    }

    private function setupDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', [
            'default' => $this->pdo,
            'sqlite'  => $this->pdo,
        ]);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function tearDownDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function setStaticProperty(string $class, string $property, mixed $value): void
    {
        $ref  = new \ReflectionClass($class);
        $prop = $ref->getProperty($property);
        $prop->setValue(null, $value);
    }
}
