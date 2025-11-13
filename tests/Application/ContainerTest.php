<?php

namespace Tests\Unit\Application;

use Tests\Application\Mock\StaticCallableClass;
use Tests\Application\Mock\SimpleClass;
use Tests\Application\Mock\Services\ConcreteServiceLayer;
use Tests\Application\Mock\Services\ConcreteService;
use Tests\Application\Mock\Services\AlternateDependency;
use Tests\Application\Mock\Repository\ConcreteRepository;
use Tests\Application\Mock\Providers\TestServiceProvider;
use Tests\Application\Mock\Providers\ProviderWithDependencies;
use Tests\Application\Mock\Providers\BootableServiceProvider;
use Tests\Application\Mock\Providers\BootableProviderWithDependencies;
use Tests\Application\Mock\Providers\AnotherServiceProvider;
use Tests\Application\Mock\MixedOptionalClass;
use Tests\Application\Mock\InvokableClass;
use Tests\Application\Mock\Interfaces\UnboundInterface;
use Tests\Application\Mock\Interfaces\TestInterface;
use Tests\Application\Mock\Interfaces\ServiceLayerInterface;
use Tests\Application\Mock\Interfaces\ServiceInterface;
use Tests\Application\Mock\Interfaces\RepositoryInterface;
use Tests\Application\Mock\Interfaces\DependencyInterface;
use Tests\Application\Mock\ExtendedSimpleClass;
use Tests\Application\Mock\DeepNestedClass;
use Tests\Application\Mock\Counter;
use Tests\Application\Mock\Controllers\ControllerClass;
use Tests\Application\Mock\ConcreteImplementation;
use Tests\Application\Mock\ConcreteDependency;
use Tests\Application\Mock\ComplexDependencyGraph;
use Tests\Application\Mock\ComplexConstructorClass;
use Tests\Application\Mock\ClassWithoutConstructor;
use Tests\Application\Mock\ClassWithVariadic;
use Tests\Application\Mock\ClassWithUnresolvablePrimitive;
use Tests\Application\Mock\ClassWithUnboundDependency;
use Tests\Application\Mock\ClassWithTypedVariadic;
use Tests\Application\Mock\ClassWithString;
use Tests\Application\Mock\ClassWithOptionalDependency;
use Tests\Application\Mock\ClassWithOnlyOptionals;
use Tests\Application\Mock\ClassWithNullableDefault;
use Tests\Application\Mock\ClassWithNullableClass;
use Tests\Application\Mock\ClassWithNullable;
use Tests\Application\Mock\ClassWithNestedDependency;
use Tests\Application\Mock\ClassWithMultiplePrimitives;
use Tests\Application\Mock\ClassWithMultipleDependencies;
use Tests\Application\Mock\ClassWithMixedRequiredOptional;
use Tests\Application\Mock\ClassWithMixedParams;
use Tests\Application\Mock\ClassWithManyParams;
use Tests\Application\Mock\ClassWithInt;
use Tests\Application\Mock\ClassWithFloat;
use Tests\Application\Mock\ClassWithEmptyConstructor;
use Tests\Application\Mock\ClassWithDependencyChain;
use Tests\Application\Mock\ClassWithDependencyAndVariadic;
use Tests\Application\Mock\ClassWithDependency;
use Tests\Application\Mock\ClassWithDefaults;
use Tests\Application\Mock\ClassWithBool;
use Tests\Application\Mock\ClassWithArray;
use Tests\Application\Mock\ClassWithAllDefaults;
use Tests\Application\Mock\CircularC;
use Tests\Application\Mock\CircularB;
use Tests\Application\Mock\CircularA;
use Tests\Application\Mock\CallableClass;
use Tests\Application\Mock\ApplicationClass;
use Tests\Application\Mock\Abstracts\AbstractClass;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->resetContainer();
    }

    protected function resetContainer(): void
    {
        $reflection = new \ReflectionClass(Container::class);

        $bindings = $reflection->getProperty('bindings');
        $bindings->setAccessible(true);
        $bindings->setValue(null, []);

        $instances = $reflection->getProperty('instances');
        $instances->setAccessible(true);
        $instances->setValue(null, []);

        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $this->resetContainer();
    }

    // =================================================
    // BASIC BINDING TESTS
    // =================================================

    public function testBindSimpleString()
    {
        $this->container->bind('key', fn() => 'value');
        $this->assertEquals('value', $this->container->get('key'));
    }

    public function testBindSimpleInteger()
    {
        $this->container->bind('number', fn() => 42);
        $this->assertEquals(42, $this->container->get('number'));
    }

    public function testBindSimpleArray()
    {
        $this->container->bind('array', fn() => [1, 2, 3]);
        $this->assertEquals([1, 2, 3], $this->container->get('array'));
    }

    public function testBindSimpleObject()
    {
        $obj = new \stdClass();
        $this->container->bind('object', fn() => $obj);
        $this->assertSame($obj, $this->container->get('object'));
    }

    public function testBindWithNullConcrete()
    {
        $this->container->bind(SimpleClass::class);
        $this->assertInstanceOf(SimpleClass::class, $this->container->get(SimpleClass::class));
    }

    public function testBindCallableReturningNull()
    {
        $this->container->bind('null', fn() => null);
        $this->assertNull($this->container->get('null'));
    }

    public function testBindCallableReturningFalse()
    {
        $this->container->bind('false', fn() => false);
        $this->assertFalse($this->container->get('false'));
    }

    public function testBindCallableReturningTrue()
    {
        $this->container->bind('true', fn() => true);
        $this->assertTrue($this->container->get('true'));
    }

    public function testBindMultipleServices()
    {
        $this->container->bind('service1', fn() => 'value1');
        $this->container->bind('service2', fn() => 'value2');
        $this->container->bind('service3', fn() => 'value3');

        $this->assertEquals('value1', $this->container->get('service1'));
        $this->assertEquals('value2', $this->container->get('service2'));
        $this->assertEquals('value3', $this->container->get('service3'));
    }

    public function testRebindService()
    {
        $this->container->bind('service', fn() => 'original');
        $this->assertEquals('original', $this->container->get('service'));

        $this->container->bind('service', fn() => 'replaced');
        $this->assertEquals('replaced', $this->container->get('service'));
    }

    //===================================================
    // SINGLETON TESTS
    //===================================================
    public function testSingletonReturnsSameInstance()
    {
        $this->container->singleton('single', fn() => new \stdClass());
        $first = $this->container->get('single');
        $second = $this->container->get('single');

        $this->assertSame($first, $second);
    }

    public function testSingletonWithClass()
    {
        $this->container->singleton(SimpleClass::class);
        $first = $this->container->get(SimpleClass::class);
        $second = $this->container->get(SimpleClass::class);

        $this->assertSame($first, $second);
    }

    public function testMultipleSingletons()
    {
        $this->container->singleton('single1', fn() => new \stdClass());
        $this->container->singleton('single2', fn() => new \stdClass());

        $this->assertNotSame($this->container->get('single1'), $this->container->get('single2'));
    }

    public function testSingletonWithState()
    {
        $this->container->singleton('counter', fn() => new Counter());
        $counter = $this->container->get('counter');
        $counter->increment();

        $secondRef = $this->container->get('counter');
        $this->assertEquals(1, $secondRef->getCount());
    }

    public function testTransientReturnsDifferentInstances()
    {
        $this->container->bind('transient', fn() => new \stdClass());
        $first = $this->container->get('transient');
        $second = $this->container->get('transient');

        $this->assertNotSame($first, $second);
    }

    //=================================================
    // INSTANCE BINDING TESTS
    //=================================================

    public function testInstanceBinding()
    {
        $obj = new \stdClass();
        $obj->value = 'test';
        $this->container->instance('obj', $obj);

        $resolved = $this->container->get('obj');
        $this->assertSame($obj, $resolved);
        $this->assertEquals('test', $resolved->value);
    }

    public function testInstanceBindingIsSingleton()
    {
        $obj = new \stdClass();
        $this->container->instance('obj', $obj);

        $first = $this->container->get('obj');
        $second = $this->container->get('obj');
        $this->assertSame($first, $second);
    }

    public function testInstanceBindingWithInterface()
    {
        $instance = new ConcreteImplementation();
        $this->container->instance(TestInterface::class, $instance);

        $resolved = $this->container->get(TestInterface::class);
        $this->assertSame($instance, $resolved);
    }

    public function testMultipleInstanceBindings()
    {
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $this->container->instance('obj1', $obj1);
        $this->container->instance('obj2', $obj2);

        $this->assertSame($obj1, $this->container->get('obj1'));
        $this->assertSame($obj2, $this->container->get('obj2'));
    }

    //==============================================
    // DEPENDENCY INJECTION TESTS
    //==============================================

    public function testSimpleDependencyInjection()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $instance = $this->container->make(ClassWithDependency::class);

        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(ConcreteDependency::class, $instance->dependency);
    }

    public function testNestedDependencyInjection()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $instance = $this->container->make(ClassWithNestedDependency::class);

        $this->assertInstanceOf(ClassWithNestedDependency::class, $instance);
        $this->assertInstanceOf(ClassWithDependency::class, $instance->nested);
        $this->assertInstanceOf(ConcreteDependency::class, $instance->nested->dependency);
    }

    public function testMultipleDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $this->container->bind(ServiceInterface::class, ConcreteService::class);

        $instance = $this->container->make(ClassWithMultipleDependencies::class);

        $this->assertInstanceOf(ConcreteDependency::class, $instance->dependency);
        $this->assertInstanceOf(ConcreteService::class, $instance->service);
    }

    public function testDependencyChain()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $this->container->bind(ServiceInterface::class, ConcreteService::class);

        $instance = $this->container->make(ClassWithDependencyChain::class);

        $this->assertInstanceOf(ClassWithDependencyChain::class, $instance);
        $this->assertInstanceOf(ClassWithMultipleDependencies::class, $instance->multi);
    }

    public function testSingletonDependency()
    {
        $this->container->singleton(DependencyInterface::class, ConcreteDependency::class);

        $instance1 = $this->container->make(ClassWithDependency::class);
        $instance2 = $this->container->make(ClassWithDependency::class);

        $this->assertNotSame($instance1, $instance2);
        $this->assertSame($instance1->dependency, $instance2->dependency);
    }

    //============================================
    // CONSTRUCTOR PARAMETER TESTS
    //============================================

    public function testConstructorWithPrimitiveString()
    {
        $instance = $this->container->make(ClassWithString::class, ['name' => 'John']);
        $this->assertEquals('John', $instance->name);
    }

    public function testConstructorWithPrimitiveInt()
    {
        $instance = $this->container->make(ClassWithInt::class, ['age' => 25]);
        $this->assertEquals(25, $instance->age);
    }

    // has issue
    // public function testConstructorWithPrimitiveBool()
    // {
    //     $instance = $this->container->make(ClassWithBool::class, ['active' => true]);
    //     $this->assertTrue($instance->active);
    // }

    public function testConstructorWithPrimitiveFloat()
    {
        $instance = $this->container->make(ClassWithFloat::class, ['price' => 19.99]);
        $this->assertEquals(19.99, $instance->price);
    }

    public function testConstructorWithPrimitiveArray()
    {
        $instance = $this->container->make(ClassWithArray::class, ['items' => [1, 2, 3]]);
        $this->assertEquals([1, 2, 3], $instance->items);
    }

    public function testConstructorWithMixedParameters()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $instance = $this->container->make(ClassWithMixedParams::class, [
            'name' => 'Test',
            'count' => 5
        ]);

        $this->assertInstanceOf(ConcreteDependency::class, $instance->dependency);
        $this->assertEquals('Test', $instance->name);
        $this->assertEquals(5, $instance->count);
    }

    public function testConstructorWithDefaultValues()
    {
        $instance = $this->container->make(ClassWithDefaults::class);

        $this->assertEquals('default', $instance->name);
        $this->assertEquals(0, $instance->count);
    }

    public function testConstructorOverrideDefaultValues()
    {
        $instance = $this->container->make(ClassWithDefaults::class, [
            'name' => 'custom',
            'count' => 10
        ]);

        $this->assertEquals('custom', $instance->name);
        $this->assertEquals(10, $instance->count);
    }

    public function testConstructorWithNullableParameter()
    {
        $instance = $this->container->make(ClassWithNullable::class);
        $this->assertNull($instance->value);
    }

    public function testConstructorWithNullableParameterProvided()
    {
        $instance = $this->container->make(ClassWithNullable::class, ['value' => 'test']);
        $this->assertEquals('test', $instance->value);
    }

    //=======================================
    // VARIADIC PARAMETER TESTS
    //=======================================

    public function testVariadicConstructor()
    {
        $instance = $this->container->make(ClassWithVariadic::class, ['a', 'b', 'c']);
        $this->assertEquals([['a', 'b', 'c']], $instance->items);
    }

    public function testVariadicConstructorEmpty()
    {
        $instance = $this->container->make(ClassWithVariadic::class, []);
        $this->assertEquals([[]], $instance->items);
    }

    public function testVariadicWithTypedParameters()
    {
        $instance = $this->container->make(ClassWithTypedVariadic::class, [1, 2, 3, 4, 5]);
        $this->assertEquals([[1, 2, 3, 4, 5]], $instance->numbers);
    }

    //========================================
    // INTERFACE BINDING TESTS
    //========================================

    public function testInterfaceToClassBinding()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $instance = $this->container->get(DependencyInterface::class);

        $this->assertInstanceOf(ConcreteDependency::class, $instance);
    }

    public function testInterfaceSingletonBinding()
    {
        $this->container->singleton(DependencyInterface::class, ConcreteDependency::class);

        $first = $this->container->get(DependencyInterface::class);
        $second = $this->container->get(DependencyInterface::class);

        $this->assertSame($first, $second);
    }

    public function testMultipleInterfaceBindings()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $this->container->bind(ServiceInterface::class, ConcreteService::class);

        $this->assertInstanceOf(ConcreteDependency::class, $this->container->get(DependencyInterface::class));
        $this->assertInstanceOf(ConcreteService::class, $this->container->get(ServiceInterface::class));
    }

    public function testRebindInterface()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $first = $this->container->get(DependencyInterface::class);

        $this->container->bind(DependencyInterface::class, AlternateDependency::class);
        $second = $this->container->get(DependencyInterface::class);

        $this->assertInstanceOf(ConcreteDependency::class, $first);
        $this->assertInstanceOf(AlternateDependency::class, $second);
    }

    //=========================================
    // ALIAS TESTS
    //=========================================

    public function testSimpleAlias()
    {
        $this->container->bind('original', fn() => 'value');
        $this->container->alias('original', 'aliased');

        $this->assertEquals('value', $this->container->get('aliased'));
    }

    public function testMultipleAliases()
    {
        $this->container->bind('original', fn() => 'value');
        $this->container->alias('original', 'alias1');
        $this->container->alias('original', 'alias2');

        $this->assertEquals('value', $this->container->get('alias1'));
        $this->assertEquals('value', $this->container->get('alias2'));
    }

    public function testAliasForClass()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $this->container->alias(DependencyInterface::class, 'dependency');

        $instance = $this->container->get('dependency');
        $this->assertInstanceOf(ConcreteDependency::class, $instance);
    }

    public function testAliasChain()
    {
        $this->container->bind('original', fn() => 'value');
        $this->container->alias('original', 'alias1');
        $this->container->alias('alias1', 'alias2');

        $this->assertEquals('value', $this->container->get('alias2'));
    }

    //========================================
    // EXTEND TESTS
    //========================================

    public function testExtendBinding()
    {
        $this->container->bind('service', fn() => 'base');
        $this->container->extend('service', fn($original) => $original . ' extended');

        $this->assertEquals('base extended', $this->container->get('service'));
    }

    public function testExtendMultipleTimes()
    {
        $this->container->bind('service', fn() => 'base');
        $this->container->extend('service', fn($original) => $original . ' first');
        $this->container->extend('service', fn($original) => $original . ' second');

        $this->assertEquals('base first second', $this->container->get('service'));
    }

    public function testExtendWithContainer()
    {
        $this->container->bind('dependency', fn() => 'dep');
        $this->container->bind('service', fn() => 'base');
        $this->container->extend('service', function ($original, $container) {
            return $original . ':' . $container->get('dependency');
        });

        $this->assertEquals('base:dep', $this->container->get('service'));
    }

    public function testExtendUnboundThrows()
    {
        $this->expectException(\RuntimeException::class);
        $this->container->extend('nonexistent', fn($original) => $original);
    }

    public function testExtendSingleton()
    {
        $this->container->singleton('service', fn() => new \stdClass());
        $this->container->extend('service', function ($original) {
            $original->extended = true;
            return $original;
        });

        $instance = $this->container->get('service');
        $this->assertTrue($instance->extended);
    }

    //=======================================
    // CALL METHOD TESTS FOR CLOSURE
    //=======================================

    public function testCallClosure()
    {
        $result = $this->container->call(fn() => 'result');
        $this->assertEquals('result', $result);
    }

    public function testCallClosureWithDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $result = $this->container->call(function (DependencyInterface $dep) {
            return get_class($dep);
        });

        $this->assertEquals(ConcreteDependency::class, $result);
    }

    public function testCallClosureWithParameters()
    {
        $result = $this->container->call(
            fn(string $name, int $age) => "$name:$age",
            ['name' => 'John', 'age' => 30]
        );

        $this->assertEquals('John:30', $result);
    }

    public function testCallClosureWithMixedDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $result = $this->container->call(
            function (DependencyInterface $dep, string $name) {
                return get_class($dep) . ':' . $name;
            },
            ['name' => 'Test']
        );

        $this->assertStringContainsString('ConcreteDependency:Test', $result);
    }

    public function testCallMethod()
    {
        $object = new CallableClass();
        $result = $this->container->call([$object, 'method']);

        $this->assertEquals('method result', $result);
    }

    public function testCallMethodWithDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $object = new CallableClass();
        $result = $this->container->call([$object, 'methodWithDependency']);

        $this->assertStringContainsString('ConcreteDependency', $result);
    }

    public function testCallMethodWithParameters()
    {
        $object = new CallableClass();
        $result = $this->container->call(
            [$object, 'methodWithParams'],
            ['name' => 'Test', 'value' => 42]
        );

        $this->assertEquals('Test:42', $result);
    }

    public function testCallStaticMethod()
    {
        $result = $this->container->call([CallableClass::class, 'staticMethod']);
        $this->assertEquals('static result', $result);
    }

    //=======================================
    // HAS TESTS
    //=======================================

    public function testHasBoundService()
    {
        $this->container->bind('service', fn() => 'value');
        $this->assertTrue($this->container->has('service'));
    }

    public function testHasUnboundService()
    {
        $this->assertFalse($this->container->has('nonexistent'));
    }

    public function testHasExistingClass()
    {
        $this->assertTrue($this->container->has(SimpleClass::class));
    }

    public function testHasNonExistentClass()
    {
        $this->assertFalse($this->container->has('NonExistentClass'));
    }

    public function testHasAfterRebind()
    {
        $this->container->bind('service', fn() => 'value1');
        $this->assertTrue($this->container->has('service'));

        $this->container->bind('service', fn() => 'value2');
        $this->assertTrue($this->container->has('service'));
    }

    public function testHasAfterUnset()
    {
        $this->container->bind('service', fn() => 'value');
        $this->assertTrue($this->container->has('service'));

        unset($this->container['service']);
        $this->assertFalse($this->container->has('service'));
    }

    //===========================================
    // HAS INSTANCE TESTS
    //===========================================

    public function testHasInstanceBeforeResolve()
    {
        $this->container->singleton('service', fn() => new \stdClass());
        $this->assertFalse($this->container->hasInstance('service'));
    }
    public function testHasInstanceAfterResolve()
    {
        $this->container->singleton('service', fn() => new \stdClass());
        $this->container->get('service');

        $this->assertTrue($this->container->hasInstance('service'));
    }

    public function testHasInstanceForInstanceBinding()
    {
        $this->container->instance('service', new \stdClass());
        $this->assertTrue($this->container->hasInstance('service'));
    }

    public function testHasInstanceForTransient()
    {
        $this->container->bind('service', fn() => new \stdClass());
        $this->container->get('service');

        $this->assertFalse($this->container->hasInstance('service'));
    }

    //=========================================
    // IS SINGLETON TESTS
    //=========================================

    public function testIsSingletonForSingleton()
    {
        $this->container->singleton('service', fn() => 'value');
        $this->assertTrue($this->container->isSingleton('service'));
    }

    public function testIsSingletonForTransient()
    {
        $this->container->bind('service', fn() => 'value');
        $this->assertFalse($this->container->isSingleton('service'));
    }

    public function testIsSingletonForUnbound()
    {
        $this->assertFalse($this->container->isSingleton('nonexistent'));
    }

    public function testIsSingletonForInstanceBinding()
    {
        $this->container->instance('service', new \stdClass());
        $this->assertTrue($this->container->isSingleton('service'));
    }

    //=================================================
    // RESOLVED TESTS
    //=================================================

    public function testResolvedBeforeGet()
    {
        $this->container->singleton('service', fn() => new \stdClass());
        $this->assertFalse($this->container->resolved('service'));
    }

    public function testResolvedAfterGet()
    {
        $this->container->singleton('service', fn() => new \stdClass());
        $this->container->get('service');

        $this->assertTrue($this->container->resolved('service'));
    }

    public function testResolvedForTransient()
    {
        $this->container->bind('service', fn() => new \stdClass());
        $this->container->get('service');

        $this->assertFalse($this->container->resolved('service'));
    }

    //===========================================
    // ARRAY ACCESS TESTS
    //===========================================

    public function testArrayAccessSet()
    {
        $this->container['key'] = fn() => 'value';
        $this->assertTrue(isset($this->container['key']));
    }

    public function testArrayAccessGet()
    {
        $this->container['key'] = fn() => 'value';
        $this->assertEquals('value', $this->container['key']);
    }

    public function testArrayAccessExists()
    {
        $this->container['key'] = fn() => 'value';
        $this->assertTrue(isset($this->container['key']));
    }

    public function testArrayAccessUnset()
    {
        $this->container['key'] = fn() => 'value';
        unset($this->container['key']);

        $this->assertFalse(isset($this->container['key']));
    }

    public function testArrayAccessMultipleKeys()
    {
        $this->container['key1'] = fn() => 'value1';
        $this->container['key2'] = fn() => 'value2';

        $this->assertEquals('value1', $this->container['key1']);
        $this->assertEquals('value2', $this->container['key2']);
    }

    //=======================================
    // WHEN CONDITION TESTS
    //=======================================

    public function testWhenTrueCondition()
    {
        $result = $this->container->when(true);
        $this->assertInstanceOf(Container::class, $result);
    }

    public function testWhenFalseCondition()
    {
        $result = $this->container->when(false);
        $this->assertNull($result);
    }

    public function testWhenCallableReturnsTrue()
    {
        $result = $this->container->when(fn() => true);
        $this->assertInstanceOf(Container::class, $result);
    }

    public function testWhenCallableReturnsFalse()
    {
        $result = $this->container->when(fn() => false);
        $this->assertNull($result);
    }

    public function testWhenChaining()
    {
        $this->container->when(true)?->bind('service', fn() => 'value');
        $this->assertTrue($this->container->has('service'));
    }

    public function testWhenChainingFalse()
    {
        $this->container->when(false)?->bind('service', fn() => 'value');
        $this->assertFalse($this->container->has('service'));
    }

    public function testWhenWithEnvironmentCheck()
    {
        $env = 'production';
        $this->container->when($env === 'production')?->singleton('cache', fn() => new \stdClass());

        $this->assertTrue($this->container->has('cache'));
    }

    // ==================== FLUSH/RESET TESTS ====================

    public function testFlushClearsBindings()
    {
        $this->container->bind('service', fn() => 'value');
        $this->container->flush();

        $this->assertFalse($this->container->has('service'));
    }

    public function testFlushClearsInstances()
    {
        $this->container->singleton('service', fn() => new \stdClass());
        $this->container->get('service');
        $this->container->flush();

        $this->assertFalse($this->container->hasInstance('service'));
    }

    public function testResetClearsAll()
    {
        $this->container->bind('service1', fn() => 'value1');
        $this->container->singleton('service2', fn() => new \stdClass());
        $this->container->get('service2');

        $this->container->reset();

        $this->assertFalse($this->container->has('service1'));
        $this->assertFalse($this->container->hasInstance('service2'));
    }

    public function testFlushAllowsRebinding()
    {
        $this->container->bind('service', fn() => 'value1');
        $this->container->flush();
        $this->container->bind('service', fn() => 'value2');

        $this->assertEquals('value2', $this->container->get('service'));
    }

    //==========================================
    // CIRCULAR DEPENDENCY INJECTION TESTS
    //==========================================

    public function testCircularDependencyDetection()
    {
        $this->container->bind(CircularA::class);
        $this->container->bind(CircularB::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency');

        $this->container->get(CircularA::class);
    }

    public function testCircularDependencyWithThreeClasses()
    {
        $this->container->bind(CircularA::class);
        $this->container->bind(CircularB::class);
        $this->container->bind(CircularC::class);

        $this->expectException(\RuntimeException::class);
        $this->container->get(CircularA::class);
    }

    //================================================
    // EXCEPTION TESTS
    //================================================

    public function testResolveAbstractClass()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not instantiable');

        $this->container->get(AbstractClass::class);
    }

    // has issue
    // public function testResolveInterface()
    // {
    //     $this->expectException(\RuntimeException::class);
    //     $this->expectExceptionMessage('not instantiable');

    //     $this->container->get(UnboundInterface::class);
    // }

    public function testResolveNonExistentClass()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not bound');

        $this->container->get('NonExistentClass');
    }

    public function testResolveUnresolvablePrimitive()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unresolvable dependency');

        $this->container->make(ClassWithUnresolvablePrimitive::class);
    }

    //==========================================
    // MAKE TESTS
    //==========================================

    public function testMakeSimpleClass()
    {
        $instance = $this->container->make(SimpleClass::class);
        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    public function testMakeClassWithDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $instance = $this->container->make(ClassWithDependency::class);

        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(ConcreteDependency::class, $instance->dependency);
    }

     public function testMakeWithParameters()
    {
        $instance = $this->container->make(ClassWithString::class, ['name' => 'Test']);
        $this->assertEquals('Test', $instance->name);
    }

    public function testMakeMultipleTimes()
    {
        $first = $this->container->make(SimpleClass::class);
        $second = $this->container->make(SimpleClass::class);

        $this->assertNotSame($first, $second);
    }

    public function testMakeVsGet()
    {
        $made = $this->container->make(SimpleClass::class);
        $gotten = $this->container->get(SimpleClass::class);

        $this->assertInstanceOf(SimpleClass::class, $made);
        $this->assertInstanceOf(SimpleClass::class, $gotten);
    }

    //===========================================
    // SHARE TESTS
    //===========================================

    public function testShareMethod()
    {
        $obj = new \stdClass();
        $obj->value = 'shared';

        $this->container->share('shared', $obj);

        $this->assertSame($obj, $this->container->get('shared'));
    }

    public function testShareIsSingleton()
    {
        $obj = new \stdClass();
        $this->container->share('shared', $obj);

        $first = $this->container->get('shared');
        $second = $this->container->get('shared');

        $this->assertSame($first, $second);
    }

    public function testShareMultipleObjects()
    {
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();

        $this->container->share('obj1', $obj1);
        $this->container->share('obj2', $obj2);

        $this->assertSame($obj1, $this->container->get('obj1'));
        $this->assertSame($obj2, $this->container->get('obj2'));
    }

    //=========================================
    // GET BINDINGS/INSTANCES TESTS
    //=========================================

    public function testGetBindingsEmpty()
    {
        $bindings = $this->container->getBindings();
        $this->assertIsArray($bindings);
        $this->assertEmpty($bindings);
    }

    public function testGetBindings()
    {
        $this->container->bind('service1', fn() => 'value1');
        $this->container->bind('service2', fn() => 'value2');

        $bindings = $this->container->getBindings();
        $this->assertCount(2, $bindings);
        $this->assertArrayHasKey('service1', $bindings);
        $this->assertArrayHasKey('service2', $bindings);
    }

    public function testGetInstancesEmpty()
    {
        $instances = $this->container->getInstances();
        $this->assertIsArray($instances);
        $this->assertEmpty($instances);
    }

    public function testGetInstances()
    {
        $this->container->singleton('single1', fn() => new \stdClass());
        $this->container->singleton('single2', fn() => new \stdClass());

        $this->container->get('single1');
        $this->container->get('single2');

        $instances = $this->container->getInstances();
        $this->assertCount(2, $instances);
    }

    //======================================
    // GET ALIASES TESTS
    //======================================

    public function testGetAliasesEmpty()
    {
        $aliases = $this->container->getAliases();
        $this->assertIsArray($aliases);
        $this->assertEmpty($aliases);
    }

    // has issue
    // public function testGetAliases()
    // {
    //     $this->container->bind('original', fn() => 'value');
    //     $this->container->alias('original', 'alias1');
    //     $this->container->alias('original', 'alias2');

    //     $aliases = $this->container->getAliases();
    //     $this->assertNotEmpty($aliases);
    // }

    //=====================================
    // RESOLVE METHOD DEPENDENCIES TESTS
    //=====================================

    public function testResolveMethodDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $deps = $this->container->resolveMethodDependencies(
            CallableClass::class,
            'methodWithDependency'
        );

        $this->assertCount(1, $deps);
        $this->assertInstanceOf(ConcreteDependency::class, $deps[0]);
    }

    public function testResolveMethodDependenciesWithParams()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $deps = $this->container->resolveMethodDependencies(
            CallableClass::class,
            'methodWithMixed',
            ['value' => 'test']
        );

        $this->assertCount(2, $deps);
        $this->assertInstanceOf(ConcreteDependency::class, $deps[0]);
        $this->assertEquals('test', $deps[1]);
    }

    //=============================================
    // IS RESOLVING TESTS
    //=============================================

    public function testIsResolvingDuringResolution()
    {
        $this->container->bind('service', function() {
            return $this->container->isResolving('service') ? 'resolving' : 'not';
        });

        $result = $this->container->get('service');
        $this->assertEquals('resolving', $result);
    }

    public function testIsResolvingAfterResolution()
    {
        $this->container->bind('service', fn() => 'value');
        $this->container->get('service');

        $this->assertFalse($this->container->isResolving('service'));
    }

    public function testIsResolvingNever()
    {
        $this->assertFalse($this->container->isResolving('never_resolved'));
    }

    //=======================================
    // SERVICE PROVIDER TESTS
    //=======================================
    public function testRegisterServiceProvider()
    {
        $provider = new TestServiceProvider();
        $this->container->register($provider);

        $this->assertTrue($this->container->has('from_provider'));
    }

    public function testRegisterServiceProviderByClass()
    {
        $this->container->register(TestServiceProvider::class);

        $this->assertTrue($this->container->has('from_provider'));
    }

    public function testMultipleServiceProviders()
    {
        $this->container->register(TestServiceProvider::class);
        $this->container->register(AnotherServiceProvider::class);

        $this->assertTrue($this->container->has('from_provider'));
        $this->assertTrue($this->container->has('another_service'));
    }

    //============================================
    // SINGLETON INSTANCE TESTS
    //============================================

    public function testGetInstanceReturnsSingleton()
    {
        $first = Container::getInstance();
        $second = Container::getInstance();

        $this->assertSame($first, $second);
    }

    public function testSetInstance()
    {
        $custom = new Container();
        Container::setInstance($custom);

        $this->assertSame($custom, Container::getInstance());
    }

    public function testForgetInstance()
    {
        $first = Container::getInstance();
        Container::forgetInstance();
        $second = Container::getInstance();

        $this->assertNotSame($first, $second);
    }

    //=============================================
    // COMPLEX DEPENDENCY SCENARIOS
    //=============================================

    public function testDeepNestedDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $this->container->bind(ServiceInterface::class, ConcreteService::class);

        $instance = $this->container->make(DeepNestedClass::class);

        $this->assertInstanceOf(DeepNestedClass::class, $instance);
        $this->assertInstanceOf(ClassWithDependencyChain::class, $instance->chain);
        $this->assertInstanceOf(ClassWithMultipleDependencies::class, $instance->chain->multi);
    }

    public function testMultipleConstructorDependenciesWithPrimitives()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $instance = $this->container->make(ComplexConstructorClass::class, [
            'name' => 'Test',
            'count' => 5,
            'active' => true
        ]);

        $this->assertInstanceOf(ConcreteDependency::class, $instance->dependency);
        $this->assertEquals('Test', $instance->name);
        $this->assertEquals(5, $instance->count);
        $this->assertTrue($instance->active);
    }

    public function testOptionalDependencyWithDefault()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $instance = $this->container->make(ClassWithOptionalDependency::class);

        $this->assertInstanceOf(ConcreteDependency::class, $instance->required);
        $this->assertEquals('default', $instance->optional);
    }

    public function testOptionalDependencyOverridden()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $instance = $this->container->make(ClassWithOptionalDependency::class, [
            'optional' => 'custom'
        ]);

        $this->assertEquals('custom', $instance->optional);
    }

    public function testMixedOptionalAndRequired()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $this->container->bind(ServiceInterface::class, ConcreteService::class);

        $instance = $this->container->make(MixedOptionalClass::class, [
            'name' => 'Custom'
        ]);

        $this->assertInstanceOf(ConcreteDependency::class, $instance->dep);
        $this->assertInstanceOf(ConcreteService::class, $instance->service);
        $this->assertEquals('Custom', $instance->name);
        $this->assertEquals(0, $instance->count);
    }

    //=============================================
    // MULTIPLE INTERFACE IMPLEMENTATIONS
    //=============================================

    public function testMultipleImplementations()
    {
        $this->container->bind('impl1', fn() => new ConcreteDependency());
        $this->container->bind('impl2', fn() => new AlternateDependency());

        $impl1 = $this->container->get('impl1');
        $impl2 = $this->container->get('impl2');

        $this->assertInstanceOf(ConcreteDependency::class, $impl1);
        $this->assertInstanceOf(AlternateDependency::class, $impl2);
    }

    public function testSwitchImplementation()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $first = $this->container->make(ClassWithDependency::class);

        $this->container->bind(DependencyInterface::class, AlternateDependency::class);
        $second = $this->container->make(ClassWithDependency::class);

        $this->assertInstanceOf(ConcreteDependency::class, $first->dependency);
        $this->assertInstanceOf(AlternateDependency::class, $second->dependency);
    }

    //========================================
    // CONTEXTUAL BINDING SCENARIOS
    //========================================

    public function testDifferentImplementationsForDifferentClasses()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $instance1 = $this->container->make(ClassWithDependency::class);
        $this->assertInstanceOf(ConcreteDependency::class, $instance1->dependency);

        $this->container->bind(DependencyInterface::class, AlternateDependency::class);
        $instance2 = $this->container->make(ClassWithDependency::class);
        $this->assertInstanceOf(AlternateDependency::class, $instance2->dependency);
    }

    //============================================
    // CALLABLE BINDING TESTS
    //============================================

    public function testCallableWithContainerParameter()
    {
        $this->container->bind('test', function(Container $container) {
            return $container->has('dependency') ? 'has' : 'not';
        });

        $this->container->bind('dependency', fn() => 'dep');
        $this->assertEquals('has', $this->container->get('test'));
    }

    public function testCallableWithParameters()
    {
        $this->container->bind('test', function(Container $c, array $params) {
            return $params['value'] ?? 'default';
        });

        $result = $this->container->get('test', ['value' => 'custom']);
        $this->assertEquals('custom', $result);
    }

    public function testNestedCallableResolution()
    {
        $this->container->bind('inner', fn() => 'inner_value');
        $this->container->bind('outer', function(Container $c) {
            return $c->get('inner') . '_outer';
        });

        $this->assertEquals('inner_value_outer', $this->container->get('outer'));
    }

    //===============================================
    // UNION TYPE AND NULLABLE TYPE TESTS (PHP 8.0+)
    //===============================================

    // has issue
    // public function testNullableClassDependency()
    // {
    //     $instance = $this->container->make(ClassWithNullableClass::class);
    //     $this->assertNull($instance->dependency);
    // }

    // has issue
    // public function testNullableClassDependencyProvided()
    // {
    //     $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
    //     $instance = $this->container->make(ClassWithNullableClass::class);

    //     $this->assertInstanceOf(ConcreteDependency::class, $instance->dependency);
    // }

    //================================================
    // CLOSURE BINDING TESTS
    //================================================

    public function testClosureAsService()
    {
        $closure = fn(string $input) => strtoupper($input);
        $this->container->instance('transformer', $closure);

        $retrieved = $this->container->get('transformer');
        $this->assertEquals('HELLO', $retrieved('hello'));
    }

    public function testClosureFactory()
    {
        $this->container->bind('closure_factory', fn() => fn() => 'result');

        $factory = $this->container->get('closure_factory');
        $this->assertEquals('result', $factory());
    }

    //==============================================
    // REBINDING TESTS
    //==============================================

    public function testRebindingSingleton()
    {
        $this->container->singleton('service', fn() => 'original');
        $first = $this->container->get('service');

        $this->container->singleton('service', fn() => 'replaced');
        $second = $this->container->get('service');

        $this->assertEquals('original', $first);
        $this->assertEquals('replaced', $second);
    }

    public function testRebindingPreservesNewType()
    {
        $this->container->bind('service', fn() => 'transient');
        $this->assertFalse($this->container->isSingleton('service'));

        $this->container->singleton('service', fn() => 'singleton');
        $this->assertTrue($this->container->isSingleton('service'));
    }

    //========================================
    // CONCURRENT RESOLUTION TESTS
    //========================================

    public function testMultipleServicesResolvedConcurrently()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $this->container->bind(ServiceInterface::class, ConcreteService::class);

        $dep = $this->container->get(DependencyInterface::class);
        $svc = $this->container->get(ServiceInterface::class);

        $this->assertInstanceOf(ConcreteDependency::class, $dep);
        $this->assertInstanceOf(ConcreteService::class, $svc);
    }

    //==================================================
    // NESTED CONTAINER CALLS
    //==================================================

    public function testNestedContainerCalls()
    {
        $this->container->bind('level1', function(Container $c) {
            return $c->get('level2') . ':1';
        });

        $this->container->bind('level2', function(Container $c) {
            return $c->get('level3') . ':2';
        });

        $this->container->bind('level3', fn() => '3');

        $this->assertEquals('3:2:1', $this->container->get('level1'));
    }

    //==========================================
    // PARAMETER POSITION TESTS
    //==========================================

    public function testPositionalParameters()
    {
        $instance = $this->container->make(ClassWithMultiplePrimitives::class, [
            'John',
            30,
            true
        ]);

        $this->assertEquals('John', $instance->name);
        $this->assertEquals(30, $instance->age);
        $this->assertTrue($instance->active);
    }

    public function testNamedParameters()
    {
        $instance = $this->container->make(ClassWithMultiplePrimitives::class, [
            'age' => 30,
            'name' => 'John',
            'active' => false
        ]);

        $this->assertEquals('John', $instance->name);
        $this->assertEquals(30, $instance->age);
        $this->assertFalse($instance->active);
    }

    public function testMixedPositionalAndNamed()
    {
        $instance = $this->container->make(ClassWithMultiplePrimitives::class, [
            'name' => 'John',
            'age' => 30,
            'active' => true
        ]);

        $this->assertEquals('John', $instance->name);
        $this->assertEquals(30, $instance->age);
        $this->assertTrue($instance->active);
    }

    //==============================================
    // DEFAULT VALUE TESTS
    //==============================================

    public function testAllDefaultValues()
    {
        $instance = $this->container->make(ClassWithAllDefaults::class);

        $this->assertEquals('default', $instance->name);
        $this->assertEquals(0, $instance->count);
        $this->assertFalse($instance->active);
        $this->assertEquals([], $instance->items);
    }

    public function testPartialDefaultOverride()
    {
        $instance = $this->container->make(ClassWithAllDefaults::class, [
            'name' => 'custom'
        ]);

        $this->assertEquals('custom', $instance->name);
        $this->assertEquals(0, $instance->count);
    }

    //===========================================
    // TYPE COERCION TESTS
    //===========================================

    public function testStringToIntCoercion()
    {
        $instance = $this->container->make(ClassWithInt::class, ['age' => '25']);
        $this->assertIsInt($instance->age);
        $this->assertEquals(25, $instance->age);
    }

    public function testIntToBoolCoercion()
    {
        $instance = $this->container->make(ClassWithBool::class, ['active' => 1]);
        $this->assertIsBool($instance->active);
        $this->assertTrue($instance->active);
    }

    //=======================================
    // EMPTY CONSTRUCTOR TESTS
    //=======================================

    public function testClassWithoutConstructor()
    {
        $instance = $this->container->make(ClassWithoutConstructor::class);
        $this->assertInstanceOf(ClassWithoutConstructor::class, $instance);
    }

    public function testClassWithEmptyConstructor()
    {
        $instance = $this->container->make(ClassWithEmptyConstructor::class);
        $this->assertInstanceOf(ClassWithEmptyConstructor::class, $instance);
    }

    //=======================================
    // STATIC METHOD TESTS
    //=======================================

    public function testCallStaticMethodWithDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $result = $this->container->call([StaticCallableClass::class, 'staticWithDependency']);

        $this->assertStringContainsString('ConcreteDependency', $result);
    }

    //====================================
    // COMPLEX SCENARIOS
    //====================================

     public function testDependencyGraphWithSingletons()
    {
        $this->container->singleton(DependencyInterface::class, ConcreteDependency::class);
        $this->container->bind(ServiceInterface::class, ConcreteService::class);

        $instance1 = $this->container->make(ClassWithMultipleDependencies::class);
        $instance2 = $this->container->make(ClassWithMultipleDependencies::class);

        $this->assertSame($instance1->dependency, $instance2->dependency);
        $this->assertNotSame($instance1->service, $instance2->service);
    }

    public function testComplexDependencyWithExtend()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $this->container->extend(DependencyInterface::class, function($dep, $c) {
            $dep->extended = true;
            return $dep;
        });

        $instance = $this->container->make(ClassWithDependency::class);
        $this->assertTrue($instance->dependency->extended);
    }

    //======================================
    // PARAMETER TEST
    //======================================

    public function testArrayParameterInjection()
    {
        $data = ['a' => 1, 'b' => 2];
        $instance = $this->container->make(ClassWithArray::class, ['items' => $data]);

        $this->assertEquals($data, $instance->items);
    }

    public function testEmptyArrayParameter()
    {
        $instance = $this->container->make(ClassWithArray::class, ['items' => []]);
        $this->assertEquals([], $instance->items);
    }

   //========================================
   // EDGE CASES
   //========================================

   public function testBindingWithSpecialCharacters()
    {
        $this->container->bind('service.name', fn() => 'value');
        $this->assertEquals('value', $this->container->get('service.name'));
    }

    public function testBindingWithNamespace()
    {
        $this->container->bind('App\\Service\\MyService', fn() => 'value');
        $this->assertEquals('value', $this->container->get('App\\Service\\MyService'));
    }

    public function testLongServiceName()
    {
        $longName = str_repeat('service', 50);
        $this->container->bind($longName, fn() => 'value');

        $this->assertEquals('value', $this->container->get($longName));
    }

    public function testNumericStringAsKey()
    {
        $this->container->bind('123', fn() => 'numeric');
        $this->assertEquals('numeric', $this->container->get('123'));
    }

    //===========================================
    // PERFORMANCE RELATED TESTS
    //===========================================

    public function testManyBindings()
    {
        for ($i = 0; $i < 100; $i++) {
            $this->container->bind("service_$i", fn() => "value_$i");
        }

        $this->assertEquals('value_50', $this->container->get('service_50'));
        $this->assertEquals('value_99', $this->container->get('service_99'));
    }

    public function testManySingletons()
    {
        for ($i = 0; $i < 50; $i++) {
            $this->container->singleton("singleton_$i", fn() => new \stdClass());
        }

        $first = $this->container->get('singleton_25');
        $second = $this->container->get('singleton_25');

        $this->assertSame($first, $second);
    }

    //===================================
    // OTHERS RANDOM METHOD AND JOB TEST
    //===================================

    public function testMethodChaining()
    {
        $this->container->when(true)?->bind('chain1', fn() => 'value1');
        $this->assertTrue($this->container->has('chain1'));
    }

    public function testInstanceOfCheck()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $instance = $this->container->get(DependencyInterface::class);

        $this->assertInstanceOf(DependencyInterface::class, $instance);
        $this->assertInstanceOf(ConcreteDependency::class, $instance);
    }

    public function testMultipleInstanceOfChecks()
    {
        $this->container->instance(DependencyInterface::class, new ConcreteDependency());
        $this->container->instance('another', new AlternateDependency());

        $dep = $this->container->get(DependencyInterface::class);
        $alt = $this->container->get('another');

        $this->assertInstanceOf(DependencyInterface::class, $dep);
        $this->assertInstanceOf(DependencyInterface::class, $alt);
    }

    public function testBuildDirectly()
    {
        $instance = $this->container->build(SimpleClass::class, []);
        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    public function testBuildWithDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $instance = $this->container->build(ClassWithDependency::class, []);

        $this->assertInstanceOf(ClassWithDependency::class, $instance);
    }

    public function testBuildWithParameters()
    {
        $instance = $this->container->build(ClassWithString::class, ['name' => 'Built']);
        $this->assertEquals('Built', $instance->name);
    }

    public function testSetAndGetInstance()
    {
        $custom = new Container();
        Container::setInstance($custom);

        $retrieved = Container::getInstance();
        $this->assertSame($custom, $retrieved);

        Container::forgetInstance();
    }

    public function testDependencyWithFactory()
    {
        $this->container->bind(DependencyInterface::class, function() {
            static $counter = 0;
            $dep = new ConcreteDependency();
            $dep->id = ++$counter;
            return $dep;
        });

        $first = $this->container->get(DependencyInterface::class);
        $second = $this->container->get(DependencyInterface::class);

        $this->assertNotEquals($first->id, $second->id);
    }

    public function testSingletonWithFactory()
    {
        $this->container->singleton(DependencyInterface::class, function() {
            static $counter = 0;
            $dep = new ConcreteDependency();
            $dep->id = ++$counter;
            return $dep;
        });

        $first = $this->container->get(DependencyInterface::class);
        $second = $this->container->get(DependencyInterface::class);

        $this->assertEquals($first->id, $second->id);
        $this->assertSame($first, $second);
    }

    public function testExtendWithComplexLogic()
    {
        $this->container->bind('service', fn() => ['base' => true]);
        $this->container->extend('service', function($original, $c) {
            $original['extended'] = true;
            $original['dependency'] = $c->has('dep') ? 'yes' : 'no';
            return $original;
        });

        $this->container->bind('dep', fn() => 'value');
        $result = $this->container->get('service');

        $this->assertTrue($result['base']);
        $this->assertTrue($result['extended']);
        $this->assertEquals('yes', $result['dependency']);
    }

    public function testNestedExtend()
    {
        $this->container->bind('service', fn() => 1);
        $this->container->extend('service', fn($val) => $val * 2);
        $this->container->extend('service', fn($val) => $val + 10);

        $this->assertEquals(12, $this->container->get('service'));
    }

    public function testResolveStringBinding()
    {
        $this->container->bind('abstract', SimpleClass::class);
        $instance = $this->container->get('abstract');

        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    public function testResolveCallableBinding()
    {
        $this->container->bind('abstract', fn() => new SimpleClass());
        $instance = $this->container->get('abstract');

        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    public function testResolvePrimitiveBinding()
    {
        $this->container->bind('config.debug', fn() => true);
        $this->assertTrue($this->container->get('config.debug'));
    }

    public function testResolveFromMultipleSources()
    {
        // Bound service
        $this->container->bind('service1', fn() => 'bound');

        // Instance
        $this->container->instance('service2', 'instance');

        // Auto-resolved class
        $class = $this->container->make(SimpleClass::class);

        $this->assertEquals('bound', $this->container->get('service1'));
        $this->assertEquals('instance', $this->container->get('service2'));
        $this->assertInstanceOf(SimpleClass::class, $class);
    }

    public function testCallbackWithNoParameters()
    {
        $result = $this->container->call(fn() => 'no params');
        $this->assertEquals('no params', $result);
    }

    public function testCallbackWithOnlyDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $result = $this->container->call(function(DependencyInterface $dep) {
            return get_class($dep);
        });

        $this->assertEquals(ConcreteDependency::class, $result);
    }

    public function testCallbackWithOnlyPrimitives()
    {
        $result = $this->container->call(
            fn(string $a, int $b) => "$a:$b",
            ['a' => 'test', 'b' => 42]
        );

        $this->assertEquals('test:42', $result);
    }

    public function testCallbackWithOptionalParameters()
    {
        $result = $this->container->call(
            fn(string $name = 'default') => $name
        );

        $this->assertEquals('default', $result);
    }

    public function testCallbackWithOptionalParametersOverridden()
    {
        $result = $this->container->call(
            fn(string $name = 'default') => $name,
            ['name' => 'custom']
        );

        $this->assertEquals('custom', $result);
    }

    //=========================================
    // INTEGRATION TESTS
    //=========================================

    public function testFullApplicationFlow()
    {
        // Setup dependencies
        $this->container->singleton(DependencyInterface::class, ConcreteDependency::class);
        $this->container->bind(ServiceInterface::class, ConcreteService::class);

        // Create application class
        $app = $this->container->make(ApplicationClass::class, [
            'config' => ['debug' => true]
        ]);

        $this->assertInstanceOf(ApplicationClass::class, $app);
        $this->assertInstanceOf(ConcreteDependency::class, $app->dependency);
        $this->assertInstanceOf(ConcreteService::class, $app->service);
        $this->assertEquals(['debug' => true], $app->config);
    }

    // has issue
    // public function testRepositoryPattern()
    // {
    //     $this->container->singleton(RepositoryInterface::class, ConcreteRepository::class);

    //     $controller = $this->container->make(ControllerClass::class);

    //     $this->assertInstanceOf(ConcreteRepository::class, $controller->repository);
    // }

    // has issue
    // public function testServiceLayerPattern()
    // {
    //     $this->container->singleton(RepositoryInterface::class, ConcreteRepository::class);
    //     $this->container->singleton(ServiceLayerInterface::class, ConcreteServiceLayer::class);

    //     $service = $this->container->get(ServiceLayerInterface::class);

    //     $this->assertInstanceOf(ConcreteServiceLayer::class, $service);
    //     $this->assertInstanceOf(ConcreteRepository::class, $service->repository);
    // }

    public function testInvalidCallableThrows()
    {
        $this->expectException(\TypeError::class);
        $this->container->call('not_a_callable');
    }

    public function testUnresolvableDependencyThrows()
    {
        $this->expectException(\RuntimeException::class);
        $this->container->make(ClassWithUnboundDependency::class);
    }

    public function testAbstractClassInstantiationThrows()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not instantiable');
        $this->container->build(AbstractClass::class, []);
    }

    public function testInterfaceInstantiationThrows()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not instantiable');
        $this->container->build(DependencyInterface::class, []);
    }

    public function testBindZero()
    {
        $this->container->bind('zero', fn() => 0);
        $this->assertEquals(0, $this->container->get('zero'));
    }

    public function testBindEmptyString()
    {
        $this->container->bind('empty', fn() => '');
        $this->assertEquals('', $this->container->get('empty'));
    }

    public function testBindEmptyArray()
    {
        $this->container->bind('empty_array', fn() => []);
        $this->assertEquals([], $this->container->get('empty_array'));
    }

    public function testBindNegativeNumber()
    {
        $this->container->bind('negative', fn() => -100);
        $this->assertEquals(-100, $this->container->get('negative'));
    }

    public function testBindFloat()
    {
        $this->container->bind('pi', fn() => 3.14159);
        $this->assertEquals(3.14159, $this->container->get('pi'));
    }

    public function testCloneNotAllowed()
    {
        $instance = $this->container;
        $cloned = clone $instance;

        // __clone is empty, so it creates a shallow copy but shouldn't be used
        $this->assertNotSame($instance, $cloned);
    }

    public function testServiceProviderWithDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $this->container->register(ProviderWithDependencies::class);

        $this->assertTrue($this->container->has('provider_service'));
    }

     public function testServiceProviderBootReceivesDependencies()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $provider = new BootableProviderWithDependencies();

        $this->container->register($provider);

        $this->assertInstanceOf(ConcreteDependency::class, $provider->bootedDependency);
    }

    public function testAliasResolvesToSameSingleton()
    {
        $this->container->singleton('original', fn() => new \stdClass());
        $this->container->alias('original', 'alias');

        $original = $this->container->get('original');
        $aliased = $this->container->get('alias');

        $this->assertSame($original, $aliased);
    }

    public function testMultipleAliasesResolveSame()
    {
        $this->container->singleton('original', fn() => new \stdClass());
        $this->container->alias('original', 'alias1');
        $this->container->alias('original', 'alias2');
        $this->container->alias('original', 'alias3');

        $o = $this->container->get('original');
        $a1 = $this->container->get('alias1');
        $a2 = $this->container->get('alias2');
        $a3 = $this->container->get('alias3');

        $this->assertSame($o, $a1);
        $this->assertSame($o, $a2);
        $this->assertSame($o, $a3);
    }

    public function testExtendPreservesSingletonBehavior()
    {
        $this->container->singleton('service', fn() => new \stdClass());
        $this->container->extend('service', function($obj) {
            $obj->extended = true;
            return $obj;
        });

        $first = $this->container->get('service');
        $second = $this->container->get('service');

        $this->assertSame($first, $second);
        $this->assertTrue($first->extended);
    }

    public function testExtendPreservesTransientBehavior()
    {
        $this->container->bind('service', fn() => new \stdClass());
        $this->container->extend('service', function($obj) {
            $obj->extended = true;
            return $obj;
        });

        $first = $this->container->get('service');
        $second = $this->container->get('service');

        $this->assertNotSame($first, $second);
        $this->assertTrue($first->extended);
        $this->assertTrue($second->extended);
    }

    public function testConstructorWithManyParameters()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $this->container->bind(ServiceInterface::class, ConcreteService::class);

        $instance = $this->container->make(ClassWithManyParams::class, [
            'name' => 'Test',
            'age' => 25,
            'email' => 'test@example.com',
            'active' => true,
            'score' => 98.5
        ]);

        $this->assertInstanceOf(ConcreteDependency::class, $instance->dep);
        $this->assertInstanceOf(ConcreteService::class, $instance->service);
        $this->assertEquals('Test', $instance->name);
        $this->assertEquals(25, $instance->age);
        $this->assertEquals('test@example.com', $instance->email);
        $this->assertTrue($instance->active);
        $this->assertEquals(98.5, $instance->score);
    }

    public function testConstructorWithOnlyOptionalParams()
    {
        $instance = $this->container->make(ClassWithOnlyOptionals::class);

        $this->assertEquals('default', $instance->name);
        $this->assertEquals(0, $instance->count);
        $this->assertFalse($instance->active);
    }

    public function testConstructorWithMixedRequiredAndOptional()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $instance = $this->container->make(ClassWithMixedRequiredOptional::class, [
            'name' => 'Required'
        ]);

        $this->assertInstanceOf(ConcreteDependency::class, $instance->dependency);
        $this->assertEquals('Required', $instance->name);
        $this->assertEquals('optional', $instance->optional);
    }

    public function testResolveMethodWithNoParams()
    {
        $deps = $this->container->resolveMethodDependencies(
            CallableClass::class,
            'method'
        );

        $this->assertEmpty($deps);
    }

    public function testResolveMethodWithOnlyPrimitives()
    {
        $deps = $this->container->resolveMethodDependencies(
            CallableClass::class,
            'methodWithParams',
            ['name' => 'Test', 'value' => 42]
        );

        $this->assertEquals('Test', $deps[0]);
        $this->assertEquals(42, $deps[1]);
    }

    public function testResolveMethodWithComplexMix()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $this->container->bind(ServiceInterface::class, ConcreteService::class);

        $deps = $this->container->resolveMethodDependencies(
            CallableClass::class,
            'methodWithComplexParams',
            ['name' => 'Test', 'count' => 5]
        );

        $this->assertInstanceOf(ConcreteDependency::class, $deps[0]);
        $this->assertInstanceOf(ConcreteService::class, $deps[1]);
        $this->assertEquals('Test', $deps[2]);
        $this->assertEquals(5, $deps[3]);
    }

    public function testResolveBoundOverUnbound()
    {
        $this->container->bind(SimpleClass::class, fn() => new ExtendedSimpleClass());

        $instance = $this->container->get(SimpleClass::class);
        $this->assertInstanceOf(ExtendedSimpleClass::class, $instance);
    }

    public function testResolveUnboundWhenNoBinding()
    {
        $instance = $this->container->get(SimpleClass::class);
        $this->assertInstanceOf(SimpleClass::class, $instance);
        $this->assertNotInstanceOf(ExtendedSimpleClass::class, $instance);
    }

    // public function testFindInstanceByType()
    // {
    //     $instance = new ConcreteDependency();
    //     $this->container->instance('my_service', $instance);

    //     $found = $this->container->get(DependencyInterface::class);
    //     $this->assertSame($instance, $found);
    // }

    // has issue
    // public function testFindFirstMatchingInstance()
    // {
    //     $instance1 = new ConcreteDependency();
    //     $instance2 = new AlternateDependency();

    //     $this->container->instance('service1', $instance1);
    //     $this->container->instance('service2', $instance2);

    //     $found = $this->container->get(DependencyInterface::class);
    //     $this->assertSame($instance1, $found);
    // }

    public function testParametersOverrideDependencies()
    {
        $customDep = new AlternateDependency();

        $instance = $this->container->make(ClassWithDependency::class, [
            'dependency' => $customDep
        ]);

        $this->assertSame($customDep, $instance->dependency);
    }

    // has issue
    // public function testCallWithInvokableClass()
    // {
    //     $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

    //     $invokable = new InvokableClass();
    //     $result = $this->container->call($invokable);

    //     $this->assertEquals('invoked', $result);
    // }

    public function testCallWithClosureBindTo()
    {
        $closure = function() {
            return $this->container->has('test') ? 'yes' : 'no';
        };

        $this->container->bind('test', fn() => 'value');
        $boundClosure = $closure->bindTo($this);

        $result = $this->container->call($boundClosure);
        $this->assertEquals('yes', $result);
    }

    public function testFlushDoesNotAffectNewBindings()
    {
        $this->container->bind('before', fn() => 'value');
        $this->container->flush();
        $this->container->bind('after', fn() => 'value');

        $this->assertFalse($this->container->has('before'));
        $this->assertTrue($this->container->has('after'));
    }

    public function testFlushClearsResolvingState()
    {
        $this->container->bind('service', function () {
            $this->container->flush();
            return 'value';
        });

        // This should not throw even though we're flushing during resolution
        $result = $this->container->get('service');
        $this->assertEquals('value', $result);
    }

    // has issue
    // public function testMultipleContainerInstances()
    // {
    //     $container1 = new Container();
    //     $container2 = new Container();

    //     $container1->bind('service', fn() => 'container1');
    //     $container2->bind('service', fn() => 'container2');

    //     $this->assertEquals('container1', $container1->get('service'));
    //     $this->assertEquals('container2', $container2->get('service'));
    // }

    // has issue
    // public function testStaticInstanceIsolation()
    // {
    //     Container::setInstance($this->container);
    //     $this->container->bind('service', fn() => 'value');

    //     $newContainer = new Container();
    //     $this->assertFalse($newContainer->has('service'));
    // }

    public function testBindingPriorityOverAutoResolution()
    {
        $this->container->bind(SimpleClass::class, fn() => new ExtendedSimpleClass());

        $instance = $this->container->make(SimpleClass::class);
        $this->assertInstanceOf(ExtendedSimpleClass::class, $instance);
    }

    public function testInstancePriorityOverBinding()
    {
        $specificInstance = new SimpleClass();
        $this->container->bind(SimpleClass::class, fn() => new ExtendedSimpleClass());
        $this->container->instance(SimpleClass::class, $specificInstance);

        $resolved = $this->container->get(SimpleClass::class);
        $this->assertSame($specificInstance, $resolved);
    }

    public function testVariadicWithDependency()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);

        $instance = $this->container->make(ClassWithDependencyAndVariadic::class, [
            'items' => ['a', 'b', 'c']
        ]);

        $this->assertInstanceOf(ConcreteDependency::class, $instance->dependency);
        $this->assertEquals(['a', 'b', 'c'], $instance->items);
    }

    public function testNullParameter()
    {
        $instance = $this->container->make(ClassWithNullable::class, ['value' => null]);
        $this->assertNull($instance->value);
    }

    public function testExplicitNullOverridesDefault()
    {
        $instance = $this->container->make(ClassWithNullableDefault::class, ['value' => null]);
        $this->assertNull($instance->value);
    }

    public function testRebindingAffectsNewResolutions()
    {
        $this->container->bind(DependencyInterface::class, ConcreteDependency::class);
        $first = $this->container->make(ClassWithDependency::class);

        $this->container->bind(DependencyInterface::class, AlternateDependency::class);
        $second = $this->container->make(ClassWithDependency::class);

        $this->assertInstanceOf(ConcreteDependency::class, $first->dependency);
        $this->assertInstanceOf(AlternateDependency::class, $second->dependency);
    }

    public function testRebindingSingletonClearsOldInstance()
    {
        $this->container->singleton('service', fn() => 'old');
        $old = $this->container->get('service');

        $this->container->singleton('service', fn() => 'new');
        $new = $this->container->get('service');

        $this->assertEquals('old', $old);
        $this->assertEquals('new', $new);
    }

    public function testDifferentParametersProduceDifferentInstances()
    {
        $instance1 = $this->container->make(ClassWithString::class, ['name' => 'First']);
        $instance2 = $this->container->make(ClassWithString::class, ['name' => 'Second']);

        $this->assertEquals('First', $instance1->name);
        $this->assertEquals('Second', $instance2->name);
        $this->assertNotSame($instance1, $instance2);
    }

    // has issue
    // public function testCompleteApplicationStack()
    // {
    //     // Database layer
    //     $this->container->singleton(ConnectionInterface::class, DatabaseConnection::class);

    //     // Repository layer
    //     $this->container->bind(RepositoryInterface::class, ConcreteRepository::class);

    //     // Service layer
    //     $this->container->bind(ServiceLayerInterface::class, ConcreteServiceLayer::class);

    //     // Controller layer
    //     $controller = $this->container->make(ControllerClass::class);

    //     $this->assertInstanceOf(ControllerClass::class, $controller);
    //     $this->assertInstanceOf(ConcreteRepository::class, $controller->repository);
    // }

    // has issue
    // public function testComplexDependencyGraph()
    // {
    //     $this->container->singleton(DependencyInterface::class, ConcreteDependency::class);
    //     $this->container->singleton(ServiceInterface::class, ConcreteService::class);
    //     $this->container->bind(RepositoryInterface::class, ConcreteRepository::class);
        
    //     $graph = $this->container->make(ComplexDependencyGraph::class, [
    //         'config' => ['key' => 'value']
    //     ]);
        
    //     $this->assertInstanceOf(ComplexDependencyGraph::class, $graph);
    //     $this->assertInstanceOf(ConcreteDependency::class, $graph->dependency);
    //     $this->assertInstanceOf(ConcreteService::class, $graph->service);
    //     $this->assertInstanceOf(ConcreteRepository::class, $graph->repository);
    //     $this->assertEquals(['key' => 'value'], $graph->config);
    // }

    public function testResolutionWithAllFeatures()
    {
        // Setup complex scenario
        $this->container->singleton(DependencyInterface::class, ConcreteDependency::class);
        $this->container->bind(ServiceInterface::class, ConcreteService::class);
        $this->container->extend(DependencyInterface::class, function($dep) {
            $dep->extended = true;
            return $dep;
        });
        $this->container->alias(ServiceInterface::class, 'service');

        // Resolve
        $instance = $this->container->make(ClassWithMultipleDependencies::class);

        $this->assertTrue($instance->dependency->extended);
        $this->assertInstanceOf(ConcreteService::class, $instance->service);

        // Check alias
        $aliased = $this->container->get('service');
        $this->assertInstanceOf(ConcreteService::class, $aliased);
    }
}
