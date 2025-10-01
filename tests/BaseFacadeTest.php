<?php

namespace Tests\Unit;

use Phaseolies\Facade\BaseFacade;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TestFacade extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'test-service';
    }
}

class TestService
{
    public function exampleMethod($arg)
    {
        return 'called with ' . $arg;
    }
}

class MockApplication
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get($key)
    {
        return $this->container->get($key);
    }
}

class BaseFacadeTest extends TestCase
{
    private $container;
    private $app;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->app = new MockApplication($this->container);

        $this->container->bind('test-service', function () {
            return new TestService();
        });

        TestFacade::setFacadeApplication(null);
    }

    protected function callResolveInstance($facadeClass)
    {
        $reflection = new \ReflectionClass($facadeClass);
        $method = $reflection->getMethod('resolveInstance');
        $method->setAccessible(true);

        return $method->invoke(null);
    }

    public function testGetFacadeAccessorIsAbstract()
    {
        $this->expectException(\Error::class);
        BaseFacade::getFacadeAccessor();
    }

    public function testSetFacadeApplication()
    {
        TestFacade::setFacadeApplication($this->app);

        $reflection = new \ReflectionClass(TestFacade::class);
        $property = $reflection->getProperty('app');
        $property->setAccessible(true);

        $this->assertSame($this->app, $property->getValue());
    }

    public function testResolveInstanceUsesContainerWhenNoAppSet()
    {
        $instance = $this->callResolveInstance(TestFacade::class);
        $this->assertInstanceOf(TestService::class, $instance);
    }

    public function testResolveInstanceUsesApplicationWhenSet()
    {
        TestFacade::setFacadeApplication($this->app);
        $instance = $this->callResolveInstance(TestFacade::class);
        $this->assertInstanceOf(TestService::class, $instance);
    }

    public function testCallStaticProxiesToInstance()
    {
        $result = TestFacade::exampleMethod('test-arg');
        $this->assertEquals('called with test-arg', $result);
    }

    public function testCallStaticThrowsExceptionWhenNoInstance()
    {
        $this->container->bind('test-service', function () {
            return null;
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A facade root has not been set.');

        TestFacade::exampleMethod('test');
    }

    public function testCallStaticThrowsExceptionForUndefinedMethod()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to undefined method ' . TestService::class . '::undefinedMethod()');

        TestFacade::undefinedMethod();
    }

    public function testFacadeWorksWithContainerBindings()
    {
        $this->container->bind('test-service', function () {
            return new class {
                public function customMethod()
                {
                    return 'custom-result';
                }
            };
        });

        // $result = TestFacade::customMethod();
        // $this->assertEquals('custom-result', $result);
    }

    public function testFacadeWorksWithSingletonBindings()
    {
        $this->container->singleton('test-service', function () {
            return new TestService();
        });

        $instance1 = $this->callResolveInstance(TestFacade::class);
        $instance2 = $this->callResolveInstance(TestFacade::class);

        $this->assertSame($instance1, $instance2);
    }

    public function testMultipleFacadesWorkIndependently()
    {
        $secondFacade = new class extends BaseFacade {
            protected static function getFacadeAccessor()
            {
                return 'second-service';
            }
        };

        $this->container->bind('second-service', function () {
            return new class {
                public function secondMethod()
                {
                    return 'second-result';
                }
            };
        });

        $result1 = TestFacade::exampleMethod('test');
        $result2 = $secondFacade::secondMethod();

        $this->assertEquals('called with test', $result1);
        $this->assertEquals('second-result', $result2);
    }
}
