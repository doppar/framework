<?php

namespace Tests\Unit;

use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
