<?php

namespace Tests\Unit;

use Phaseolies\Support\UrlGenerator;
use Phaseolies\Support\Collection;
use Phaseolies\Http\Request;
use Phaseolies\Database\Entity\Query\Builder;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use PDOException;
use PDO;

class QueryBuilderTest extends TestCase
{
    private $pdo;
    private $builder;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE,
                age INTEGER,
                status TEXT DEFAULT 'active',
                salary DECIMAL(10,2),
                department TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL
            )
        ");

        $this->pdo->exec("
            INSERT INTO users (name, email, age, status, salary, department) VALUES
            ('John Doe', 'john@example.com', 25, 'active', 50000, 'Engineering'),
            ('Jane Smith', 'jane@example.com', 30, 'active', 60000, 'Marketing'),
            ('Bob Johnson', 'bob@example.com', 35, 'inactive', 70000, 'Engineering'),
            ('Alice Brown', 'alice@example.com', 28, 'active', 55000, 'Sales'),
            ('Charlie Wilson', 'charlie@example.com', 40, 'active', 80000, 'Engineering')
        ");

        $container = new Container();
        $container->bind('db', Database::class);
        $container->bind('request', fn() => new Request());
        $container->bind('url', fn() => new UrlGenerator());

        $this->builder = new Builder($this->pdo, 'users');
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(Builder::class, $this->builder);
    }

    public function testSelectWithArray()
    {
        $builder = $this->builder->select(['id', 'name']);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testSelectWithMultipleArguments()
    {
        $builder = $this->builder->select('id', 'name', 'email');
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testSelectWithRawExpression()
    {
        $builder = $this->builder->select('COUNT(*) as count');
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testSelectRaw()
    {
        $builder = $this->builder->selectRaw('COUNT(*) as total, AVG(age) as avg_age');
        $sql = $builder->toSql();
        $this->assertStringContainsString('COUNT(*) as total, AVG(age) as avg_age', $sql);
    }

    public function testOmit()
    {
        $builder = $this->builder->omit('email', 'age');
        $result = $builder->first();

        if ($result) {
            $this->assertArrayNotHasKey('email', $result);
            $this->assertArrayNotHasKey('age', $result);
        }
    }

    public function testOmitWithArray()
    {
        $builder = $this->builder->omit(['email', 'age']);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testWhereBasic()
    {
        $builder = $this->builder->where('name', 'John Doe');
        $this->assertInstanceOf(Builder::class, $builder);

        $result = $builder->first();
        $this->assertEquals('John Doe', $result['name']);
    }

    public function testWhereWithOperator()
    {
        $builder = $this->builder->where('age', '>', 30);
        $results = $builder->get();

        foreach ($results as $result) {
            $this->assertGreaterThan(30, $result['age']);
        }
    }

    public function testWhereWithTwoArguments()
    {
        $builder = $this->builder->where('name', 'John Doe');
        $result = $builder->first();
        $this->assertEquals('John Doe', $result['name']);
    }

    public function testWhereNull()
    {
        $this->pdo->exec("INSERT INTO users (name, email, age, department) VALUES ('Null Dept', 'null@example.com', 45, NULL)");

        $builder = $this->builder->whereNull('department');
        $results = $builder->get();

        $this->assertGreaterThan(0, $results->count());
        foreach ($results as $result) {
            $this->assertNull($result['department']);
        }
    }

    public function testWhereNotNull()
    {
        $builder = $this->builder->whereNotNull('department');
        $results = $builder->get();

        foreach ($results as $result) {
            $this->assertNotNull($result['department']);
        }
    }

    public function testOrWhere()
    {
        $builder = $this->builder
            ->where('name', 'John Doe')
            ->orWhere('name', 'Jane Smith');

        $results = $builder->get();
        $this->assertGreaterThanOrEqual(2, $results->count());

        $names = array_column($results->all(), 'name');
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
    }

    public function testWhereIn()
    {
        $builder = $this->builder->whereIn('id', [1, 2, 3]);
        $results = $builder->get();

        $this->assertEquals(3, $results->count());

        $ids = array_column($results->all(), 'id');
        $this->assertContains(1, $ids);
        $this->assertContains(2, $ids);
        $this->assertContains(3, $ids);
    }

    public function testWhereInEmpty()
    {
        $builder = $this->builder->whereIn('id', []);
        $results = $builder->get();

        $this->assertEquals(0, $results->count());
    }

    public function testOrWhereIn()
    {
        $builder = $this->builder
            ->where('id', 1)
            ->orWhereIn('id', [2, 3]);

        $results = $builder->get();
        $this->assertEquals(3, $results->count());
    }

    public function testWhereBetween()
    {
        $builder = $this->builder->whereBetween('age', [25, 30]);
        $results = $builder->get();

        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(25, $result['age']);
            $this->assertLessThanOrEqual(30, $result['age']);
        }
    }

    public function testOrWhereBetween()
    {
        $builder = $this->builder
            ->where('id', 1)
            ->orWhereBetween('age', [30, 35]);

        $results = $builder->get();
        $this->assertGreaterThan(1, $results->count());
    }

    public function testWhereNotBetween()
    {
        $builder = $this->builder->whereNotBetween('age', [26, 34]);
        $results = $builder->get();

        foreach ($results as $result) {
            $this->assertTrue($result['age'] < 26 || $result['age'] > 34);
        }
    }

    public function testOrWhereNotBetween()
    {
        $builder = $this->builder
            ->where('id', 1)
            ->orWhereNotBetween('age', [30, 40]);

        $results = $builder->get();
        $this->assertGreaterThan(0, $results->count());
    }

    public function testWhereLike()
    {
        $builder = $this->builder->whereLike('name', 'John%');
        $results = $builder->get();

        $this->assertGreaterThan(0, $results->count());
        foreach ($results as $result) {
            $this->assertStringStartsWith('John', $result['name']);
        }
    }

    public function testOrWhereLike()
    {
        $builder = $this->builder
            ->where('id', 1)
            ->orWhereLike('name', 'Jane%');

        $results = $builder->get();
        $this->assertGreaterThan(1, $results->count());
    }

    public function testWhereRaw()
    {
        $builder = $this->builder->whereRaw('LENGTH(name) > ?', [8]);
        $results = $builder->get();

        foreach ($results as $result) {
            $this->assertGreaterThan(8, strlen($result['name']));
        }
    }

    public function testWhereNested()
    {
        $builder = $this->builder
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('age', '>', 25)
                    ->where('department', 'Engineering');
            });

        $results = $builder->get();
        $this->assertGreaterThan(0, $results->count());
    }

    public function testOrWhereNested()
    {
        $builder = $this->builder
            ->where('status', 'active')
            ->orWhere(function ($query) {
                $query->where('age', '>', 35)
                    ->where('salary', '>', 75000);
            });

        $results = $builder->get();
        $this->assertGreaterThan(0, $results->count());
    }

    public function testDeeplyNestedWhere()
    {
        $builder = $this->builder
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('age', '>', 25)
                    ->orWhere(function ($q) {
                        $q->where('department', 'Engineering')
                            ->where('salary', '>', 60000);
                    });
            });

        $results = $builder->get();
        $this->assertGreaterThan(0, $results->count());
    }

    public function testOrderBy()
    {
        $builder = $this->builder->orderBy('name', 'ASC');
        $results = $builder->get();

        $names = array_column($results->all(), 'name');
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertEquals($sortedNames, $names);
    }

    public function testOrderByDesc()
    {
        $builder = $this->builder->orderBy('name', 'DESC');
        $results = $builder->get();

        $names = array_column($results->all(), 'name');
        $sortedNames = $names;
        rsort($sortedNames);
        $this->assertEquals($sortedNames, $names);
    }

    public function testOrderByRaw()
    {
        $builder = $this->builder->orderByRaw('name DESC');
        $results = $builder->get();

        $names = array_column($results->all(), 'name');
        $this->assertEquals('John Doe', $names[0]);
    }

    public function testNewest()
    {
        $builder = $this->builder->newest('id');
        $results = $builder->get();

        $this->assertEquals(5, $results[0]['id']);
    }

    public function testOldest()
    {
        $builder = $this->builder->oldest('id');
        $results = $builder->get();

        $this->assertEquals(1, $results[0]['id']);
    }

    public function testGroupBy()
    {
        $builder = $this->builder
            ->select('department')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('department');

        $results = $builder->get();
        $this->assertGreaterThan(0, $results->count());

        foreach ($results as $result) {
            $this->assertArrayHasKey('count', $result);
        }
    }

    public function testGroupByRaw()
    {
        $builder = $this->builder
            ->selectRaw('department, COUNT(*) as count')
            ->groupByRaw('department');

        $results = $builder->get();
        $this->assertGreaterThan(0, $results->count());
    }

    public function testLimit()
    {
        $builder = $this->builder->limit(2);
        $results = $builder->get();

        $this->assertEquals(2, $results->count());
    }

    public function testOffset()
    {
        $builder = $this->builder->orderBy('id', 'ASC')->limit(2)->offset(1);
        $results = $builder->get();

        $this->assertEquals(2, $results->count());
        $this->assertEquals(2, $results[0]['id']);
    }

    public function testJoin()
    {
        $this->pdo->exec("
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                title TEXT NOT NULL,
                content TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        $this->pdo->exec("
            INSERT INTO posts (user_id, title, content) VALUES 
            (1, 'First Post', 'Content 1'),
            (1, 'Second Post', 'Content 2'),
            (2, 'Jane Post', 'Content 3')
        ");

        $builder = $this->builder
            ->select('users.name', 'posts.title')
            ->join('posts', 'users.id', '=', 'posts.user_id');

        $results = $builder->get();
        $this->assertGreaterThan(0, $results->count());

        foreach ($results as $result) {
            $this->assertArrayHasKey('title', $result);
        }
    }

    public function testCount()
    {
        $count = $this->builder->count();
        $this->assertEquals(5, $count);
    }

    public function testCountWithConditions()
    {
        $count = $this->builder->where('status', 'active')->count();
        $this->assertEquals(4, $count); // 4 active users
    }

    public function testCountWithGroupBy()
    {
        $this->pdo->exec("
            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                amount DECIMAL(10,2),
                status TEXT
            )
        ");

        $this->pdo->exec("
            INSERT INTO orders (user_id, amount, status) VALUES 
            (1, 100.00, 'completed'),
            (1, 200.00, 'pending'),
            (2, 150.00, 'completed'),
            (3, 300.00, 'completed')
        ");

        $orderBuilder = new Builder($this->pdo, 'orders');
        $count = $orderBuilder
            ->select('user_id')
            ->groupBy('user_id')
            ->count();

        $this->assertEquals(3, $count); // 3 distinct users
    }

    public function testExists()
    {
        $exists = $this->builder->where('name', 'John Doe')->exists();
        $this->assertTrue($exists);

        $notExists = $this->builder->where('name', 'Non Existent')->exists();
        $this->assertFalse($notExists);
    }

    public function testPaginate()
    {
        $result = $this->builder->paginate(2);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('last_page', $result);

        $this->assertCount(2, $result['data']);
        $this->assertEquals(5, $result['total']);
    }

    public function testInsert()
    {
        $id = $this->builder->insert([
            'name' => 'New User',
            'email' => 'new@example.com',
            'age' => 22
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $user = $this->builder->where('id', $id)->first();
        $this->assertEquals('New User', $user['name']);
    }

    public function testInsertMany()
    {
        $rows = [
            ['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20],
            ['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 21],
            ['name' => 'User 3', 'email' => 'user3@example.com', 'age' => 22]
        ];

        $affected = $this->builder->insertMany($rows);
        $this->assertEquals(3, $affected);

        $count = $this->builder->count();
        $this->assertEquals(8, $count); // 5 original + 3 new
    }

    public function testInsertManyWithChunking()
    {
        $rows = [
            ['name' => 'Chunk User 1', 'email' => 'chunk1@example.com', 'age' => 30],
            ['name' => 'Chunk User 2', 'email' => 'chunk2@example.com', 'age' => 31],
            ['name' => 'Chunk User 3', 'email' => 'chunk3@example.com', 'age' => 32],
            ['name' => 'Chunk User 4', 'email' => 'chunk4@example.com', 'age' => 33],
        ];

        $affected = $this->builder->insertMany($rows, 2); // Chunk size of 2
        $this->assertEquals(4, $affected);

        $count = $this->builder->count();
        $this->assertEquals(9, $count); // 5 original + 4 new
    }

    public function testUpdate()
    {
        $result = $this->builder->where('id', 1)->update(['name' => 'Updated Name']);
        $this->assertTrue($result);

        $user = $this->builder->where('id', 1)->first();
        $this->assertEquals('Updated Name', $user['name']);
    }

    public function testIncrement()
    {
        $affected = $this->builder->where('id', 1)->increment('age', 5);
        $this->assertEquals(1, $affected);

        $user = $this->builder->where('id', 1)->first();
        $this->assertEquals(30, $user['age']); // 25 + 5
    }

    public function testDecrement()
    {
        $affected = $this->builder->where('id', 1)->decrement('age', 5);
        $this->assertEquals(1, $affected);

        $user = $this->builder->where('id', 1)->first();
        $this->assertEquals(20, $user['age']); // 25 - 5
    }

    public function testIncrementWithExtra()
    {
        $affected = $this->builder->where('id', 1)->increment('age', 5, ['name' => 'Updated Name']);
        $this->assertEquals(1, $affected);

        $user = $this->builder->where('id', 1)->first();
        $this->assertEquals(30, $user['age']);
        $this->assertEquals('Updated Name', $user['name']);
    }

    public function testDelete()
    {
        $initialCount = $this->builder->count();

        // Create a user to delete
        $userId = $this->builder->insert([
            'name' => 'User to Delete',
            'email' => 'delete@example.com',
            'age' => 40
        ]);

        $deleteBuilder = new Builder($this->pdo, 'users');
        $result = $deleteBuilder->where('id', $userId)->delete();
        $this->assertTrue($result);

        $countBuilder = new Builder($this->pdo, 'users');
        $this->assertEquals($initialCount, $countBuilder->count());
    }

    public function testSelectRawWithBindings()
    {
        $builder = $this->builder->selectRaw('name, age + ? as future_age', [5]);
        $sql = $builder->toSql();

        $this->assertStringContainsString('name, age + 5 as future_age', $sql);
    }

    public function testFirstReturnsSingleResult()
    {
        $result = $this->builder->where('id', 1)->first();

        $this->assertInstanceOf(Collection::class, $result);
        $resultArray = $result->toArray();
        $this->assertEquals('John Doe', $resultArray['name']);

        $nonExistent = $this->builder->where('id', 999)->first();
        $this->assertNull($nonExistent);
    }

    public function testWhereRawWithBindings()
    {
        $builder = $this->builder->whereRaw('age > ? AND salary > ?', [25, 50000]);
        $results = $builder->get();
        $this->assertGreaterThan(0, $results->count());
    }

    public function testOrderByRawWithBindings()
    {
        $builder = $this->builder->orderByRaw('FIELD(department, ?, ?)', ['Engineering', 'Marketing']);
        $sql = $builder->toSql();
        $this->assertStringContainsString('ORDER BY FIELD(department, ?, ?)', $sql);
    }

    public function testGroupByRawWithBindings()
    {
        $builder = $this->builder->groupByRaw('YEAR(created_at), MONTH(created_at)');
        $sql = $builder->toSql();
        $this->assertStringContainsString('GROUP BY YEAR(created_at), MONTH(created_at)', $sql);
    }

    public function testWhereLikeCaseSensitive()
    {
        $builder = $this->builder->whereLike('name', 'john', true);
        $results = $builder->get();
        $this->assertGreaterThanOrEqual(0, $results->count());
    }

    public function testOrWhereLikeCaseSensitive()
    {
        $builder = $this->builder
            ->where('id', 1)
            ->orWhereLike('name', 'jane', true);
        $results = $builder->get();
        $this->assertGreaterThan(0, $results->count());
    }

    public function testCamelToSnake()
    {
        $reflection = new \ReflectionClass($this->builder);
        $method = $reflection->getMethod('camelToSnake');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'camelCaseString');
        $this->assertEquals('camel_case_string', $result);
    }

    public function testGetPdoParamType()
    {
        $reflection = new \ReflectionClass($this->builder);
        $method = $reflection->getMethod('getPdoParamType');
        $method->setAccessible(true);

        $this->assertEquals(PDO::PARAM_INT, $method->invoke($this->builder, 123));
        $this->assertEquals(PDO::PARAM_BOOL, $method->invoke($this->builder, true));
        $this->assertEquals(PDO::PARAM_NULL, $method->invoke($this->builder, null));
        $this->assertEquals(PDO::PARAM_STR, $method->invoke($this->builder, 'string'));
    }

    public function testHasValue()
    {
        $reflection = new \ReflectionClass($this->builder);
        $method = $reflection->getMethod('hasValue');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->builder, true));
        $this->assertFalse($method->invoke($this->builder, false));
        $this->assertTrue($method->invoke($this->builder, 1));
        $this->assertFalse($method->invoke($this->builder, 0));
        $this->assertTrue($method->invoke($this->builder, 'text'));
        $this->assertFalse($method->invoke($this->builder, ''));
        $this->assertTrue($method->invoke($this->builder, ['item']));
        $this->assertFalse($method->invoke($this->builder, []));
    }

    public function testQuoteIdentifier()
    {
        $reflection = new \ReflectionClass($this->builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $this->assertEquals('`column`', $method->invoke($this->builder, 'column'));
        $this->assertEquals('`table`.`column`', $method->invoke($this->builder, 'table.column'));
    }

    public function testGetTableColumns()
    {
        $reflection = new \ReflectionClass($this->builder);
        $method = $reflection->getMethod('getTableColumns');
        $method->setAccessible(true);

        $columns = $method->invoke($this->builder);
        $this->assertIsArray($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('email', $columns);
    }

    public function testUpsertInsert()
    {
        $affected = $this->builder->upsert(
            [['name' => 'Upsert User', 'email' => 'upsert@example.com', 'age' => 25]],
            'email'
        );
        $this->assertEquals(1, $affected);

        $user = $this->builder->where('email', 'upsert@example.com')->first();
        $this->assertEquals('Upsert User', $user['name']);
    }

    public function testUpsertUpdate()
    {
        // First insert
        $this->builder->upsert(
            [['name' => 'Upsert User', 'email' => 'upsert@example.com', 'age' => 25]],
            'email'
        );

        // Then update
        $affected = $this->builder->upsert(
            [['name' => 'Updated Upsert User', 'email' => 'upsert@example.com', 'age' => 26]],
            'email'
        );
        $this->assertEquals(1, $affected);

        $user = $this->builder->where('email', 'upsert@example.com')->first();
        $this->assertEquals('Updated Upsert User', $user['name']);
        $this->assertEquals(26, $user['age']);
    }

    public function testUpsertWithSpecificColumns()
    {
        // First insert
        $this->builder->upsert(
            [['name' => 'Upsert User', 'email' => 'upsert@example.com', 'age' => 25, 'department' => 'IT']],
            'email'
        );

        // Update only name
        $affected = $this->builder->upsert(
            [['name' => 'Name Only Update', 'email' => 'upsert@example.com', 'age' => 25, 'department' => 'IT']],
            'email',
            ['name'] // Only update name column
        );
        $this->assertEquals(1, $affected);

        $user = $this->builder->where('email', 'upsert@example.com')->first();
        $this->assertEquals('Name Only Update', $user['name']);
        $this->assertEquals('IT', $user['department']); // Should remain unchanged
    }

    public function testIfTrue()
    {
        $builder = $this->builder
            ->if(true, function ($query) {
                $query->where('status', 'active');
            });

        $results = $builder->get();
        $this->assertEquals(4, $results->count()); // 4 active users
    }

    public function testIfFalse()
    {
        $builder = $this->builder
            ->if(false, function ($query) {
                $query->where('status', 'active');
            });

        $results = $builder->get();
        $this->assertEquals(5, $results->count()); // All users
    }

    public function testIfWithDefault()
    {
        $builder = $this->builder
            ->if(
                false,
                function ($query) {
                    $query->where('status', 'active');
                },
                function ($query) {
                    $query->where('status', 'inactive');
                }
            );

        $results = $builder->get();
        $this->assertEquals(1, $results->count()); // 1 inactive user
    }

    public function testIfWithCallableValue()
    {
        $builder = $this->builder
            ->if(
                function () {
                    return true;
                },
                function ($query) {
                    $query->where('status', 'active');
                }
            );

        $results = $builder->get();
        $this->assertEquals(4, $results->count());
    }

    public function testDistinct()
    {
        $departments = $this->builder->distinct('department');
        $this->assertInstanceOf(Collection::class, $departments);
        $this->assertContains('Engineering', $departments->toArray());
        $this->assertContains('Marketing', $departments->toArray());
        $this->assertContains('Sales', $departments->toArray());
    }

    public function testGroupConcat()
    {
        $concatenated = $this->builder->groupConcat('name', '|');
        $this->assertIsString($concatenated);
        $this->assertStringContainsString('John Doe', $concatenated);
        $this->assertStringContainsString('Jane Smith', $concatenated);
    }

    public function testReset()
    {
        $builder = $this->builder
            ->where('name', 'John Doe')
            ->orderBy('name', 'DESC')
            ->limit(5)
            ->reset();

        // After reset, should return all records
        $users = $builder->get();
        $this->assertEquals(5, $users->count());
    }

    public function testDynamicWhereMethods()
    {
        $users = $this->builder->whereName('John Doe')->get();
        $this->assertEquals(1, $users->count());
        $this->assertEquals('John Doe', $users[0]['name']);

        $users = $this->builder->whereEmail('john@example.com')->get();
        $this->assertEquals(1, $users->count());
        $this->assertEquals('john@example.com', $users[0]['email']);
    }

    public function testDynamicWhereWithOperator()
    {
        $users = $this->builder->whereAge(25)->get();
        $this->assertEquals(1, $users->count());
        $this->assertEquals(25, $users[0]['age']);
    }

    public function testDynamicWhereWithNull()
    {
        // Add a user with null department
        $this->pdo->exec("INSERT INTO users (name, email, age, department) VALUES ('Null Dept', 'null@example.com', 45, NULL)");

        $users = $this->builder->whereDepartment(null)->get();
        $this->assertGreaterThan(0, $users->count());

        foreach ($users as $user) {
            $this->assertNull($user['department']);
        }
    }

    public function testToSql()
    {
        $sql = $this->builder
            ->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->limit(10)
            ->toSql();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('FROM users', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testToSqlWithNestedWhere()
    {
        $sql = $this->builder
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('age', '>', 25)
                    ->orWhere('department', 'Engineering');
            })
            ->toSql();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('(', $sql);
        $this->assertStringContainsString(')', $sql);
    }

    public function testToSqlWithGroupBy()
    {
        $sql = $this->builder
            ->select('department')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('department')
            ->toSql();

        $this->assertStringContainsString('GROUP BY', $sql);
    }

    // ==================== LAZY LOADING TESTS ====================

    // public function testFetchLazy() // tested by making it public
    // {
    //     $count = 0;
    //     foreach ($this->builder->fetchLazy() as $row) {
    //         $count++;
    //         $this->assertIsArray($row);
    //         $this->assertArrayHasKey('name', $row);
    //     }
    //     $this->assertEquals(5, $count);
    // }

    public function testGetReturnsCollection()
    {
        $results = $this->builder->get();
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertEquals(5, $results->count());
    }

    public function testInsertDuplicateEmailThrowsException()
    {
        $this->expectException(PDOException::class);

        $this->builder->insert([
            'name' => 'Duplicate',
            'email' => 'john@example.com', // Already exists
            'age' => 20
        ]);
    }

    public function testInsertManyWithInvalidRowsThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);

        $rows = [
            ['name' => 'User 1', 'email' => 'user1@example.com'],
            ['name' => 'User 2'] // Missing email
        ];

        $this->builder->insertMany($rows);
    }

    public function testUpsertWithEmptyUniqueByThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder->upsert(
            [['name' => 'User', 'email' => 'user@example.com']],
            [] // Empty uniqueBy
        );
    }

    public function testEmptyConditions()
    {
        $builder = new Builder($this->pdo, 'users');
        $results = $builder->get();
        $this->assertEquals(5, $results->count());
    }

    public function testLimitZero()
    {
        $builder = $this->builder->limit(0);
        $results = $builder->get();
        $this->assertEquals(0, $results->count());
    }

    public function testNegativeLimit()
    {
        $builder = $this->builder->limit(-1);
        $results = $builder->get();
        $this->assertEquals(5, $results->count());
    }

    public function testEmptySelect()
    {
        $builder = $this->builder->select([]);
        $sql = $builder->toSql();
        $this->assertStringContainsString('SELECT  FROM users', $sql);
    }

    public function testWhereWithNullValue()
    {
        $builder = $this->builder->where('name', null);
        $results = $builder->get();
        $this->assertEquals(0, $results->count());
    }

    public function testComplexQueryBuilding()
    {
        $sql = $this->builder
            ->select('id', 'name', 'email')
            ->where('status', 'active')
            ->whereIn('age', [25, 28, 30])
            ->whereNotNull('email')
            ->orderBy('name', 'DESC')
            ->limit(5)
            ->toSql();

        $this->assertStringContainsString('SELECT id, name, email', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY name DESC', $sql);
        $this->assertStringContainsString('LIMIT 5', $sql);
    }

    public function testComplexQueryWithMultipleJoins()
    {
        $this->pdo->exec("
            CREATE TABLE profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE,
                bio TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        $this->pdo->exec("
            INSERT INTO profiles (user_id, bio) VALUES 
            (1, 'John Bio'),
            (2, 'Jane Bio')
        ");

        $sql = $this->builder
            ->select('users.name', 'profiles.bio', 'posts.title')
            ->join('profiles', 'users.id', '=', 'profiles.user_id')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('users.status', 'active')
            ->where(function ($query) {
                $query->where('profiles.bio', 'LIKE', '%Bio%')
                    ->orWhere('posts.title', 'LIKE', '%Post%');
            })
            ->orderBy('users.name', 'ASC')
            ->toSql();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('JOIN', $sql);
        $this->assertStringContainsString('WHERE', $sql);
    }

    public function testPerformanceWithLargeDataset()
    {
        // Insert larger dataset
        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $rows[] = [
                'name' => "User $i",
                'email' => "user$i@example.com",
                'age' => rand(20, 60),
                'status' => $i % 2 == 0 ? 'active' : 'inactive'
            ];
        }

        $startTime = microtime(true);
        $affected = $this->builder->insertMany($rows);
        $insertTime = microtime(true) - $startTime;

        $this->assertEquals(50, $affected);
        $this->assertLessThan(1.0, $insertTime); // Should complete within 1 second

        // Test query performance
        $startTime = microtime(true);
        $users = $this->builder->where('status', 'active')->get();
        $queryTime = microtime(true) - $startTime;

        $this->assertLessThan(0.5, $queryTime); // Should complete within 0.5 seconds
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->builder = null;
    }
}
