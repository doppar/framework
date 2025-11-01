<?php

namespace Tests\Unit\Application;

use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

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

    public function testSingletonInstance()
    {
        $instance1 = Container::getInstance();
        $instance2 = Container::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testSetInstance()
    {
        $newContainer = new Container();
        Container::setInstance($newContainer);

        $this->assertSame($newContainer, Container::getInstance());
    }

    public function testBindAndResolve()
    {
        $this->container->bind('test', fn() => 'test value');

        $this->assertEquals('test value', $this->container->get('test'));
    }

    public function testBindClass()
    {
        $this->container->bind(TestInterface::class, TestClass::class);

        $instance = $this->container->get(TestInterface::class);
        $this->assertInstanceOf(TestClass::class, $instance);
    }

    public function testSingletonBinding()
    {
        $this->container->singleton('singleton', fn() => new \stdClass());

        $instance1 = $this->container->get('singleton');
        $instance2 = $this->container->get('singleton');

        $this->assertSame($instance1, $instance2);
    }

    public function testHas()
    {
        $this->container->bind('test', fn() => 'value');

        $this->assertTrue($this->container->has('test'));
        $this->assertFalse($this->container->has('nonexistent'));
    }

    public function testResolveUnboundClass()
    {
        $instance = $this->container->get(TestClass::class);
        $this->assertInstanceOf(TestClass::class, $instance);
    }

    public function testResolveWithDependencies()
    {
        $this->container->bind(TestInterface::class, TestClass::class);
        $instance = $this->container->make(ClassWithDependency::class);
        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(TestClass::class, $instance->dependency);
    }

    public function testResolveWithPrimitiveParameters()
    {
        $instance = $this->container->make(ClassWithPrimitives::class, ['param1' => 'value1', 'param2' => 42]);
        $this->assertEquals('value1', $instance->param1);
        $this->assertEquals(42, $instance->param2);
    }

    public function testArrayAccess()
    {
        $this->container['array_test'] = fn() => 'array access value';

        $this->assertTrue(isset($this->container['array_test']));
        $this->assertEquals('array access value', $this->container['array_test']);

        unset($this->container['array_test']);
        $this->assertFalse(isset($this->container['array_test']));
    }

    public function testWhenConditionTrue()
    {
        $result = $this->container->when(true);
        $result->bind('conditional', fn() => 'condition met');

        $this->assertTrue($this->container->has('conditional'));
    }

    public function testWhenConditionFalse()
    {
        $result = $this->container->when(false);
        $this->assertNull($result);
    }

    public function testWhenWithCallable()
    {
        $result = $this->container->when(fn() => true);
        $result->bind('callable_conditional', fn() => 'callable condition met');

        $this->assertTrue($this->container->has('callable_conditional'));
    }

    public function testResolveAbstractClassThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->container->get(AbstractClass::class);
    }

    public function testResolveNonExistentClassThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->container->get('NonexistentClass');
    }

    public function testMakeAliasForGet()
    {
        $this->container->bind('make_test', fn() => 'make test');

        $this->assertEquals('make test', $this->container->make('make_test'));
    }

    public function testBuildWithParameters()
    {
        $instance = $this->container->make(ClassWithPrimitives::class, ['value1', 42]);
        $this->assertEquals('value1', $instance->param1);
        $this->assertEquals(42, $instance->param2);
    }

    public function testResolveInterfaceThrowsExceptionWhenNotBound()
    {
        $this->expectException(RuntimeException::class);
        $this->container->get(UnboundInterface::class);
    }

    public function testShareMethod()
    {
        $instance = new \stdClass();
        $instance->name = 'test';

        $this->container->share('shared', $instance);

        $resolved = $this->container->get('shared');
        $this->assertSame($instance, $resolved);
        $this->assertEquals('test', $resolved->name);
    }

    public function testExtendBinding()
    {
        $this->container->bind('service', fn() => 'original');
        $this->container->extend('service', fn($original) => $original . ' extended');

        $result = $this->container->get('service');
        $this->assertEquals('original extended', $result);
    }

    public function testExtendUnboundThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->container->extend('nonexistent', fn($original) => $original);
    }

    public function testAlias()
    {
        $this->container->bind('original', fn() => 'original value');
        $this->container->alias('original', 'aliased');

        $this->assertEquals('original value', $this->container->get('aliased'));
    }

    public function testCallWithDependencies()
    {
        $this->container->bind(TestInterface::class, TestClass::class);
        $result = $this->container->call(function (TestInterface $dependency, string $param) {
            return get_class($dependency) . ':' . $param;
        }, ['param' => 'test']);

        $this->assertStringContainsString('TestClass:test', $result);
    }

    public function testCallWithMethodDependencies()
    {
        $this->container->bind(TestInterface::class, TestClass::class);

        $callable = [new ClassWithMethod(), 'methodWithDependency'];
        $result = $this->container->call($callable, ['extra' => 'value']);

        $this->assertEquals('Tests\Unit\Application\TestClass:value', $result);
    }

    public function testIsSingleton()
    {
        $this->container->bind('transient', fn() => 'transient');
        $this->container->singleton('singleton', fn() => 'singleton');

        $this->assertFalse($this->container->isSingleton('transient'));
        $this->assertTrue($this->container->isSingleton('singleton'));
        $this->assertFalse($this->container->isSingleton('nonexistent'));
    }

    public function testResolveMethodDependencies()
    {
        $this->container->bind(TestInterface::class, TestClass::class);

        $dependencies = $this->container->resolveMethodDependencies(
            ClassWithMethod::class,
            'methodWithDependency',
            ['extra' => 'test_value']
        );

        $this->assertCount(2, $dependencies);
        $this->assertInstanceOf(TestClass::class, $dependencies[0]);
        $this->assertEquals('test_value', $dependencies[1]);
    }

    public function testIsResolving()
    {
        $this->container->bind('slow_service', function () {
            return $this->container->isResolving('slow_service') ? 'resolving' : 'not resolving';
        });

        $result = $this->container->get('slow_service');
        $this->assertEquals('resolving', $result);
    }

    public function testRegisterServiceProvider()
    {
        $this->container->register(TestServiceProvider::class);

        $this->assertTrue($this->container->has('from_provider'));
        $this->assertEquals('provided_value', $this->container->get('from_provider'));
    }

    public function testResetMethod()
    {
        $this->container->bind('test', fn() => 'value');
        $this->container->singleton('singleton', fn() => new \stdClass());

        $this->container->get('singleton'); // Create instance

        $this->assertTrue($this->container->has('test'));
        $this->assertTrue($this->container->hasInstance('singleton'));

        $this->container->reset();

        $this->assertFalse($this->container->has('test'));
        $this->assertFalse($this->container->hasInstance('singleton'));
    }

    public function testResolvedMethod()
    {
        $this->container->singleton('singleton', fn() => new \stdClass());

        $this->assertFalse($this->container->resolved('singleton'));

        $this->container->get('singleton');

        $this->assertTrue($this->container->resolved('singleton'));
        $this->assertFalse($this->container->resolved('nonexistent'));
    }
}

// Test classes and interfaces for dependency injection

interface TestInterface {}
interface UnboundInterface {}

class TestClass implements TestInterface {}

abstract class AbstractClass {}

class ClassWithDependency
{
    public TestInterface $dependency;

    public function __construct(TestInterface $dependency)
    {
        $this->dependency = $dependency;
    }
}

class ClassWithPrimitives
{
    public string $param1;
    public int $param2;

    public function __construct(string $param1, int $param2)
    {
        $this->param1 = $param1;
        $this->param2 = $param2;
    }
}

class SpecialTestClass implements TestInterface {}

class ClassWithMethod
{
    public function methodWithDependency(TestInterface $dependency, string $extra): string
    {
        return get_class($dependency) . ':' . $extra;
    }
}

class TestServiceProvider
{
    public function register(Container $container): void
    {
        $container->bind('from_provider', fn() => 'provided_value');
    }

    public function boot(): void
    {
        // Boot logic if needed
    }
}

class ClassWithOptionalDependency
{
    public $dependency;
    public $optional;

    public function __construct(TestInterface $dependency, string $optional = 'default')
    {
        $this->dependency = $dependency;
        $this->optional = $optional;
    }
}

class ClassWithVariadic
{
    public $items;

    public function __construct(string ...$items)
    {
        $this->items = $items;
    }
}