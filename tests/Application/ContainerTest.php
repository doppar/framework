<?php

namespace Tests\Unit\Application;

use Tests\Application\Mock\SimpleClass;
use Tests\Application\Mock\Services\ConcreteService;
use Tests\Application\Mock\Services\AlternateDependency;
use Tests\Application\Mock\Interfaces\TestInterface;
use Tests\Application\Mock\Interfaces\ServiceInterface;
use Tests\Application\Mock\Interfaces\DependencyInterface;
use Tests\Application\Mock\Counter;
use Tests\Application\Mock\ConcreteImplementation;
use Tests\Application\Mock\ConcreteDependency;
use Tests\Application\Mock\ClassWithVariadic;
use Tests\Application\Mock\ClassWithTypedVariadic;
use Tests\Application\Mock\ClassWithString;
use Tests\Application\Mock\ClassWithNullable;
use Tests\Application\Mock\ClassWithNestedDependency;
use Tests\Application\Mock\ClassWithMultipleDependencies;
use Tests\Application\Mock\ClassWithMixedParams;
use Tests\Application\Mock\ClassWithInt;
use Tests\Application\Mock\ClassWithFloat;
use Tests\Application\Mock\ClassWithDependencyChain;
use Tests\Application\Mock\ClassWithDependency;
use Tests\Application\Mock\ClassWithDefaults;
use Tests\Application\Mock\ClassWithBool;
use Tests\Application\Mock\ClassWithArray;
use Tests\Application\Mock\CallableClass;
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

    //=========================================
    // HAS INSTANCE TESTS
    //=========================================

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
}
