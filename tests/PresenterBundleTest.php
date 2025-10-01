<?php

namespace Tests\Unit;

use Phaseolies\Support\Presenter\PresenterBundle;
use Phaseolies\Support\Presenter\Presenter;
use Phaseolies\Support\Collection;
use Phaseolies\Http\Request;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;

class PresenterBundleTest extends TestCase
{
    private $container;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $this->container = new Container();
        $this->container->bind('request', fn() => new Request());
    }

    protected function createTestPresenterClass()
    {
        return new class(['id' => 1]) extends Presenter {
            protected function toArray(): array
            {
                return [
                    'id' => $this->presenter['id'] ?? null,
                    'name' => $this->presenter['name'] ?? null,
                    'email' => $this->presenter['email'] ?? null,
                ];
            }
        };
    }

    protected function createTestPresenterBundle($data, $presenterClass = null)
    {
        $presenterClass = $presenterClass ?? $this->createTestPresenterClass();
        return new PresenterBundle($data, get_class($presenterClass));
    }

    public function testInitializationWithArray()
    {
        $data = [['id' => 1], ['id' => 2]];
        $bundle = $this->createTestPresenterBundle($data);

        $this->assertInstanceOf(PresenterBundle::class, $bundle);
    }

    public function testInitializationWithCollection()
    {
        $presenterClass = $this->createTestPresenterClass();
        $collection = new Collection(get_class($presenterClass), [['id' => 1], ['id' => 2]]);
        $bundle = new PresenterBundle($collection, get_class($presenterClass));

        $this->assertInstanceOf(PresenterBundle::class, $bundle);
    }

    public function testInitializationWithPaginatedArray()
    {
        $data = [
            'data' => [['id' => 1], ['id' => 2]],
            'current_page' => 1,
            'per_page' => 15,
            'total' => 30
        ];
        $bundle = $this->createTestPresenterBundle($data);

        $this->assertInstanceOf(PresenterBundle::class, $bundle);
        $result = $bundle->toPaginatedResponse();
        $this->assertArrayHasKey('meta', $result);
        $this->assertEquals(1, $result['meta']['current_page']);
    }

    public function testExceptMethod()
    {
        $data = [['id' => 1, 'name' => 'Test', 'email' => 'test@example.com']];
        $bundle = $this->createTestPresenterBundle($data);

        $result = $bundle->except(['email'])->jsonSerialize();
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayNotHasKey('email', $result[0]);
    }

    public function testOnlyMethod()
    {
        $data = [['id' => 1, 'name' => 'Test', 'email' => 'test@example.com']];
        $bundle = $this->createTestPresenterBundle($data);

        $result = $bundle->only(['id'])->jsonSerialize();
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('name', $result[0]);
        $this->assertArrayNotHasKey('email', $result[0]);
    }

    public function testPreserveKeys()
    {
        $data = ['a' => ['id' => 1], 'b' => ['id' => 2]];
        $bundle = $this->createTestPresenterBundle($data);

        $result = $bundle->preserveKeys()->jsonSerialize();
        $this->assertArrayHasKey('a', $result);
        $this->assertArrayHasKey('b', $result);
    }

    public function testLazySerialization()
    {
        $data = [['id' => 1], ['id' => 2], ['id' => 3]];
        $bundle = $this->createTestPresenterBundle($data);

        $result = $bundle->lazy()->jsonSerialize();
        $this->assertCount(3, $result);
    }

    public function testJsonSerializeWithOnlyAndExcept()
    {
        $data = [
            ['id' => 1, 'name' => 'Test 1', 'email' => 'test1@example.com'],
            ['id' => 2, 'name' => 'Test 2', 'email' => 'test2@example.com']
        ];
        $bundle = $this->createTestPresenterBundle($data);

        $result = $bundle->only(['id', 'name'])->except(['name'])->jsonSerialize();
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayNotHasKey('name', $result[0]);
        $this->assertArrayNotHasKey('email', $result[0]);
    }

    public function testIsPaginatedArrayDetection()
    {
        $paginatedData = [
            'data' => [['id' => 1]],
            'current_page' => 1,
            'total' => 10
        ];
        $bundle = $this->createTestPresenterBundle([]);

        $reflection = new \ReflectionClass($bundle);
        $method = $reflection->getMethod('isPaginatedArray');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($bundle, $paginatedData));
        $this->assertFalse($method->invoke($bundle, [['id' => 1]]));
    }

    public function testExtractPaginationMeta()
    {
        $paginatedData = [
            'data' => [['id' => 1], ['id' => 2]],
            'current_page' => 2,
            'per_page' => 2,
            'total' => 10,
            'last_page' => 5
        ];
        $bundle = $this->createTestPresenterBundle([]);

        $reflection = new \ReflectionClass($bundle);
        $method = $reflection->getMethod('extractPaginationMeta');
        $method->setAccessible(true);

        $meta = $method->invoke($bundle, $paginatedData);

        $this->assertEquals(2, $meta['current_page']);
        $this->assertEquals(2, $meta['per_page']);
        $this->assertEquals(10, $meta['total']);
        $this->assertEquals(5, $meta['last_page']);
        $this->assertNotNull($meta['next_page_url']);
        $this->assertNotNull($meta['prev_page_url']);
    }

    public function testInvalidCollectionTypeThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);

        $presenterClass = $this->createTestPresenterClass();
        new PresenterBundle('invalid', get_class($presenterClass));
    }
}
