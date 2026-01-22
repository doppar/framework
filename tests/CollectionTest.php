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

    public function testSortByWithStringKey()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        $users = [
            ['id' => 3, 'name' => 'Charlie', 'age' => 25],
            ['id' => 1, 'name' => 'Alice', 'age' => 30],
            ['id' => 2, 'name' => 'Bob', 'age' => 20]
        ];

        $collection = new Collection($modelClass, $users);

        // Sort by name
        $result = $collection->sortBy('name');
        $this->assertEquals([
            ['id' => 1, 'name' => 'Alice', 'age' => 30],
            ['id' => 2, 'name' => 'Bob', 'age' => 20],
            ['id' => 3, 'name' => 'Charlie', 'age' => 25]
        ], $result->all());

        // Sort by age
        $result2 = $collection->sortBy('age');
        $this->assertEquals([
            ['id' => 2, 'name' => 'Bob', 'age' => 20],
            ['id' => 3, 'name' => 'Charlie', 'age' => 25],
            ['id' => 1, 'name' => 'Alice', 'age' => 30]
        ], $result2->all());
    }

    public function testSortByWithCallback()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        $users = [
            ['id' => 1, 'name' => 'Alice', 'age' => 30],
            ['id' => 2, 'name' => 'Bob', 'age' => 20],
            ['id' => 3, 'name' => 'Charlie', 'age' => 25]
        ];

        $collection = new Collection($modelClass, $users);

        // Sort by name length
        $result = $collection->sortBy(fn($user) => strlen($user['name']));
        $this->assertEquals([
            ['id' => 2, 'name' => 'Bob', 'age' => 20],
            ['id' => 1, 'name' => 'Alice', 'age' => 30],
            ['id' => 3, 'name' => 'Charlie', 'age' => 25]
        ], $result->all());
    }

    public function testSortByWithObjects()
    {
        $model1 = $this->makeTestModel(3, 'Charlie');
        $model2 = $this->makeTestModel(1, 'Alice');
        $model3 = $this->makeTestModel(2, 'Bob');

        $collection = new Collection(get_class($model1), [$model1, $model2, $model3]);

        // Sort by id
        $result = $collection->sortBy('id');
        $this->assertEquals([$model2, $model3, $model1], $result->all());

        // Sort by name
        $result2 = $collection->sortBy('name');
        $this->assertEquals([$model2, $model3, $model1], $result2->all());
    }

    public function testSortByDesc()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        $users = [
            ['id' => 1, 'name' => 'Alice', 'age' => 30],
            ['id' => 2, 'name' => 'Bob', 'age' => 20],
            ['id' => 3, 'name' => 'Charlie', 'age' => 25]
        ];

        $collection = new Collection($modelClass, $users);

        // Sort by age descending
        $result = $collection->sortByDesc('age');
        $this->assertEquals([
            ['id' => 1, 'name' => 'Alice', 'age' => 30],
            ['id' => 3, 'name' => 'Charlie', 'age' => 25],
            ['id' => 2, 'name' => 'Bob', 'age' => 20]
        ], $result->all());

        // Sort by name descending
        $result2 = $collection->sortByDesc('name');
        $this->assertEquals([
            ['id' => 3, 'name' => 'Charlie', 'age' => 25],
            ['id' => 2, 'name' => 'Bob', 'age' => 20],
            ['id' => 1, 'name' => 'Alice', 'age' => 30]
        ], $result2->all());
    }

    public function testSortByWithNumericOptions()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        $items = [
            ['value' => '100'],
            ['value' => '20'],
            ['value' => '3']
        ];

        $collection = new Collection($modelClass, $items);

        // String sort (lexicographic comparison)
        $result1 = $collection->sortBy('value', SORT_STRING);
        $this->assertEquals([
            ['value' => '100'],
            ['value' => '20'],
            ['value' => '3']
        ], $result1->all());

        // Numeric sort
        $result2 = $collection->sortBy('value', SORT_NUMERIC);
        $this->assertEquals([
            ['value' => '3'],
            ['value' => '20'],
            ['value' => '100']
        ], $result2->all());
    }

    public function testSortByWithEmptyCollection()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, []);

        $result = $collection->sortBy('name');
        $this->assertEquals([], $result->all());
    }

    public function testSortByPreservesModelType()
    {
        $model1 = $this->makeTestModel(3, 'Charlie');
        $model2 = $this->makeTestModel(1, 'Alice');

        $collection = new Collection(get_class($model1), [$model1, $model2]);

        $result = $collection->sortBy('id');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(get_class($model1), $result->getModel());
    }

    public function testSortByChainability()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        $users = [
            ['id' => 1, 'name' => 'Alice', 'department' => 'IT', 'age' => 30],
            ['id' => 2, 'name' => 'Bob', 'department' => 'IT', 'age' => 25],
            ['id' => 3, 'name' => 'Charlie', 'department' => 'HR', 'age' => 35]
        ];

        $collection = new Collection($modelClass, $users);

        // Chain sortBy with filter
        $result = $collection
            ->filter(fn($user) => $user['department'] === 'IT')
            ->sortBy('age');

        $this->assertEquals([
            ['id' => 2, 'name' => 'Bob', 'department' => 'IT', 'age' => 25],
            ['id' => 1, 'name' => 'Alice', 'department' => 'IT', 'age' => 30]
        ], $result->all());
    }

    public function testSortByWithNullValues()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        $users = [
            ['id' => 1, 'name' => 'Alice', 'score' => 100],
            ['id' => 2, 'name' => 'Bob', 'score' => null],
            ['id' => 3, 'name' => 'Charlie', 'score' => 50]
        ];

        $collection = new Collection($modelClass, $users);

        $result = $collection->sortBy('score');

        // Null values should sort to the beginning with SORT_REGULAR
        $this->assertEquals(null, $result->all()[0]['score']);
        $this->assertEquals(50, $result->all()[1]['score']);
        $this->assertEquals(100, $result->all()[2]['score']);
    }

    public function testSortByWithMixedTypes()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));

        $collection = new Collection($modelClass, [
            ['data' => 'zebra'],
            ['data' => 100],
            ['data' => 'apple'],
            ['data' => 50]
        ]);

        // This tests that sortBy handles mixed types gracefully
        $result = $collection->sortBy('data');

        // Should not throw an error
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(4, $result);
    }

    public function testChunkDividesCollectionIntoChunks()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 3, 4, 5, 6, 7]);

        $chunks = $collection->chunk(3);

        $this->assertEquals([
            [1, 2, 3],
            [4, 5, 6],
            [7]
        ], $chunks->all());
    }

    public function testChunkWithExactDivision()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 3, 4, 5, 6]);

        $chunks = $collection->chunk(2);

        $this->assertEquals([
            [1, 2],
            [3, 4],
            [5, 6]
        ], $chunks->all());
    }

    public function testChunkWithInvalidSize()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 3]);

        $this->expectException(\InvalidArgumentException::class);
        $collection->chunk(0);
    }


    public function testPartitionDividesCollectionByCondition()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 3, 4, 5, 6]);

        [$even, $odd] = $collection->partition(fn($num) => $num % 2 === 0);

        $this->assertEquals([2, 4, 6], $even->all());
        $this->assertEquals([1, 3, 5], $odd->all());
    }

    public function testPartitionWithObjects()
    {
        $users = [
            ['name' => 'Alice', 'active' => true],
            ['name' => 'Bob', 'active' => false],
            ['name' => 'Charlie', 'active' => true],
        ];

        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, $users);

        [$active, $inactive] = $collection->partition(fn($user) => $user['active']);

        $this->assertCount(2, $active);
        $this->assertCount(1, $inactive);
    }

    public function testDiffReturnsItemsNotInOtherCollection()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection1 = new Collection($modelClass, [1, 2, 3, 4, 5]);
        $collection2 = new Collection($modelClass, [3, 4, 5, 6, 7]);

        $diff = $collection1->diff($collection2);

        $this->assertEquals([1, 2], $diff->all());
    }

    public function testDiffWithArray()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 3, 4, 5]);

        $diff = $collection->diff([3, 4, 5]);

        $this->assertEquals([1, 2], $diff->all());
    }

    public function testIntersectReturnsCommonItems()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection1 = new Collection($modelClass, [1, 2, 3, 4, 5]);
        $collection2 = new Collection($modelClass, [3, 4, 5, 6, 7]);

        $intersect = $collection1->intersect($collection2);

        $this->assertEquals([3, 4, 5], $intersect->all());
    }

    public function testIntersectWithArray()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 3, 4, 5]);

        $intersect = $collection->intersect([2, 3, 6]);

        $this->assertEquals([2, 3], $intersect->all());
    }

    public function testTapExecutesCallbackWithoutModifying()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 3]);

        $tapped = null;
        $result = $collection->tap(function ($col) use (&$tapped) {
            $tapped = $col->count();
        });

        $this->assertEquals(3, $tapped);
        $this->assertSame($collection, $result);
        $this->assertEquals([1, 2, 3], $collection->all());
    }

    public function testPipePassesCollectionToCallback()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 3]);

        $result = $collection->pipe(fn($col) => $col->sum());

        $this->assertEquals(6, $result);
    }

    public function testPipeCanReturnAnything()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, ['a', 'b', 'c']);

        $result = $collection->pipe(fn($col) => implode(',', $col->all()));

        $this->assertEquals('a,b,c', $result);
    }

    public function testDuplicatesFindsRepeatedValues()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 2, 3, 3, 3, 4]);

        $duplicates = $collection->duplicates();

        // Should return all duplicate occurrences except the first
        $this->assertEquals([2, 3, 3], $duplicates->all());
    }

    public function testDuplicatesWithKey()
    {
        $users = [
            ['id' => 1, 'email' => 'alice@example.com'],
            ['id' => 2, 'email' => 'bob@example.com'],
            ['id' => 3, 'email' => 'alice@example.com'],
            ['id' => 4, 'email' => 'charlie@example.com'],
        ];

        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, $users);

        $duplicates = $collection->duplicates('email');

        $this->assertCount(1, $duplicates);
        $this->assertEquals('alice@example.com', $duplicates->first()['email']);
    }

    public function testSumCalculatesTotal()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 3, 4, 5]);

        $this->assertEquals(15, $collection->sum());
    }

    public function testSumWithKey()
    {
        $items = [
            ['price' => 100],
            ['price' => 200],
            ['price' => 300],
        ];

        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, $items);

        $this->assertEquals(600, $collection->sum('price'));
    }

    public function testSumWithCallback()
    {
        $items = [
            ['quantity' => 2, 'price' => 10],
            ['quantity' => 3, 'price' => 20],
        ];

        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, $items);

        $total = $collection->sum(fn($item) => $item['quantity'] * $item['price']);

        $this->assertEquals(80, $total);
    }

    public function testAvgCalculatesAverage()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 3, 4, 5]);

        $this->assertEquals(3, $collection->avg());
    }

    public function testAvgWithKey()
    {
        $users = [
            ['age' => 20],
            ['age' => 30],
            ['age' => 40],
        ];

        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, $users);

        $this->assertEquals(30, $collection->avg('age'));
    }

    public function testAvgWithEmptyCollection()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, []);

        $this->assertNull($collection->avg());
    }

    public function testMinReturnsSmallestValue()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [5, 2, 8, 1, 9]);

        $this->assertEquals(1, $collection->min());
    }

    public function testMaxReturnsLargestValue()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [5, 2, 8, 1, 9]);

        $this->assertEquals(9, $collection->max());
    }

    public function testMinWithKey()
    {
        $items = [
            ['price' => 100],
            ['price' => 50],
            ['price' => 200],
        ];

        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, $items);

        $this->assertEquals(50, $collection->min('price'));
    }

    public function testMaxWithKey()
    {
        $items = [
            ['price' => 100],
            ['price' => 50],
            ['price' => 200],
        ];

        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, $items);

        $this->assertEquals(200, $collection->max('price'));
    }

    public function testSoleReturnsOnlyItem()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [42]);

        $this->assertEquals(42, $collection->sole());
    }

    public function testSoleWithCallback()
    {
        $users = [
            ['id' => 1, 'name' => 'Alice', 'active' => true],
            ['id' => 2, 'name' => 'Bob', 'active' => false],
            ['id' => 3, 'name' => 'Charlie', 'active' => true],
        ];

        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, $users);

        $inactive = $collection->sole(fn($user) => !$user['active']);

        $this->assertEquals('Bob', $inactive['name']);
    }

    public function testSoleThrowsWhenEmpty()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No items found');
        $collection->sole();
    }

    public function testSoleThrowsWhenMultiple()
    {
        $modelClass = get_class($this->makeTestModel(0, ""));
        $collection = new Collection($modelClass, [1, 2, 3]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Multiple items found');
        $collection->sole();
    }
}
