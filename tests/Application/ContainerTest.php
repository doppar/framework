<?php

namespace Tests\Unit\Application;

use Tests\Application\Mock\SimpleClass;
use Tests\Application\Mock\Interfaces\TestInterface;
use Tests\Application\Mock\Counter;
use Tests\Application\Mock\ConcreteImplementation;
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
}
