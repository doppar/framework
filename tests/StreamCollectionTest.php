<?php

namespace Tests\Unit;

use Phaseolies\Support\StreamCollection;
use Phaseolies\Support\Collection;
use PHPUnit\Framework\TestCase;
use ArrayIterator;
use Generator;

class StreamCollectionTest extends TestCase
{
    public function testInitializationWithArray()
    {
        $collection = new StreamCollection([1, 2, 3]);
        $this->assertInstanceOf(StreamCollection::class, $collection);
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testInitializationWithGenerator()
    {
        $generator = function () {
            yield 1;
            yield 2;
            yield 3;
        };

        $collection = new StreamCollection($generator());
        $this->assertInstanceOf(StreamCollection::class, $collection);
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testInitializationWithCallable()
    {
        $callable = function () {
            yield 'a' => 1;
            yield 'b' => 2;
        };

        $collection = new StreamCollection($callable);
        $this->assertInstanceOf(StreamCollection::class, $collection);
        $this->assertEquals(['a' => 1, 'b' => 2], $collection->all());
    }

    public function testInitializationWithIterator()
    {
        $iterator = new ArrayIterator(['x' => 10, 'y' => 20]);
        $collection = new StreamCollection($iterator);
        $this->assertInstanceOf(StreamCollection::class, $collection);
        $this->assertEquals(['x' => 10, 'y' => 20], $collection->all());
    }

    public function testInitializationWithInvalidSource()
    {
        $this->expectException(\InvalidArgumentException::class);
        new StreamCollection('invalid source');
    }

    public function testMakeMethod()
    {
        $collection = StreamCollection::make(function () {
            yield 'test' => 'value';
        });

        $this->assertInstanceOf(StreamCollection::class, $collection);
        $this->assertEquals(['test' => 'value'], $collection->all());
    }

    public function testGetIterator()
    {
        $items = [1, 2, 3];
        $collection = new StreamCollection($items);
        $iterator = $collection->getIterator();

        $this->assertInstanceOf(\Traversable::class, $iterator);
        $this->assertEquals($items, iterator_to_array($iterator));
    }

    public function testMap()
    {
        $collection = new StreamCollection([1, 2, 3]);
        $mapped = $collection->map(fn($item) => $item * 2);

        $this->assertInstanceOf(StreamCollection::class, $mapped);
        $this->assertEquals([2, 4, 6], $mapped->all());
    }

    public function testMapWithKeys()
    {
        $collection = new StreamCollection(['a' => 1, 'b' => 2]);
        $mapped = $collection->map(fn($item, $key) => $key . ':' . $item);

        $this->assertEquals(['a' => 'a:1', 'b' => 'b:2'], $mapped->all());
    }

    public function testFilter()
    {
        $collection = new StreamCollection([1, 2, 3, 4, 5]);
        $filtered = $collection->filter(fn($item) => $item % 2 === 0);

        $this->assertInstanceOf(StreamCollection::class, $filtered);
        $this->assertEquals([1 => 2, 3 => 4], $filtered->all());
    }

    public function testFilterWithValues()
    {
        $collection = new StreamCollection([1, 2, 3, 4, 5]);
        $filtered = $collection->filter(fn($item) => $item % 2 === 0)->values();

        $this->assertInstanceOf(StreamCollection::class, $filtered);
        $this->assertEquals([2, 4], $filtered->all());
    }

    public function testFilterWithKeys()
    {
        $collection = new StreamCollection(['a' => 1, 'b' => 2, 'c' => 3]);
        $filtered = $collection->filter(fn($item, $key) => $key === 'b' || $item === 3);

        $this->assertEquals(['b' => 2, 'c' => 3], $filtered->all());
    }

    public function testChunk()
    {
        $collection = new StreamCollection([1, 2, 3, 4, 5, 6]);
        $chunked = $collection->chunk(2);

        $this->assertInstanceOf(StreamCollection::class, $chunked);

        $chunks = $chunked->all();
        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(Collection::class, $chunks[0]);

        $this->assertEquals([0 => 1, 1 => 2], $chunks[0]->all());
        $this->assertEquals([2 => 3, 3 => 4], $chunks[1]->all());
        $this->assertEquals([4 => 5, 5 => 6], $chunks[2]->all());
    }

    public function testChunkWithValues()
    {
        $collection = new StreamCollection([1, 2, 3, 4, 5, 6]);
        $chunked = $collection->chunk(2)->map(function ($chunk) {
            return $chunk->values();
        });

        $this->assertInstanceOf(StreamCollection::class, $chunked);

        $chunks = $chunked->all();
        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(Collection::class, $chunks[0]);

        $this->assertEquals([1, 2], $chunks[0]->all());
        $this->assertEquals([3, 4], $chunks[1]->all());
        $this->assertEquals([5, 6], $chunks[2]->all());
    }

    public function testChunkWithUnevenSize()
    {
        $collection = new StreamCollection([1, 2, 3, 4, 5]);
        $chunked = $collection->chunk(2);

        $chunks = $chunked->all();
        $this->assertCount(3, $chunks);

        $this->assertEquals([0 => 1, 1 => 2], $chunks[0]->all());
        $this->assertEquals([2 => 3, 3 => 4], $chunks[1]->all());
        $this->assertEquals([4 => 5], $chunks[2]->all());
    }

    public function testChunkWithUnevenSizeAndResetKeys()
    {
        $collection = new StreamCollection([1, 2, 3, 4, 5]);
        $chunked = $collection->chunk(2)->map(function ($chunk) {
            return $chunk->values();
        });

        $chunks = $chunked->all();
        $this->assertCount(3, $chunks);

        $this->assertEquals([1, 2], $chunks[0]->all());
        $this->assertEquals([3, 4], $chunks[1]->all());
        $this->assertEquals([5], $chunks[2]->all());
    }

    public function testEach()
    {
        $collection = new StreamCollection([1, 2, 3]);
        $sum = 0;
        $keys = [];

        $collection->each(function ($item, $key) use (&$sum, &$keys) {
            $sum += $item;
            $keys[] = $key;
        });

        $this->assertEquals(6, $sum);
        $this->assertEquals([0, 1, 2], $keys);
    }

    public function testCollect()
    {
        $streamCollection = new StreamCollection([1, 2, 3]);
        $collection = $streamCollection->collect();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testCollectWithModel()
    {
        $model = new class {
            public function toArray(): array
            {
                return ['test' => 'value'];
            }
        };

        $streamCollection = new StreamCollection([$model]);
        $collection = $streamCollection->collect(get_class($model));

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(1, $collection);
    }

    public function testAll()
    {
        $items = ['a' => 1, 'b' => 2, 'c' => 3];
        $collection = new StreamCollection($items);

        $this->assertEquals($items, $collection->all());
    }

    public function testFirst()
    {
        $collection = new StreamCollection([10, 20, 30]);
        $this->assertEquals(10, $collection->first());

        $emptyCollection = new StreamCollection([]);
        $this->assertNull($emptyCollection->first());
    }

    public function testFirstWithAssociativeArray()
    {
        $collection = new StreamCollection(['x' => 100, 'y' => 200]);
        $this->assertEquals(100, $collection->first());
    }

    public function testCount()
    {
        $collection = new StreamCollection([1, 2, 3, 4, 5]);
        $this->assertEquals(5, $collection->count());

        $emptyCollection = new StreamCollection([]);
        $this->assertEquals(0, $emptyCollection->count());
    }

    public function testIsEmpty()
    {
        $emptyCollection = new StreamCollection([]);
        $this->assertTrue($emptyCollection->isEmpty());

        $collection = new StreamCollection([1]);
        $this->assertFalse($collection->isEmpty());
    }

    public function testIsNotEmpty()
    {
        $emptyCollection = new StreamCollection([]);
        $this->assertFalse($emptyCollection->isNotEmpty());

        $collection = new StreamCollection([1]);
        $this->assertTrue($collection->isNotEmpty());
    }

    public function testPluck()
    {
        $items = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ];

        $collection = new StreamCollection($items);
        $plucked = $collection->pluck('name');

        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $plucked->all());
    }

    public function testPluckWithKey()
    {
        $items = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ];

        $collection = new StreamCollection($items);
        $plucked = $collection->pluck('name', 'id');

        $this->assertEquals([1 => 'Alice', 2 => 'Bob', 3 => 'Charlie'], $plucked->all());
    }

    public function testPluckWithObjects()
    {
        $items = [
            (object) ['id' => 1, 'name' => 'Alice'],
            (object) ['id' => 2, 'name' => 'Bob'],
        ];

        $collection = new StreamCollection($items);
        $plucked = $collection->pluck('name');

        $this->assertEquals(['Alice', 'Bob'], $plucked->all());
    }

    public function testFlatten()
    {
        $collection = new StreamCollection([
            [1, 2],
            [3, [4, 5]],
            6,
        ]);

        $flattened = $collection->flatten();

        $this->assertEquals([1, 2, 3, 4, 5, 6], $flattened->values()->all());
    }

    public function testFlattenWithDepth()
    {
        $collection = new StreamCollection([
            [1, [2, [3, 4]]],
            [5, 6],
        ]);

        $flattenedDepth1 = $collection->flatten(1);
        $this->assertEquals([1, [2, [3, 4]], 5, 6], $flattenedDepth1->values()->all());

        $flattenedDepth2 = $collection->flatten(2);
        $this->assertEquals([1, 2, [3, 4], 5, 6], $flattenedDepth2->values()->all());
    }

    public function testFlattenWithCollections()
    {
        $innerCollection = new Collection('', [2, 3]);
        $streamCollection = new StreamCollection([4, 5]);

        $collection = new StreamCollection([
            1,
            $innerCollection,
            $streamCollection,
            6,
        ]);

        $flattened = $collection->flatten();
        $this->assertEquals([1, 2, 3, 4, 5, 6], $flattened->values()->all());
    }

    public function testUnique()
    {
        $collection = new StreamCollection([1, 2, 2, 3, 1, 4]);
        $unique = $collection->unique();

        $this->assertEquals([1, 2, 3, 4], $unique->all());
    }

    public function testUniqueWithKey()
    {
        $items = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 1, 'name' => 'Alice Duplicate'], // This will overwrite the first one
            ['id' => 3, 'name' => 'Charlie'],
        ];

        $collection = new StreamCollection($items);
        $unique = $collection->unique('id');

        $uniqueArray = $unique->values()->all();

        $this->assertCount(3, $uniqueArray);
        $this->assertEquals('Alice Duplicate', $uniqueArray[0]['name']); // First item is now the duplicate
        $this->assertEquals('Bob', $uniqueArray[1]['name']);
        $this->assertEquals('Charlie', $uniqueArray[2]['name']);
    }

    public function testUniqueStrict()
    {
        $collection = new StreamCollection(['1', 1, 1]);
        $uniqueStrict = $collection->unique(null, true);
        $uniqueNotStrict = $collection->unique(null, false);

        $this->assertEquals(['1', 1], $uniqueStrict->all());
        $this->assertEquals([1], $uniqueNotStrict->all());
    }

    public function testSkip()
    {
        $collection = new StreamCollection([1, 2, 3, 4, 5]);
        $skipped = $collection->skip(2);

        $this->assertInstanceOf(StreamCollection::class, $skipped);
        $this->assertEquals([2 => 3, 3 => 4, 4 => 5], $skipped->all());
        $this->assertEquals([3, 4, 5], $skipped->values()->all());
    }

    public function testSkipWithAssociative()
    {
        $collection = new StreamCollection(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        $skipped = $collection->skip(2);

        $this->assertEquals(['c' => 3, 'd' => 4], $skipped->all());
    }

    public function testTake()
    {
        $collection = new StreamCollection([1, 2, 3, 4, 5]);
        $taken = $collection->take(3);

        $this->assertEquals([1, 2, 3], $taken->all());
    }

    public function testTakeMoreThanAvailable()
    {
        $collection = new StreamCollection([1, 2]);
        $taken = $collection->take(5);

        $this->assertEquals([1, 2], $taken->all());
    }

    public function testValues()
    {
        $collection = new StreamCollection(['a' => 1, 'b' => 2, 'c' => 3]);
        $values = $collection->values();

        $this->assertEquals([1, 2, 3], $values->all());
    }

    public function testPush()
    {
        $collection = new StreamCollection([1, 2]);
        $collection->push(3);

        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testPushToEmpty()
    {
        $collection = new StreamCollection([]);
        $collection->push('test');

        $this->assertEquals(['test'], $collection->all());
    }

    public function testPushMultiple()
    {
        $collection = new StreamCollection([1]);
        $collection->push(2);
        $collection->push(3);

        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testToJson()
    {
        $collection = new StreamCollection([1, 2, 3]);
        $json = $collection->toJson();

        $this->assertEquals('[1,2,3]', $json);
    }

    public function testToJsonWithOptions()
    {
        $collection = new StreamCollection(['a' => 1, 'b' => 2]);
        $json = $collection->toJson(JSON_PRETTY_PRINT);

        $this->assertJson($json);
        $this->assertEquals('{"a":1,"b":2}', json_encode(json_decode($json)));
    }

    public function testToArray()
    {
        $items = ['a' => 1, 'b' => 2, 'c' => 3];
        $collection = new StreamCollection($items);

        $this->assertEquals($items, $collection->toArray());
    }

    public function testLazyEvaluation()
    {
        $executionCount = 0;

        $generator = function () use (&$executionCount) {
            echo "Generator started\n";
            $executionCount++;
            echo "Yield 1 (count: $executionCount)\n";
            yield 1;
            $executionCount++;
            echo "Yield 2 (count: $executionCount)\n";
            yield 2;
            $executionCount++;
            echo "Yield 3 (count: $executionCount)\n";
            yield 3;
        };

        $collection = new StreamCollection($generator);

        $this->assertEquals(0, $executionCount);

        $first = $collection->first();
        $this->assertEquals(1, $first);
        $this->assertEquals(1, $executionCount);

        $all = $collection->all();
        echo "All items: " . json_encode($all) . "\n";
        $this->assertEquals(4, $executionCount);
    }

    public function testMethodChaining()
    {
        $collection = new StreamCollection([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        $result = $collection
            ->filter(fn($n) => $n % 2 === 0) // [2, 4, 6, 8, 10]
            ->map(fn($n) => $n * 3) // [6, 12, 18, 24, 30]
            ->skip(1) // [12, 18, 24, 30]
            ->take(2) // [12, 18]
            ->values() // Reset keys [12, 18]
            ->all();

        $this->assertEquals([12, 18], $result);
    }

    public function testLargeDatasetMemoryEfficiency()
    {
        $memoryBefore = memory_get_usage();

        $largeGenerator = function () {
            for ($i = 0; $i < 1000; $i++) {
                yield $i;
            }
        };

        $collection = new StreamCollection($largeGenerator);

        $sum = 0;
        $collection->each(function ($item) use (&$sum) {
            $sum += $item;
        });

        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;

        $this->assertEquals(499500, $sum); // Sum of 0..999
        $this->assertLessThan(1024 * 1024, $memoryUsed); // Less than 1MB
    }

    public function testNestedStreamCollections()
    {
        $inner1 = new StreamCollection([1, 2]);
        $inner2 = new StreamCollection([3, 4]);

        $outer = new StreamCollection([$inner1, $inner2, 5]);
        $flattened = $outer->flatten();

        $this->assertEquals([1, 2, 3, 4, 5], $flattened->values()->all());
    }
}
