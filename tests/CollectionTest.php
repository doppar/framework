<?php

namespace Tests\Unit;

use Phaseolies\Support\Collection;
use Phaseolies\Database\Entity\Model;
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
                    "id" => $this->id,
                    "name" => $this->name,
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
        $collection = new Collection(Model::class, ["a" => 1, "b" => 2]);

        // Test offsetExists
        $this->assertTrue(isset($collection["a"]));
        $this->assertFalse(isset($collection["c"]));

        // Test offsetGet
        $this->assertEquals(1, $collection["a"]);

        // Test offsetSet
        $collection["c"] = 3;
        $this->assertEquals(3, $collection["c"]);

        // Test offsetUnset
        unset($collection["b"]);
        $this->assertFalse(isset($collection["b"]));
    }

    public function testMagicGetAndIsset()
    {
        $collection = new Collection(Model::class, ["foo" => "bar"]);

        $this->assertEquals("bar", $collection->foo);
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
        $model1 = $this->makeTestModel(1, "Alice");
        $model2 = $this->makeTestModel(2, "Bob");
        $collection = new Collection(get_class($model1), [$model1, $model2]);

        $keyed = $collection->keyBy("id");
        $this->assertEquals(
            [
                1 => $model1,
                2 => $model2,
            ],
            $keyed,
        );
    }

    public function testGroupBy()
    {
        $model1 = $this->makeTestModel(1, "Alice");
        $model2 = $this->makeTestModel(1, "Bob");
        $model3 = $this->makeTestModel(2, "Charlie");
        $collection = new Collection(get_class($model1), [
            $model1,
            $model2,
            $model3,
        ]);

        $grouped = $collection->groupBy("id");
        $this->assertEquals(
            [
                1 => [$model1, $model2],
                2 => [$model3],
            ],
            $grouped,
        );
    }

    public function testToArray()
    {
        $model1 = $this->makeTestModel(1, "Alice");
        $model2 = $this->makeTestModel(2, "Bob");
        $collection = new Collection(get_class($model1), [$model1, $model2]);

        $array = $collection->toArray();
        $this->assertEquals(
            [["id" => 1, "name" => "Alice"], ["id" => 2, "name" => "Bob"]],
            $array,
        );
    }

    public function testMap()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3]);
        $mapped = $collection->map(function ($item) {
            return $item * 2;
        });

        $this->assertEquals([2, 4, 6], $mapped->all());
    }

    public function testFilter()
    {
        $model = $this->makeTestModel(0, "");
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
        $model = $this->makeTestModel(0, "Test");

        // Test basic flattening
        $collection1 = new Collection(get_class($model), [
            [1, 2, [3, 4]],
            [5, 6],
            7,
            [8, [9, 10]],
        ]);

        $flattened1 = $collection1->flatten();
        $this->assertEquals(
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            $flattened1->all(),
        );

        // Test with limited depth
        $collection2 = new Collection(get_class($model), [
            [1, [2, [3, [4, 5]]]],
            [6, [7]],
        ]);

        $flattenedDepth1 = $collection2->flatten(1);
        $this->assertEquals(
            [1, [2, [3, [4, 5]]], 6, [7]],
            $flattenedDepth1->all(),
        );

        $flattenedDepth2 = $collection2->flatten(2);
        $this->assertEquals([1, 2, [3, [4, 5]], 6, 7], $flattenedDepth2->all());

        // Test with model objects
        $model1 = $this->makeTestModel(1, "Alice");
        $model2 = $this->makeTestModel(2, "Bob");
        $model3 = $this->makeTestModel(3, "Charlie");

        $collection3 = new Collection(get_class($model1), [
            $model1,
            [$model2, $model3],
        ]);

        $flattenedModels = $collection3->flatten();
        $this->assertEquals(
            [$model1, $model2, $model3],
            $flattenedModels->all(),
        );

        // Test with empty collection
        $emptyCollection = new Collection(get_class($model), []);
        $this->assertEquals([], $emptyCollection->flatten()->all());

        // Test with mixed types
        $mixedCollection = new Collection(get_class($model), [
            "a",
            ["b", ["c" => "d"]],
            new \stdClass(),
            [1, 2],
        ]);

        $flattenedMixed = $mixedCollection->flatten();
        $this->assertCount(6, $flattenedMixed->all());
        $this->assertEquals("a", $flattenedMixed->all()[0]);
        $this->assertEquals("b", $flattenedMixed->all()[1]);

        // Only value from associative array
        $this->assertEquals("d", $flattenedMixed->all()[2]);
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
        $modelClass = get_class($this->makeTestModel(0, ""));

        // Basic array plucking
        $arrayCollection = new Collection($modelClass, [
            ["id" => 1, "name" => "Alice", "email" => "alice@example.com"],
            ["id" => 2, "name" => "Bob", "email" => "bob@example.com"],
            ["id" => 3, "name" => "Charlie", "email" => "charlie@example.com"],
        ]);

        // Pluck single value
        $this->assertEquals(
            ["Alice", "Bob", "Charlie"],
            $arrayCollection->pluck("name")->all(),
        );

        // Pluck with key
        $this->assertEquals(
            [1 => "Alice", 2 => "Bob", 3 => "Charlie"],
            $arrayCollection->pluck("name", "id")->all(),
        );

        // Object plucking
        $model1 = $this->makeTestModel(1, "Alice");
        $model2 = $this->makeTestModel(2, "Bob");
        $model3 = $this->makeTestModel(3, "Charlie");
        $objectCollection = new Collection($modelClass, [
            $model1,
            $model2,
            $model3,
        ]);

        // Pluck from objects
        $this->assertEquals(
            ["Alice", "Bob", "Charlie"],
            $objectCollection->pluck("name")->all(),
        );

        // Pluck from objects with key
        $this->assertEquals(
            [1 => "Alice", 2 => "Bob", 3 => "Charlie"],
            $objectCollection->pluck("name", "id")->all(),
        );

        // Mixed collection (arrays and objects)
        $mixedCollection = new Collection($modelClass, [
            ["id" => 1, "name" => "Alice"],
            $this->makeTestModel(2, "Bob"),
            (object) ["id" => 3, "name" => "Charlie"],
        ]);

        $this->assertEquals(
            ["Alice", "Bob", "Charlie"],
            $mixedCollection->pluck("name")->all(),
        );

        // Edge cases
        // Empty collection
        $emptyCollection = new Collection($modelClass, []);
        $this->assertEquals([], $emptyCollection->pluck("name")->all());
        $this->assertEquals([], $emptyCollection->pluck("name", "id")->all());

        // Non-existent keys
        $this->assertEquals(
            [null, null, null],
            $arrayCollection->pluck("nonexistent")->all(),
        );

        $this->assertEquals(
            [1 => null, 2 => null, 3 => null],
            $arrayCollection->pluck("nonexistent", "id")->all(),
        );

        // Special cases
        // Numeric keys
        $numericCollection = new Collection($modelClass, [
            10 => ["name" => "Alice"],
            20 => ["name" => "Bob"],
        ]);
        $this->assertEquals(
            ["Alice", "Bob"],
            $numericCollection->pluck("name")->all(),
        );

        // Null values
        $nullCollection = new Collection($modelClass, [
            ["name" => null],
            ["name" => "Bob"],
        ]);
        $this->assertEquals(
            [null, "Bob"],
            $nullCollection->pluck("name")->all(),
        );

        // Verify return type is always Collection
        $this->assertInstanceOf(
            Collection::class,
            $arrayCollection->pluck("name"),
        );
        $this->assertInstanceOf(
            Collection::class,
            $objectCollection->pluck("name", "id"),
        );
    }

    public function testMapAsGroup()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        // Test data
        $users = [
            [
                "id" => 1,
                "name" => "Alice",
                "department" => "IT",
                "active" => true,
            ],
            [
                "id" => 2,
                "name" => "Bob",
                "department" => "HR",
                "active" => true,
            ],
            [
                "id" => 3,
                "name" => "Charlie",
                "department" => "IT",
                "active" => false,
            ],
            [
                "id" => 4,
                "name" => "Diana",
                "department" => "Finance",
                "active" => true,
            ],
        ];

        $collection = new Collection($modelClass, $users);

        // Test 1: Simple grouping by string key
        $result1 = $collection->mapAsGroup("department");
        $expected1 = [
            "IT" => [
                [
                    "id" => 1,
                    "name" => "Alice",
                    "department" => "IT",
                    "active" => true,
                ],
                [
                    "id" => 3,
                    "name" => "Charlie",
                    "department" => "IT",
                    "active" => false,
                ],
            ],
            "HR" => [
                [
                    "id" => 2,
                    "name" => "Bob",
                    "department" => "HR",
                    "active" => true,
                ],
            ],
            "Finance" => [
                [
                    "id" => 4,
                    "name" => "Diana",
                    "department" => "Finance",
                    "active" => true,
                ],
            ],
        ];
        $this->assertEquals($expected1, $result1);

        // Test 2: Grouping with mapping callback
        $result2 = $collection->mapAsGroup(
            "department",
            fn($user) => ["name" => $user["name"], "active" => $user["active"]],
        );
        $expected2 = [
            "IT" => [
                ["name" => "Alice", "active" => true],
                ["name" => "Charlie", "active" => false],
            ],
            "HR" => [["name" => "Bob", "active" => true]],
            "Finance" => [["name" => "Diana", "active" => true]],
        ];
        $this->assertEquals($expected2, $result2);

        // Test 3: Grouping with callback key resolver
        $result3 = $collection->mapAsGroup(
            fn($user) => $user["active"] ? "active" : "inactive",
            fn($user) => $user["name"],
        );
        $expected3 = [
            "active" => ["Alice", "Bob", "Diana"],
            "inactive" => ["Charlie"],
        ];
        $this->assertEquals($expected3, $result3);

        // Test 4: Grouping with complex callback
        $result4 = $collection->mapAsGroup(
            fn($user) => $user["department"] .
                "_" .
                ($user["active"] ? "active" : "inactive"),
            fn($user) => ["id" => $user["id"], "initial" => $user["name"][0]],
        );
        $expected4 = [
            "IT_active" => [["id" => 1, "initial" => "A"]],
            "HR_active" => [["id" => 2, "initial" => "B"]],
            "IT_inactive" => [["id" => 3, "initial" => "C"]],
            "Finance_active" => [["id" => 4, "initial" => "D"]],
        ];
        $this->assertEquals($expected4, $result4);

        // Test 5: Empty collection
        $emptyCollection = new Collection($modelClass, []);
        $this->assertEquals([], $emptyCollection->mapAsGroup("department"));

        // Test 6: Null keys are excluded
        $usersWithNull = [
            ["id" => 1, "name" => "Alice", "department" => "IT"],
            ["id" => 2, "name" => "Bob", "department" => null],
        ];
        $collectionWithNull = new Collection($modelClass, $usersWithNull);
        $result6 = $collectionWithNull->mapAsGroup("department");
        $expected6 = [
            "IT" => [["id" => 1, "name" => "Alice", "department" => "IT"]],
        ];
        $this->assertEquals($expected6, $result6);
    }

    public function testMapAsKey()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        // Test data
        $users = [
            ["id" => 1, "name" => "Alice", "email" => "alice@example.com"],
            ["id" => 2, "name" => "Bob", "email" => "bob@example.com"],
            ["id" => 3, "name" => "Charlie", "email" => "charlie@example.com"],
        ];

        $collection = new Collection($modelClass, $users);

        // Test 1: Simple keying by string key
        $result1 = $collection->mapAsKey("id");
        $expected1 = [
            1 => ["id" => 1, "name" => "Alice", "email" => "alice@example.com"],
            2 => ["id" => 2, "name" => "Bob", "email" => "bob@example.com"],
            3 => [
                "id" => 3,
                "name" => "Charlie",
                "email" => "charlie@example.com",
            ],
        ];
        $this->assertEquals($expected1, $result1);

        // Test 2: Keying with mapping callback
        $result2 = $collection->mapAsKey(
            "id",
            fn($user) => ["name" => $user["name"], "email" => $user["email"]],
        );
        $expected2 = [
            1 => ["name" => "Alice", "email" => "alice@example.com"],
            2 => ["name" => "Bob", "email" => "bob@example.com"],
            3 => ["name" => "Charlie", "email" => "charlie@example.com"],
        ];
        $this->assertEquals($expected2, $result2);

        // Test 3: Keying with callback key resolver
        $result3 = $collection->mapAsKey(
            fn($user) => "user_" . $user["id"],
            fn($user) => $user["name"],
        );
        $expected3 = [
            "user_1" => "Alice",
            "user_2" => "Bob",
            "user_3" => "Charlie",
        ];
        $this->assertEquals($expected3, $result3);

        // Test 4: Keying with email as key
        $result4 = $collection->mapAsKey("email", fn($user) => $user["name"]);
        $expected4 = [
            "alice@example.com" => "Alice",
            "bob@example.com" => "Bob",
            "charlie@example.com" => "Charlie",
        ];
        $this->assertEquals($expected4, $result4);

        // Test 5: Duplicate keys (should overwrite)
        $usersWithDuplicates = [
            ["id" => 1, "name" => "Alice", "department" => "IT"],
            ["id" => 1, "name" => "Alice2", "department" => "HR"],
        ];
        $duplicateCollection = new Collection(
            $modelClass,
            $usersWithDuplicates,
        );
        $result5 = $duplicateCollection->mapAsKey("id");
        $expected5 = [
            1 => ["id" => 1, "name" => "Alice2", "department" => "HR"],
        ];
        $this->assertEquals($expected5, $result5);

        // Test 6: Empty collection
        $emptyCollection = new Collection($modelClass, []);
        $this->assertEquals([], $emptyCollection->mapAsKey("id"));

        // Test 7: Null keys are excluded
        $usersWithNull = [
            ["id" => 1, "name" => "Alice", "email" => "alice@example.com"],
            ["id" => null, "name" => "NoID", "email" => "noid@example.com"],
        ];
        $collectionWithNull = new Collection($modelClass, $usersWithNull);
        $result7 = $collectionWithNull->mapAsKey("id");
        $expected7 = [
            1 => ["id" => 1, "name" => "Alice", "email" => "alice@example.com"],
        ];
        $this->assertEquals($expected7, $result7);
    }

    public function testGroupByAlias()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        $users = [
            ["id" => 1, "name" => "Alice", "department" => "IT"],
            ["id" => 2, "name" => "Bob", "department" => "HR"],
        ];

        $collection = new Collection($modelClass, $users);

        // Test that groupBy is an alias for mapAsGroup
        $result1 = $collection->groupBy("department");
        $result2 = $collection->mapAsGroup("department");

        $this->assertEquals($result1, $result2);

        // Test with mapping callback
        $result3 = $collection->groupBy(
            "department",
            fn($user) => $user["name"],
        );
        $result4 = $collection->mapAsGroup(
            "department",
            fn($user) => $user["name"],
        );

        $this->assertEquals($result3, $result4);
    }

    public function testKeyByAlias()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        $users = [["id" => 1, "name" => "Alice"], ["id" => 2, "name" => "Bob"]];

        $collection = new Collection($modelClass, $users);

        // Test that keyBy is an alias for mapAsKey
        $result1 = $collection->keyBy("id");
        $result2 = $collection->mapAsKey("id");

        $this->assertEquals($result1, $result2);

        // Test with mapping callback
        $result3 = $collection->keyBy("id", fn($user) => $user["name"]);
        $result4 = $collection->mapAsKey("id", fn($user) => $user["name"]);

        $this->assertEquals($result3, $result4);
    }

    public function testMapToGroups()
    {
        $modelClass = get_class($this->makeTestModel(0, ''));

        $users = [
            ['id' => 1, 'name' => 'Alice', 'department' => 'IT', 'active' => true],
            ['id' => 2, 'name' => 'Bob', 'department' => 'HR', 'active' => false]
        ];

        $collection = new Collection($modelClass, $users);

        // Test multiple groups from single callback
        $result = $collection->mapToGroups(function ($user) {
            return [
                'department_' . $user['department'] => $user['name'],
                'status_' . ($user['active'] ? 'active' : 'inactive') => $user['name'],
                'all_users' => $user['name']
            ];
        });

        $expected = [
            'department_IT' => ['Alice'],
            'status_active' => ['Alice'],
            'all_users' => ['Alice', 'Bob'], // Both users should be in all_users
            'department_HR' => ['Bob'],
            'status_inactive' => ['Bob']
        ];

        $this->assertEquals($expected, $result);

        // Test empty collection
        $emptyCollection = new Collection($modelClass, []);
        $this->assertEquals([], $emptyCollection->mapToGroups(fn($user) => []));
    }

    public function testMapWithKeys()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        $users = [
            ["id" => 1, "name" => "Alice", "email" => "alice@example.com"],
            ["id" => 2, "name" => "Bob", "email" => "bob@example.com"],
        ];

        $collection = new Collection($modelClass, $users);

        // Test multiple key-value pairs from single callback
        $result = $collection->mapWithKeys(function ($user) {
            return [
                "user_" . $user["id"] => $user["name"],
                "email_" . $user["id"] => $user["email"],
                "id_" . $user["id"] => $user["id"],
            ];
        });

        $expected = [
            "user_1" => "Alice",
            "email_1" => "alice@example.com",
            "id_1" => 1,
            "user_2" => "Bob",
            "email_2" => "bob@example.com",
            "id_2" => 2,
        ];

        $this->assertEquals($expected, $result);

        // Test empty collection
        $emptyCollection = new Collection($modelClass, []);
        $this->assertEquals([], $emptyCollection->mapWithKeys(fn($user) => []));

        // Test duplicate keys (should overwrite)
        $result2 = $collection->mapWithKeys(function ($user) {
            return [
                "same_key" => $user["name"],
                "same_key" => "overwritten",
            ];
        });

        $this->assertEquals(["same_key" => "overwritten"], $result2);
    }

    public function testBuildKeyResolver()
    {
        $collection = new Collection(Model::class, []);

        // Test string key resolver
        $resolver1 = $this->invokeMethod($collection, 'buildKeyResolver', ['name']);
        $arrayItem = ['name' => 'Alice', 'age' => 25];
        $objectItem = (object) ['name' => 'Bob', 'age' => 30];

        $this->assertEquals('Alice', $resolver1($arrayItem));
        $this->assertEquals('Bob', $resolver1($objectItem));
        $this->assertNull($resolver1(['age' => 25]));

        // Test callback resolver
        $callback = fn($item) => $item['name'] . '_' . $item['age'];
        $resolver2 = $this->invokeMethod($collection, 'buildKeyResolver', [$callback]);

        $this->assertEquals('Alice_25', $resolver2($arrayItem));

        // Test that callable is returned as-is
        $this->assertSame($callback, $this->invokeMethod($collection, 'buildKeyResolver', [$callback]));
    }

    private function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function testTakeWithPositiveLimit()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3, 4, 5]);

        $result = $collection->take(3);

        $this->assertEquals([1, 2, 3], $result->all());
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(get_class($model), $result->getModel());
    }

    public function testTakeWithLimitGreaterThanCollectionSize()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3]);

        $result = $collection->take(10);

        $this->assertEquals([1, 2, 3], $result->all());
    }

    public function testTakeWithZeroLimit()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3, 4, 5]);

        $result = $collection->take(0);

        $this->assertEquals([], $result->all());
    }

    public function testTakeWithNegativeLimit()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3, 4, 5]);

        $result = $collection->take(-2);

        $this->assertEquals([4, 5], $result->all());
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(get_class($model), $result->getModel());
    }

    public function testTakeWithLargeNegativeLimit()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3]);

        $result = $collection->take(-5);

        $this->assertEquals([1, 2, 3], $result->all());
    }

    public function testTakeWithEmptyCollection()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), []);

        $result = $collection->take(3);

        $this->assertEquals([], $result->all());
    }

    public function testTakeWithModels()
    {
        $model1 = $this->makeTestModel(1, "Alice");
        $model2 = $this->makeTestModel(2, "Bob");
        $model3 = $this->makeTestModel(3, "Charlie");
        $model4 = $this->makeTestModel(4, "Diana");

        $collection = new Collection(get_class($model1), [$model1, $model2, $model3, $model4]);

        $result = $collection->take(2);

        $this->assertEquals([$model1, $model2], $result->all());
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(get_class($model1), $result->getModel());
    }

    public function testTakeLastWithPositiveLimit()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3, 4, 5]);

        $result = $collection->takeLast(3);

        $this->assertEquals([3, 4, 5], $result->all());
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(get_class($model), $result->getModel());
    }

    public function testTakeLastWithLimitGreaterThanCollectionSize()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3]);

        $result = $collection->takeLast(10);

        $this->assertEquals([1, 2, 3], $result->all());
    }

    public function testTakeLastWithZeroLimit()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3, 4, 5]);

        $result = $collection->takeLast(0);

        $this->assertEquals([], $result->all());
    }

    public function testTakeLastWithNegativeLimit()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3, 4, 5]);

        $result = $collection->takeLast(-2);

        $this->assertEquals([], $result->all());
    }

    public function testTakeLastWithEmptyCollection()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), []);

        $result = $collection->takeLast(3);

        $this->assertEquals([], $result->all());
    }

    public function testTakeLastWithModels()
    {
        $model1 = $this->makeTestModel(1, "Alice");
        $model2 = $this->makeTestModel(2, "Bob");
        $model3 = $this->makeTestModel(3, "Charlie");
        $model4 = $this->makeTestModel(4, "Diana");

        $collection = new Collection(get_class($model1), [$model1, $model2, $model3, $model4]);

        $result = $collection->takeLast(2);

        $this->assertEquals([$model3, $model4], $result->all());
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(get_class($model1), $result->getModel());
    }

    public function testTakeAndTakeLastPreserveKeys()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [
            'a' => 1,
            'b' => 2,
            'c' => 3,
            'd' => 4,
            'e' => 5
        ]);

        // take should preserve keys for positive limits
        $takeResult = $collection->take(3);
        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $takeResult->all());

        // takeLast should preserve keys for positive limits
        $takeLastResult = $collection->takeLast(3);
        $this->assertEquals(['c' => 3, 'd' => 4, 'e' => 5], $takeLastResult->all());
    }

    public function testTakeWithSingleItem()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [42]);

        $result = $collection->take(1);

        $this->assertEquals([42], $result->all());
    }

    public function testTakeLastWithSingleItem()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [42]);

        $result = $collection->takeLast(1);

        $this->assertEquals([42], $result->all());
    }

    public function testTakeLastChainability()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        // Debug: Check each step
        $step1 = $collection->takeLast(8);
        $this->assertEquals([3, 4, 5, 6, 7, 8, 9, 10], $step1->all(), 'Step 1 failed');

        $step2 = $step1->takeLast(5);
        $this->assertEquals([6, 7, 8, 9, 10], $step2->all(), 'Step 2 failed');

        $step3 = $step2->takeLast(3);
        $this->assertEquals([8, 9, 10], $step3->all(), 'Step 3 failed');

        // Test the chain
        $result = $collection->takeLast(8)->takeLast(5)->takeLast(3);
        $this->assertEquals([8, 9, 10], $result->all());
    }

    public function testTakeChainability()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        // Debug: Check each step
        $step1 = $collection->take(8);
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8], $step1->all(), 'Step 1 failed');

        $step2 = $step1->take(5);
        $this->assertEquals([1, 2, 3, 4, 5], $step2->all(), 'Step 2 failed');

        $step3 = $step2->take(3);
        $this->assertEquals([1, 2, 3], $step3->all(), 'Step 3 failed');

        // Test the chain
        $result = $collection->take(8)->take(5)->take(3);
        $this->assertEquals([1, 2, 3], $result->all());
    }

    public function testTakeAndTakeLastCombined()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        // Take first 8, then last 3 of those
        $result = $collection->take(8)->takeLast(3);

        $this->assertEquals([6, 7, 8], $result->all());

        // Take last 8, then first 3 of those
        $result2 = $collection->takeLast(8)->take(3);

        $this->assertEquals([3, 4, 5], $result2->all());
    }

    public function testTakeWithAssociativeArrays()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
            ['id' => 4, 'name' => 'Diana']
        ]);

        $result = $collection->take(2);

        $this->assertEquals([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob']
        ], $result->all());
    }

    public function testTakeLastWithAssociativeArrays()
    {
        $model = $this->makeTestModel(0, "");
        $collection = new Collection(get_class($model), [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
            ['id' => 4, 'name' => 'Diana']
        ]);

        $result = $collection->takeLast(2);

        $this->assertEquals([
            ['id' => 3, 'name' => 'Charlie'],
            ['id' => 4, 'name' => 'Diana']
        ], $result->all());
    }
}
