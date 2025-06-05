<?php

namespace Tests\Unit;

use Phaseolies\Cache\CacheStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use DateInterval;
use Closure;

class CacheStoreTest extends TestCase
{
    private CacheStore $cache;
    private ArrayAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new ArrayAdapter();
        $this->cache = new CacheStore($this->adapter, 'test_');
    }

    public function testGetReturnsDefaultForMissingKey()
    {
        $result = $this->cache->get('nonexistent_key', 'default_value');
        $this->assertEquals('default_value', $result);
    }

    public function testSetAndGet()
    {
        $this->assertTrue($this->cache->set('test_key', 'test_value'));
        $this->assertEquals('test_value', $this->cache->get('test_key'));
    }

    public function testSetWithTtl()
    {
        $this->assertTrue($this->cache->set('ttl_key', 'value', 60));
        $this->assertEquals('value', $this->cache->get('ttl_key'));
    }

    public function testSetWithDateIntervalTtl()
    {
        $ttl = new DateInterval('PT1M'); // 1 minute
        $this->assertTrue($this->cache->set('interval_key', 'value', $ttl));
        $this->assertEquals('value', $this->cache->get('interval_key'));
    }

    public function testDelete()
    {
        $this->cache->set('to_delete', 'value');
        $this->assertTrue($this->cache->delete('to_delete'));
        $this->assertNull($this->cache->get('to_delete'));
    }

    public function testClear()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->assertTrue($this->cache->clear());
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testGetMultiple()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $result = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');
        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'default'
        ], $result);
    }

    public function testSetMultiple()
    {
        $values = [
            'multi1' => 'value1',
            'multi2' => 'value2'
        ];

        $this->assertTrue($this->cache->setMultiple($values));
        $this->assertEquals('value1', $this->cache->get('multi1'));
        $this->assertEquals('value2', $this->cache->get('multi2'));
    }

    public function testDeleteMultiple()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $this->assertTrue($this->cache->deleteMultiple(['key1', 'key2']));
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        $this->assertTrue($this->cache->has('key3'));
    }

    public function testHas()
    {
        $this->assertFalse($this->cache->has('some_key'));
        $this->cache->set('some_key', 'value');
        $this->assertTrue($this->cache->has('some_key'));
    }

    public function testIncrement()
    {
        $this->cache->set('counter', 5);
        $this->assertEquals(6, $this->cache->increment('counter'));
        $this->assertEquals(8, $this->cache->increment('counter', 2));
        $this->assertEquals(8, $this->cache->get('counter'));
    }

    public function testIncrementReturnsFalseForNonExistentKey()
    {
        $this->assertFalse($this->cache->increment('nonexistent'));
    }

    public function testDecrement()
    {
        $this->assertTrue($this->cache->set('counter', 5));

        $result = $this->cache->decrement('counter');
        $this->assertEquals(4, $result);
        $this->assertEquals(4, $this->cache->get('counter'));

        $result = $this->cache->decrement('counter', 2);
        $this->assertEquals(2, $result);
        $this->assertEquals(2, $this->cache->get('counter'));

        $result = $this->cache->decrement('nonexistent');
        $this->assertFalse($result);
    }

    public function testAdd()
    {
        $this->assertTrue($this->cache->add('new_key', 'value'));

        $this->assertEquals('value', $this->cache->get('new_key'));

        $this->assertFalse($this->cache->add('new_key', 'new_value'));

        $this->assertEquals('value', $this->cache->get('new_key'));

        $this->assertTrue($this->cache->add('ttl_key', 'ttl_value', 60));
        $this->assertEquals('ttl_value', $this->cache->get('ttl_key'));
    }

    public function testForever()
    {
        $this->assertTrue($this->cache->forever('forever_key', 'forever_value'));

        $this->assertEquals('forever_value', $this->cache->get('forever_key'));

        $prefixedKey = 'test_forever_key';
        $this->assertTrue($this->adapter->hasItem($prefixedKey));
    }

    public function testForget()
    {
        $this->assertTrue($this->cache->set('to_forget', 'value'));
        $this->assertEquals('value', $this->cache->get('to_forget'));

        $this->assertTrue($this->cache->forget('to_forget'));

        $this->assertNull($this->cache->get('to_forget'));

        $prefixedKey = 'test_to_forget';
        $this->assertFalse($this->adapter->hasItem($prefixedKey));

        $this->assertFalse($this->cache->forget('nonexistent_key'));
    }

    public function testStash()
    {
        $callback = function () {
            return 'callback_value';
        };

        // First call should execute callback
        $result = $this->cache->stash('stash_key', 60, $callback);
        $this->assertEquals('callback_value', $result);

        // Second call should get from cache
        $result = $this->cache->stash('stash_key', 60, function () {
            return 'new_value';
        });
        $this->assertEquals('callback_value', $result);
    }

    public function testStashForever()
    {
        $callback = function () {
            return 'forever_value';
        };

        $result = $this->cache->stashForever('forever_stash', $callback);
        $this->assertEquals('forever_value', $result);
        $this->assertEquals('forever_value', $this->cache->get('forever_stash'));
    }

    public function testStashWhen()
    {
        $callback = function () {
            return 'conditional_value';
        };

        // When condition is true, should cache
        $result = $this->cache->stashWhen('conditional_key', $callback, true, 60);
        $this->assertEquals('conditional_value', $result);
        $this->assertEquals('conditional_value', $this->cache->get('conditional_key'));

        // When condition is false, shouldn't cache
        $result = $this->cache->stashWhen('no_cache_key', $callback, false);
        $this->assertEquals('conditional_value', $result);
        $this->assertNull($this->cache->get('no_cache_key'));
    }

    public function testInvalidKeyThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->cache->get('invalid/key');
    }

    public function testPrefixIsApplied()
    {
        $this->cache->set('prefixed', 'value');
        $this->assertTrue($this->adapter->hasItem('test_prefixed'));
    }

    public function testGetAdapter()
    {
        $this->assertSame($this->adapter, $this->cache->getAdapter());
    }
}
