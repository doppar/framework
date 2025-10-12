<?php

namespace Tests\Unit;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Phaseolies\Cache\Lock\AtomicLock;
use Phaseolies\Cache\CacheStore;
use PHPUnit\Framework\TestCase;
use DateInterval;

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

    // =======================================
    // Atomic Lock Test
    // =======================================
    public function testLockCreation()
    {
        $lock = $this->cache->locked('test_lock', 10);

        $this->assertInstanceOf(AtomicLock::class, $lock);
        $this->assertEquals('test_lock', $lock->getName());
        $this->assertEquals(10, $lock->getSeconds());
        $this->assertNotEmpty($lock->getOwner());
    }

    public function testLockCreationWithCustomOwner()
    {
        $lock = $this->cache->locked('test_lock', 10, 'custom_owner');

        $this->assertEquals('custom_owner', $lock->getOwner());
    }

    public function testLockAcquisition()
    {
        $lock = $this->cache->locked('test_lock', 10);

        $result = $lock->get();

        $this->assertTrue($result);
        $this->assertTrue($lock->isOwnedByCurrentProcess());
    }

    public function testLockAcquisitionFailsWhenAlreadyLocked()
    {
        $lock1 = $this->cache->locked('test_lock', 10);
        $lock2 = $this->cache->locked('test_lock', 10);

        $this->assertTrue($lock1->get());
        $this->assertFalse($lock2->get());
    }

    public function testLockRelease()
    {
        $lock = $this->cache->locked('test_lock', 10);

        $this->assertTrue($lock->get());
        $this->assertTrue($lock->release());
        $this->assertFalse($lock->isOwnedByCurrentProcess());
    }

    public function testLockReleaseFailsWhenNotOwner()
    {
        $lock1 = $this->cache->locked('test_lock', 10);
        $lock2 = $this->cache->locked('test_lock', 10);

        $this->assertTrue($lock1->get());
        $this->assertFalse($lock2->release());
    }

    public function testLockBlockingAcquisition()
    {
        $lock1 = $this->cache->locked('test_lock', 10);
        $lock2 = $this->cache->locked('test_lock', 10);

        $this->assertTrue($lock1->get());

        // Start a separate process to test blocking (simulated)
        $acquired = false;
        $startTime = time();

        // This should block for up to 1 second
        $result = $lock1->release();
        $this->assertTrue($result);

        $acquired = $lock2->block(1);
        $endTime = time();

        $this->assertTrue($acquired);
        $this->assertLessThan(2, $endTime - $startTime); // Should be less than 2 seconds
    }

    public function testLockBlockingTimeout()
    {
        $lock1 = $this->cache->locked('test_lock', 10);
        $lock2 = $this->cache->locked('test_lock', 10);

        $this->assertTrue($lock1->get());

        $startTime = time();
        $acquired = $lock2->block(1); // Try to acquire for 1 second
        $endTime = time();

        $this->assertFalse($acquired);
        $this->assertGreaterThanOrEqual(1, $endTime - $startTime);
    }

    public function testLockOwner()
    {
        $lock = $this->cache->locked('test_lock', 10);

        $this->assertTrue($lock->get());
        $this->assertEquals($lock->getOwner(), $lock->owner());
    }

    public function testLockOwnerWhenNotAcquired()
    {
        $lock = $this->cache->locked('test_lock', 10);

        $this->assertEmpty($lock->owner());
    }

    public function testLockIsOwnedByCurrentProcess()
    {
        $lock = $this->cache->locked('test_lock', 10);

        $this->assertFalse($lock->isOwnedByCurrentProcess());

        $this->assertTrue($lock->get());

        $this->assertTrue($lock->isOwnedByCurrentProcess());

        $this->assertTrue($lock->release());

        $this->assertFalse($lock->isOwnedByCurrentProcess());
    }

    public function testRestoreLockForNonExistentLock()
    {
        $restoredLock = $this->cache->restoreLock('nonexistent_lock', 'some_owner');

        $this->assertInstanceOf(AtomicLock::class, $restoredLock);
        $this->assertEquals('nonexistent_lock', $restoredLock->getName());
        $this->assertEquals('some_owner', $restoredLock->getOwner());
        $this->assertEquals(10, $restoredLock->getSeconds());
        $this->assertFalse($restoredLock->isOwnedByCurrentProcess());
    }

    public function testConcurrentLockOperations()
    {
        $lockName = 'concurrent_lock';

        // Simulate multiple processes trying to acquire the same lock
        $lock1 = $this->cache->locked($lockName, 5);
        $lock2 = $this->cache->locked($lockName, 5);
        $lock3 = $this->cache->locked($lockName, 5);

        // First lock should succeed
        $this->assertTrue($lock1->get());
        $this->assertTrue($lock1->isOwnedByCurrentProcess());

        // Others should fail
        $this->assertFalse($lock2->get());
        $this->assertFalse($lock2->isOwnedByCurrentProcess());
        $this->assertFalse($lock3->get());
        $this->assertFalse($lock3->isOwnedByCurrentProcess());

        // Release first lock
        $this->assertTrue($lock1->release());

        // Now second lock should be able to acquire it
        $this->assertTrue($lock2->get());
        $this->assertTrue($lock2->isOwnedByCurrentProcess());
    }

    public function testLockExpiration()
    {
        $lock = $this->cache->locked('expiring_lock', 1); // 1 second TTL

        $this->assertTrue($lock->get());

        // Wait for lock to expire
        sleep(2);

        // Lock should have expired
        $this->assertFalse($lock->isOwnedByCurrentProcess());

        // Should be able to acquire the lock again
        $newLock = $this->cache->locked('expiring_lock', 10);
        $this->assertTrue($newLock->get());
    }

    public function testLockMultipleDifferentLocks()
    {
        $lock1 = $this->cache->locked('lock1', 10);
        $lock2 = $this->cache->locked('lock2', 10);

        // Both locks should be acquirable since they're different
        $this->assertTrue($lock1->get());
        $this->assertTrue($lock2->get());

        $this->assertTrue($lock1->isOwnedByCurrentProcess());
        $this->assertTrue($lock2->isOwnedByCurrentProcess());

        $this->assertTrue($lock1->release());
        $this->assertTrue($lock2->release());
    }

    public function testLockOwnerIsUniquePerInstance()
    {
        $lock1 = $this->cache->locked('lock_test', 5);
        $lock2 = $this->cache->locked('lock_test', 5);

        $this->assertNotEquals($lock1->getOwner(), $lock2->getOwner());
    }

    public function testLockCannotAcquireIfAlreadyExists()
    {
        $lock1 = $this->cache->locked('lock_test', 5);
        $this->assertTrue($lock1->get());

        $lock2 = $this->cache->locked('lock_test', 5);
        $this->assertFalse($lock2->get());

        $lock1->release();
    }

    public function testLockGetNameAndOwner()
    {
        $lock = $this->cache->locked('lock_test', 5);
        $this->assertEquals('lock_test', $lock->getName());
        $this->assertNotEmpty($lock->getOwner());
    }

    public function testReleaseReturnsFalseIfLockDoesNotExist()
    {
        $lock = $this->cache->locked('lock_test', 5);
        // Not acquired yet
        $this->assertFalse($lock->release());
    }

    public function testRestoreLockThrowsOnOwnerMismatch()
    {
        $lock = $this->cache->locked('lock_test', 5);
        $this->assertTrue($lock->get());

        $wrongOwner = 'fake_owner_123';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Lock owner mismatch/');
        $this->cache->restoreLock('lock_test', $wrongOwner);
    }


    public function testIsOwnedByCurrentProcess()
    {
        $lock = $this->cache->locked('lock_test', 5);
        $lock->get();

        $this->assertTrue($lock->isOwnedByCurrentProcess());

        // Create new lock with same name but different owner
        $lock2 = $this->cache->locked('lock_test', 5);
        $this->assertFalse($lock2->isOwnedByCurrentProcess());
    }

    public function testAtomicLockOwnerRetrieval()
    {
        $lock = $this->cache->locked('lock_test', 5);
        $this->assertTrue($lock->get());

        $ownerFromLock = $lock->getOwner();
        $ownerFromCache = $lock->owner();

        $this->assertEquals($ownerFromLock, $ownerFromCache);
    }

    public function testAtomicLockBlockWaitsUntilAvailable()
    {
        $lock1 = $this->cache->locked('lock_test', 1);
        $this->assertTrue($lock1->get());

        // Create another lock that will block until available
        $lock2 = $this->cache->locked('lock_test', 1);

        // Release the first lock after a short delay in another thread simulation
        // Since we can’t sleep in test too long, we manually simulate expiry
        sleep(1);
        $this->adapter->deleteItem('lock_test');

        $this->assertTrue($lock2->block(2));
        $this->assertFalse($this->adapter->hasItem('lock_test'));
        $this->assertTrue($lock2->release());
    }

    public function testLockOwnerGeneration()
    {
        $lock1 = $this->cache->locked('lock1', 10);
        $lock2 = $this->cache->locked('lock2', 10);

        $owner1 = $lock1->getOwner();
        $owner2 = $lock2->getOwner();

        $this->assertNotEquals($owner1, $owner2);

        // Each owner should be non-empty and unique
        $this->assertNotEmpty($owner1);
        $this->assertNotEmpty($owner2);

        // Should contain the process ID
        $this->assertStringContainsString((string) getmypid(), $owner1);
        $this->assertStringContainsString((string) getmypid(), $owner2);

        // Should be reasonably long (uniqid + _ + pid)
        $this->assertGreaterThan(10, strlen($owner1));
        $this->assertGreaterThan(10, strlen($owner2));
    }

    public function testRestoreLock()
    {
        $originalLock = $this->cache->locked('test_lock', 30, 'test_owner');
        $this->assertTrue($originalLock->get());

        // Restore the lock with the same owner
        $restoredLock = $this->cache->restoreLock('test_lock', 'test_owner');

        $this->assertInstanceOf(AtomicLock::class, $restoredLock);
        $this->assertEquals('test_lock', $restoredLock->getName());
        $this->assertEquals('test_owner', $restoredLock->getOwner());
        $this->assertEquals(30, $restoredLock->getSeconds());
        $this->assertTrue($restoredLock->isOwnedByCurrentProcess());
    }

    public function testLockDataStructure()
    {
        $lock = $this->cache->locked('test_lock', 30);

        $this->assertTrue($lock->get());

        $lockData = $this->cache->get('test_lock');
        $this->assertNotNull($lockData);

        $data = json_decode($lockData, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('owner', $data);
        $this->assertArrayHasKey('duration', $data);
        $this->assertArrayHasKey('acquired_at', $data);
        $this->assertEquals($lock->getOwner(), $data['owner']);
        $this->assertEquals(30, $data['duration']);
        $this->assertIsInt($data['acquired_at']);
    }
}
