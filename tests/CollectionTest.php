<?php

namespace Tests\Unit;

use Phaseolies\Support\Collection;
use Phaseolies\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use ArrayIterator;
use Traversable;

class CollectionTest extends TestCase
{
    protected function makeTestModel($id, $name)
    {
        return new class($id, $name) extends Model {
            public $id;
            public $name;

            public function __construct($id, $name)
            {
                $this->id = $id;
                $this->name = $name;
            }

            public function toArray(): array
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name
                ];
            }
        };
    }

    public function testInitialization()
    {
        $collection = new Collection(Model::class, [1, 2, 3]);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testArrayAccess()
    {
        $collection = new Collection(Model::class, ['a' => 1, 'b' => 2]);

        // Test offsetExists
        $this->assertTrue(isset($collection['a']));
        $this->assertFalse(isset($collection['c']));

        // Test offsetGet
        $this->assertEquals(1, $collection['a']);

        // Test offsetSet
        $collection['c'] = 3;
        $this->assertEquals(3, $collection['c']);

        // Test offsetUnset
        unset($collection['b']);
        $this->assertFalse(isset($collection['b']));
    }

    public function testMagicGetAndIsset()
    {
        $collection = new Collection(Model::class, ['foo' => 'bar']);

        $this->assertEquals('bar', $collection->foo);
        $this->assertTrue(isset($collection->foo));
        $this->assertFalse(isset($collection->baz));
    }

    public function testGetIterator()
    {
        $items = [1, 2, 3];
        $collection = new Collection(Model::class, $items);
        $iterator = $collection->getIterator();

        $this->assertInstanceOf(Traversable::class, $iterator);
        $this->assertInstanceOf(ArrayIterator::class, $iterator);
        $this->assertEquals($items, iterator_to_array($iterator));
    }

    public function testCount()
    {
        $collection = new Collection(Model::class, [1, 2, 3]);
        $this->assertEquals(3, $collection->count());
    }

    public function testAll()
    {
        $items = [1, 2, 3];
        $collection = new Collection(Model::class, $items);
        $this->assertEquals($items, $collection->all());
    }

    public function testFirst()
    {
        $collection = new Collection(Model::class, [1, 2, 3]);
        $this->assertEquals(1, $collection->first());

        $emptyCollection = new Collection(Model::class, []);
        $this->assertNull($emptyCollection->first());
    }

    public function testKeyBy()
    {
        $model1 = $this->makeTestModel(1, 'Alice');
        $model2 = $this->makeTestModel(2, 'Bob');
        $collection = new Collection(get_class($model1), [$model1, $model2]);

        $keyed = $collection->keyBy('id');
        $this->assertEquals([
            1 => $model1,
            2 => $model2
        ], $keyed);
    }

    public function testGroupBy()
    {
        $model1 = $this->makeTestModel(1, 'Alice');
        $model2 = $this->makeTestModel(1, 'Bob');
        $model3 = $this->makeTestModel(2, 'Charlie');
        $collection = new Collection(get_class($model1), [$model1, $model2, $model3]);

        $grouped = $collection->groupBy('id');
        $this->assertEquals([
            1 => [$model1, $model2],
            2 => [$model3]
        ], $grouped);
    }

    public function testToArray()
    {
        $model1 = $this->makeTestModel(1, 'Alice');
        $model2 = $this->makeTestModel(2, 'Bob');
        $collection = new Collection(get_class($model1), [$model1, $model2]);

        $array = $collection->toArray();
        $this->assertEquals([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob']
        ], $array);
    }

    public function testMap()
    {
        $model = $this->makeTestModel(0, '');
        $collection = new Collection(get_class($model), [1, 2, 3]);
        $mapped = $collection->map(function ($item) {
            return $item * 2;
        });

        $this->assertEquals([2, 4, 6], $mapped->all());
    }

    public function testFilter()
    {
        $model = $this->makeTestModel(0, '');
        $collection = new Collection(get_class($model), [1, 2, 3, 4]);
        $filtered = $collection->filter(function ($item) {
            return $item % 2 === 0;
        });

        $this->assertEquals([2, 4], $filtered->all());
    }

    public function testEach()
    {
        $collection = new Collection(Model::class, [1, 2, 3]);
        $sum = 0;
        $collection->each(function ($item) use (&$sum) {
            $sum += $item;
        });

        $this->assertEquals(6, $sum);

        $sum = 0;
        $collection->each(function ($item) use (&$sum) {
            $sum += $item;
            if ($item >= 2) {
                return false;
            }
        });

        $this->assertEquals(3, $sum);
    }

    public function testFlatten()
    {
        $model = $this->makeTestModel(0, 'Test');

        // Test basic flattening
        $collection1 = new Collection(get_class($model), [
            [1, 2, [3, 4]],
            [5, 6],
            7,
            [8, [9, 10]]
        ]);

        $flattened1 = $collection1->flatten();
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $flattened1->all());

        // Test with limited depth
        $collection2 = new Collection(get_class($model), [
            [1, [2, [3, [4, 5]]]],
            [6, [7]]
        ]);

        $flattenedDepth1 = $collection2->flatten(1);
        $this->assertEquals([1, [2, [3, [4, 5]]], 6, [7]], $flattenedDepth1->all());

        $flattenedDepth2 = $collection2->flatten(2);
        $this->assertEquals([1, 2, [3, [4, 5]], 6, 7], $flattenedDepth2->all());

        // Test with model objects
        $model1 = $this->makeTestModel(1, 'Alice');
        $model2 = $this->makeTestModel(2, 'Bob');
        $model3 = $this->makeTestModel(3, 'Charlie');

        $collection3 = new Collection(get_class($model1), [
            $model1,
            [$model2, $model3]
        ]);

        $flattenedModels = $collection3->flatten();
        $this->assertEquals([$model1, $model2, $model3], $flattenedModels->all());

        // Test with empty collection
        $emptyCollection = new Collection(get_class($model), []);
        $this->assertEquals([], $emptyCollection->flatten()->all());

        // Test with mixed types
        $mixedCollection = new Collection(get_class($model), [
            'a',
            ['b', ['c' => 'd']],
            new \stdClass(),
            [1, 2]
        ]);

        $flattenedMixed = $mixedCollection->flatten();
        $this->assertCount(6, $flattenedMixed->all());
        $this->assertEquals('a', $flattenedMixed->all()[0]);
        $this->assertEquals('b', $flattenedMixed->all()[1]);

        // Only value from associative array
        $this->assertEquals('d', $flattenedMixed->all()[2]);
        $this->assertInstanceOf(\stdClass::class, $flattenedMixed->all()[3]);
        $this->assertEquals(1, $flattenedMixed->all()[4]);

        // Test that original collection remains unchanged
        $original = [1, [2, 3]];
        $collectionOriginal = new Collection(get_class($model), $original);
        $collectionOriginal->flatten();
        $this->assertEquals($original, $collectionOriginal->all());
    }

    public function testPluck()
    {
        $modelClass = get_class($this->makeTestModel(0, ''));

        // Basic array plucking
        $arrayCollection = new Collection($modelClass, [
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
            ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com'],
        ]);

        // Pluck single value
        $this->assertEquals(
            ['Alice', 'Bob', 'Charlie'],
            $arrayCollection->pluck('name')->all()
        );

        // Pluck with key
        $this->assertEquals(
            [1 => 'Alice', 2 => 'Bob', 3 => 'Charlie'],
            $arrayCollection->pluck('name', 'id')->all()
        );

        // Object plucking
        $model1 = $this->makeTestModel(1, 'Alice');
        $model2 = $this->makeTestModel(2, 'Bob');
        $model3 = $this->makeTestModel(3, 'Charlie');
        $objectCollection = new Collection($modelClass, [$model1, $model2, $model3]);

        // Pluck from objects
        $this->assertEquals(
            ['Alice', 'Bob', 'Charlie'],
            $objectCollection->pluck('name')->all()
        );

        // Pluck from objects with key
        $this->assertEquals(
            [1 => 'Alice', 2 => 'Bob', 3 => 'Charlie'],
            $objectCollection->pluck('name', 'id')->all()
        );

        // Mixed collection (arrays and objects)
        $mixedCollection = new Collection($modelClass, [
            ['id' => 1, 'name' => 'Alice'],
            $this->makeTestModel(2, 'Bob'),
            (object) ['id' => 3, 'name' => 'Charlie']
        ]);

        $this->assertEquals(
            ['Alice', 'Bob', 'Charlie'],
            $mixedCollection->pluck('name')->all()
        );

        // Edge cases
        // Empty collection
        $emptyCollection = new Collection($modelClass, []);
        $this->assertEquals([], $emptyCollection->pluck('name')->all());
        $this->assertEquals([], $emptyCollection->pluck('name', 'id')->all());

        // Non-existent keys
        $this->assertEquals(
            [null, null, null],
            $arrayCollection->pluck('nonexistent')->all()
        );

        $this->assertEquals(
            [1 => null, 2 => null, 3 => null],
            $arrayCollection->pluck('nonexistent', 'id')->all()
        );

        // Special cases
        // Numeric keys
        $numericCollection = new Collection($modelClass, [
            10 => ['name' => 'Alice'],
            20 => ['name' => 'Bob']
        ]);
        $this->assertEquals(
            ['Alice', 'Bob'],
            $numericCollection->pluck('name')->all()
        );

        // Null values
        $nullCollection = new Collection($modelClass, [
            ['name' => null],
            ['name' => 'Bob']
        ]);
        $this->assertEquals(
            [null, 'Bob'],
            $nullCollection->pluck('name')->all()
        );

        // Verify return type is always Collection
        $this->assertInstanceOf(
            Collection::class,
            $arrayCollection->pluck('name')
        );
        $this->assertInstanceOf(
            Collection::class,
            $objectCollection->pluck('name', 'id')
        );
    }
}
