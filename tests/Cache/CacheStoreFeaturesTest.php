<?php

namespace Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use Phaseolies\Cache\CacheStore;
use Phaseolies\Cache\TaggedCache;
use Phaseolies\DI\Container;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheInterface as ContractsCacheInterface;
use Tests\Support\MockContainer;

/**
 * An adapter that records how it is used: counts reads, and does not support namespaces.
 */
class SpyAdapter implements AdapterInterface, ContractsCacheInterface
{
    public int $hasItemCalls = 0;
    public int $getCalls = 0;

    public function __construct(public ArrayAdapter $inner = new ArrayAdapter())
    {
    }

    public function getItem(mixed $key): CacheItem
    {
        return $this->inner->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        return $this->inner->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        $this->hasItemCalls++;

        return $this->inner->hasItem($key);
    }

    public function clear(string $prefix = ''): bool
    {
        return $this->inner->clear($prefix);
    }

    public function deleteItem(string $key): bool
    {
        return $this->inner->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->inner->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->inner->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->inner->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->inner->commit();
    }

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        $this->getCalls++;

        return $this->inner->get($key, $callback, $beta, $metadata);
    }

    public function delete(string $key): bool
    {
        return $this->inner->delete($key);
    }
}

class CacheStoreFeaturesTest extends TestCase
{
    private ArrayAdapter $adapter;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());
        $this->adapter = new ArrayAdapter();
    }

    protected function tearDown(): void
    {
        Container::forgetInstance();
    }

    private function store(?int $defaultTtl = null, ?ArrayAdapter $adapter = null): CacheStore
    {
        return new CacheStore($adapter ?? $this->adapter, 'p_', $defaultTtl);
    }

    private function expiryOf(string $key, ?ArrayAdapter $adapter = null): ?float
    {
        return ($adapter ?? $this->adapter)->getItem('p_' . $key)->getMetadata()['expiry'] ?? null;
    }

    // ----- default ttl --------------------------------------------------------------

    public function testTheDefaultTtlAppliesToPlainWritesButNotToForever(): void
    {
        $store = $this->store(100);

        $store->set('plain', 1);
        $store->forever('perm', 2);
        $store->setMultiple(['multi' => 3]);
        $store->add('added', 4);

        $this->assertEqualsWithDelta(time() + 100, $this->expiryOf('plain'), 2);
        $this->assertEqualsWithDelta(time() + 100, $this->expiryOf('multi'), 2);
        $this->assertEqualsWithDelta(time() + 100, $this->expiryOf('added'), 2);
        $this->assertNull($this->expiryOf('perm'), 'forever() must not inherit the default ttl');
    }

    public function testAnExplicitTtlBeatsTheDefault(): void
    {
        $this->store(100)->set('k', 1, 10);

        $this->assertEqualsWithDelta(time() + 10, $this->expiryOf('k'), 2);
    }

    public function testNoDefaultTtlMeansNoExpiry(): void
    {
        $this->store()->set('k', 1);

        $this->assertNull($this->expiryOf('k'));
    }

    // ----- increment / decrement ------------------------------------------------------

    public function testIncrementAndDecrementKeepTheTtlOnTheArrayAdapter(): void
    {
        $store = $this->store();
        $store->set('n', 5, 60);

        $this->assertSame(6, $store->increment('n'));
        $this->assertSame(4, $store->decrement('n', 2));
        $this->assertEqualsWithDelta(time() + 60, $this->expiryOf('n'), 2);
    }

    public function testIncrementOnAMissingKeyReturnsFalse(): void
    {
        $this->assertFalse($this->store()->increment('absent'));
        $this->assertFalse($this->store()->decrement('absent'));
    }

    // ----- stash ----------------------------------------------------------------------

    public function testStashRunsTheCallbackOnlyOnAMiss(): void
    {
        $store = $this->store();
        $calls = 0;
        $compute = function () use (&$calls) {
            return 'v' . ++$calls;
        };

        $this->assertSame('v1', $store->stash('k', 60, $compute));
        $this->assertSame('v1', $store->stash('k', 60, $compute));
        $this->assertSame(1, $calls);
    }

    public function testStashCachesNullAndFalsyValues(): void
    {
        $store = $this->store();
        $calls = 0;

        foreach ([null, false, 0, ''] as $i => $value) {
            $store->stash("k$i", 60, function () use ($value, &$calls) {
                $calls++;

                return $value;
            });
            $this->assertSame($value, $store->stash("k$i", 60, fn() => 'recomputed'));
        }

        $this->assertSame(4, $calls);
    }

    public function testStashReadsTheItemOnceInsteadOfHasThenGet(): void
    {
        $spy = new SpyAdapter();
        $store = new CacheStore($spy, 'p_');
        $store->set('k', 'cached');

        $this->assertSame('cached', $store->stash('k', 60, fn() => 'recomputed'));
        $this->assertSame(0, $spy->hasItemCalls, 'has() then get() leaves a window where the item can expire');
        $this->assertSame(1, $spy->getCalls);
    }

    public function testStashUsesTheStoreDefaultTtlAndForeverUsesNone(): void
    {
        $store = $this->store(50);

        $store->stash('a', null, fn() => 1);
        $store->stashForever('b', fn() => 2);
        $store->stash('c', 20, fn() => 3);

        $this->assertEqualsWithDelta(time() + 50, $this->expiryOf('a'), 2);
        $this->assertNull($this->expiryOf('b'));
        $this->assertEqualsWithDelta(time() + 20, $this->expiryOf('c'), 2);
    }

    public function testStashDoesNotCacheACallbackThatThrows(): void
    {
        $store = $this->store();

        try {
            $store->stash('k', 60, fn() => throw new \DomainException('nope'));
            $this->fail('Expected the exception');
        } catch (\DomainException) {
        }

        $this->assertFalse($store->has('k'));
        $this->assertSame('ok', $store->stash('k', 60, fn() => 'ok'));
    }

    public function testStashWhenSkipsTheCacheWhenTheConditionIsFalse(): void
    {
        $store = $this->store();

        $this->assertSame('x', $store->stashWhen('k', fn() => 'x', false, 60));
        $this->assertFalse($store->has('k'));
        $this->assertSame('x', $store->stashWhen('k', fn() => 'x', true));
        $this->assertTrue($store->has('k'));
    }

    // ----- pull / missing -------------------------------------------------------------

    public function testPullReturnsTheValueAndRemovesIt(): void
    {
        $store = $this->store();
        $store->set('k', 'v');

        $this->assertSame('v', $store->pull('k'));
        $this->assertFalse($store->has('k'));
        $this->assertSame('fallback', $store->pull('k', 'fallback'));
    }

    public function testMissing(): void
    {
        $store = $this->store();
        $store->set('k', null);

        $this->assertTrue($store->missing('absent'));
        $this->assertFalse($store->missing('k'), 'a stored null is still present');
    }

    // ----- store() --------------------------------------------------------------------

    public function testStoreWithoutAnArgumentIsTheSameStore(): void
    {
        $store = $this->store();

        $this->assertSame($store, $store->store());
    }

    public function testStoreResolvesOtherStoresByName(): void
    {
        $other = $this->store();
        $store = $this->store()->resolveStoresUsing('main', fn(string $name) => $name === 'other' ? $other : throw new \RuntimeException("no [$name]"));

        $this->assertSame($other, $store->store('other'));
        $this->assertSame($store, $store->store('main'), 'asking for itself returns itself');

        $this->expectExceptionMessage('no [missing]');
        $store->store('missing');
    }

    public function testStoreWithoutAResolverExplainsWhy(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cache store [redis] cannot be resolved');

        $this->store()->store('redis');
    }

    public function testASubclassedStoreCanStillReachPlainStores(): void
    {
        // A profiler wraps the store in a subclass; store() hands back whatever the resolver builds.
        $subclass = new class(new ArrayAdapter(), 'p_') extends CacheStore {
        };
        $plain = $this->store();
        $subclass->resolveStoresUsing('main', fn() => $plain);

        $this->assertSame($plain, $subclass->store('other'));
    }

    public function testAWrappingStoreCanInheritTheSettingsOfTheOneItReplaces(): void
    {
        $original = new CacheStore(new ArrayAdapter(), 'orig_', 77);
        $other = $this->store();
        $original->resolveStoresUsing('main', fn() => $other);

        $replacement = (new CacheStore(new ArrayAdapter(), $original->getPrefix()))->inheritSettingsFrom($original);
        $replacement->set('k', 1);

        $this->assertSame('orig_', $replacement->getPrefix());
        $this->assertSame($other, $replacement->store('other'));
        $this->assertSame($replacement, $replacement->store('main'), 'it also knows its own name');
        $this->assertEqualsWithDelta(time() + 77, $replacement->getAdapter()->getItem('orig_k')->getMetadata()['expiry'], 2);
    }

    // ----- tags -----------------------------------------------------------------------

    public function testTaggedItemsAreReadBackThroughTheTaggedCache(): void
    {
        $tagged = $this->store()->tags('users');

        $this->assertInstanceOf(TaggedCache::class, $tagged);
        $this->assertTrue($tagged->set('42', ['name' => 'Ada']));
        $this->assertSame(['name' => 'Ada'], $tagged->get('42'));
        $this->assertTrue($tagged->has('42'));
        $this->assertSame('none', $tagged->get('absent', 'none'));
    }

    public function testFlushRemovesOnlyTheItemsCarryingThatTag(): void
    {
        $store = $this->store();
        $store->tags('users')->set('a', 1);
        $store->tags('posts')->set('b', 2);

        $this->assertTrue($store->tags('users')->flush());

        $this->assertFalse($store->tags('users')->has('a'));
        $this->assertSame(2, $store->tags('posts')->get('b'));
    }

    public function testFlushingAnyOfSeveralTagsRemovesTheItem(): void
    {
        $store = $this->store();
        $store->tags(['users', 'reports'])->set('both', 1);
        $store->tags('users')->set('only-users', 2);
        $store->tags('other')->set('unrelated', 3);

        $store->tags('reports')->flush();

        $this->assertFalse($store->tags('users')->has('both'), 'carried the reports tag');
        $this->assertTrue($store->tags('users')->has('only-users'));
        $this->assertTrue($store->tags('other')->has('unrelated'));
    }

    public function testTaggedStashCachesAndIsRemovedByFlush(): void
    {
        $tagged = $this->store()->tags(['reports']);
        $calls = 0;
        $compute = function () use (&$calls) {
            return 'report' . ++$calls;
        };

        $this->assertSame('report1', $tagged->stash('top', 60, $compute));
        $this->assertSame('report1', $tagged->stash('top', 60, $compute));

        $tagged->flush();

        $this->assertSame('report2', $tagged->stash('top', 60, $compute));
    }

    public function testTaggedStashForeverSurvivesUntilFlushed(): void
    {
        $tagged = $this->store(50)->tags('t');

        $tagged->stashForever('k', fn() => 'v');
        $tagged->forever('k2', 'v2');

        $this->assertSame('v', $tagged->get('k'));
        $this->assertSame('v2', $tagged->get('k2'));

        $tagged->flush();
        $this->assertFalse($tagged->has('k'));
    }

    public function testTaggedItemsAreKeptApartFromPlainKeys(): void
    {
        $store = $this->store();
        $store->set('shared', 'plain');
        $store->tags('t')->set('shared', 'tagged');

        $this->assertSame('plain', $store->get('shared'));
        $this->assertSame('tagged', $store->tags('t')->get('shared'));

        $store->tags('t')->flush();

        $this->assertSame('plain', $store->get('shared'), 'flushing a tag leaves plain keys alone');
    }

    public function testClearAlsoRemovesTaggedItems(): void
    {
        $store = $this->store();
        $store->set('plain', 1);
        $store->tags('t')->set('tagged', 2);

        $store->clear();

        $this->assertFalse($store->has('plain'));
        $this->assertFalse($store->tags('t')->has('tagged'));
    }

    public function testTaggedItemsExpire(): void
    {
        $tagged = $this->store()->tags('t');
        $tagged->set('short', 'v', 1);
        $tagged->set('long', 'v', 60);

        sleep(2);

        $this->assertFalse($tagged->has('short'));
        $this->assertTrue($tagged->has('long'));
    }

    public function testTaggedPullDeleteAndForget(): void
    {
        $tagged = $this->store()->tags('t');
        $tagged->set('a', 1);
        $tagged->set('b', 2);
        $tagged->set('c', 3);

        $this->assertSame(1, $tagged->pull('a'));
        $this->assertSame('gone', $tagged->pull('a', 'gone'));
        $this->assertTrue($tagged->delete('b'));
        $this->assertTrue($tagged->forget('c'));
        $this->assertFalse($tagged->has('b') || $tagged->has('c'));
    }

    public function testTaggedKeysAreSharedAcrossTags(): void
    {
        $store = $this->store();
        $store->tags('users')->set('same-key', 'first');
        $store->tags('posts')->set('same-key', 'second');

        $this->assertSame('second', $store->tags('users')->get('same-key'), 'tags group items for flushing, they do not scope keys');
    }

    public function testTagsExposeTheirNames(): void
    {
        $this->assertSame(['a', 'b'], $this->store()->tags(['a', 'b'])->getTags());
        $this->assertSame(['a'], $this->store()->tags('a')->getTags());
    }

    public function testAtLeastOneTagIsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->store()->tags([]);
    }

    public function testTagsNeedAnAdapterWithNamespaceSupport(): void
    {
        $store = new CacheStore(new class extends SpyAdapter {
        }, 'p_');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not support tags');

        $store->tags('t');
    }

    public function testTagsWorkOnTheFilesystemDriver(): void
    {
        $directory = sys_get_temp_dir() . '/doppar-tags-' . bin2hex(random_bytes(4));

        try {
            $store = new CacheStore(new FilesystemAdapter('tagtest', 0, $directory), 'p_');
            $store->tags(['users'])->set('user.1', 'ada');
            $store->tags(['posts'])->set('post.1', 'hello');

            $this->assertSame('ada', $store->tags('users')->get('user.1'));

            $store->tags('users')->flush();

            $this->assertFalse($store->tags('users')->has('user.1'));
            $this->assertSame('hello', $store->tags('posts')->get('post.1'));
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
