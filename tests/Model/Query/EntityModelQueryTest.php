<?php

namespace Tests\Unit\Model\Query;

use Tests\Support\Presenter\MockUserPresenter;
use Tests\Support\Presenter\MockPostPresenter;
use Tests\Support\Model\MockUser;
use Tests\Support\Model\MockTag;
use Tests\Support\Model\MockPost;
use Tests\Support\Model\MockComment;
use Tests\Support\MockContainer;
use Phaseolies\Support\UrlGenerator;
use Phaseolies\Support\Presenter\PresenterBundle;
use Phaseolies\Support\Facades\DB;
use Phaseolies\Support\Collection;
use Phaseolies\Http\Request;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use PDO;
use Mockery;

class EntityModelQueryTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());
        $container = new Container();
        $container->bind('request', fn() => new Request());
        $container->bind('url', fn() => UrlGenerator::class);
        $container->bind('db', fn() => new Database('default'));

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTestTables();
        $this->setupDatabaseConnections();
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->tearDownDatabaseConnections();
    }

    private function createTestTables(): void
    {
        // Create users table
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE,
                age INTEGER,
                status TEXT DEFAULT 'active',
                created_at TEXT
            )
        ");

        // Create posts table
        $this->pdo->exec("
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                title TEXT NOT NULL,
                content TEXT,
                status BOOLEAN DEFAULT 1,
                views INTEGER DEFAULT 0,
                created_at TEXT
            )
        ");

        // Create comments table
        $this->pdo->exec("
            CREATE TABLE comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER,
                user_id INTEGER,
                body TEXT NOT NULL,
                approved BOOLEAN DEFAULT 0,
                created_at TEXT
            )
        ");

        // Create tags table
        $this->pdo->exec("
            CREATE TABLE tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )
        ");

        // Create post_tag pivot table
        $this->pdo->exec("
            CREATE TABLE post_tag (
                post_id INTEGER,
                tag_id INTEGER,
                created_at TEXT
            )
        ");

        // Insert test data
        $this->pdo->exec("
            INSERT INTO users (name, email, age, status, created_at) VALUES
            ('John Doe', 'john@example.com', 30, 'active', '2024-01-01 10:00:00'),
            ('Jane Smith', 'jane@example.com', 25, 'active', '2024-01-02 10:00:00'),
            ('Bob Wilson', 'bob@example.com', 35, 'inactive', '2024-01-03 10:00:00')
        ");

        $this->pdo->exec("
            INSERT INTO posts (user_id, title, content, status, views, created_at) VALUES 
            (1, 'First Post', 'Content 1', 1, 100, '2024-01-01 11:00:00'),
            (1, 'Second Post', 'Content 2', 0, 50, '2024-01-02 11:00:00'),
            (2, 'Jane Post', 'Content 3', 1, 200, '2024-01-03 11:00:00'),
            (1, 'Third Post', 'Content 4', 1, 150, '2024-01-04 11:00:00')
        ");

        $this->pdo->exec("
            INSERT INTO comments (post_id, user_id, body, approved, created_at) VALUES 
            (1, 1, 'Great post!', 1, '2024-01-01 12:00:00'),
            (1, 2, 'Nice work', 0, '2024-01-01 13:00:00'),
            (2, 1, 'Interesting', 1, '2024-01-02 12:00:00'),
            (3, 2, 'Amazing', 1, '2024-01-03 12:00:00'),
            (1, 3, 'Awesome', 1, '2024-01-01 14:00:00')
        ");

        $this->pdo->exec("
            INSERT INTO tags (name) VALUES 
            ('PHP'),
            ('Doppar'),
            ('Testing'),
            ('Database')
        ");

        $this->pdo->exec("
            INSERT INTO post_tag (post_id, tag_id, created_at) VALUES 
            (1, 1, '2024-01-01 11:00:00'),
            (1, 2, '2024-01-01 11:00:00'),
            (2, 1, '2024-01-02 11:00:00'),
            (3, 3, '2024-01-03 11:00:00'),
            (4, 4, '2024-01-04 11:00:00')
        ");
    }

    private function setupDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);

        $this->setStaticProperty(Database::class, 'connections', [
            'default' => $this->pdo,
            'sqlite' => $this->pdo
        ]);
    }

    private function tearDownDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function setStaticProperty(string $className, string $propertyName, $value): void
    {
        try {
            $reflection = new \ReflectionClass($className);
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue(null, $value);
            $property->setAccessible(false);
        } catch (\ReflectionException $e) {
            $this->fail("Failed to set static property {$propertyName}: " . $e->getMessage());
        }
    }

    public function testAll()
    {
        //  0 => array:6 [
        //     "id" => 1
        //     "name" => "John Doe"
        //     "email" => "john@example.com"
        //     "age" => 30
        //     "status" => "active"
        //     "created_at" => "2024-01-01 10:00:00"
        //   ]
        //   1 => array:6 [
        //     "id" => 2
        //     "name" => "Jane Smith"
        //     "email" => "jane@example.com"
        //     "age" => 25
        //     "status" => "active"
        //     "created_at" => "2024-01-02 10:00:00"
        //   ]
        //   2 => array:6 [
        //     "id" => 3
        //     "name" => "Bob Wilson"
        //     "email" => "bob@example.com"
        //     "age" => 35
        //     "status" => "inactive"
        //     "created_at" => "2024-01-03 10:00:00"
        //   ]
        // ]
        $users = MockUser::all(); // 3 users


        //   0 => array:7 [
        //     "id" => 1
        //     "user_id" => 1
        //     "title" => "First Post"
        //     "content" => "Content 1"
        //     "status" => 1
        //     "views" => 100
        //     "created_at" => "2024-01-01 11:00:00"
        //   ]
        //   1 => array:7 [
        //     "id" => 2
        //     "user_id" => 1
        //     "title" => "Second Post"
        //     "content" => "Content 2"
        //     "status" => 0
        //     "views" => 50
        //     "created_at" => "2024-01-02 11:00:00"
        //   ]
        //   2 => array:7 [
        //     "id" => 3
        //     "user_id" => 2
        //     "title" => "Jane Post"
        //     "content" => "Content 3"
        //     "status" => 1
        //     "views" => 200
        //     "created_at" => "2024-01-03 11:00:00"
        //   ]
        //   3 => array:7 [
        //     "id" => 4
        //     "user_id" => 1
        //     "title" => "Third Post"
        //     "content" => "Content 4"
        //     "status" => 1
        //     "views" => 150
        //     "created_at" => "2024-01-04 11:00:00"
        //   ]
        $posts = MockPost::all(); // 4 posts

        //   0 => array:6 [
        //     "id" => 1
        //     "post_id" => 1
        //     "user_id" => 1
        //     "body" => "Great post!"
        //     "approved" => 1
        //     "created_at" => "2024-01-01 12:00:00"
        //   ]
        //   1 => array:6 [
        //     "id" => 2
        //     "post_id" => 1
        //     "user_id" => 2
        //     "body" => "Nice work"
        //     "approved" => 0
        //     "created_at" => "2024-01-01 13:00:00"
        //   ]
        //   2 => array:6 [
        //     "id" => 3
        //     "post_id" => 2
        //     "user_id" => 1
        //     "body" => "Interesting"
        //     "approved" => 1
        //     "created_at" => "2024-01-02 12:00:00"
        //   ]
        //   3 => array:6 [
        //     "id" => 4
        //     "post_id" => 3
        //     "user_id" => 2
        //     "body" => "Amazing"
        //     "approved" => 1
        //     "created_at" => "2024-01-03 12:00:00"
        //   ]
        //   4 => array:6 [
        //     "id" => 5
        //     "post_id" => 1
        //     "user_id" => 3
        //     "body" => "Awesome"
        //     "approved" => 1
        //     "created_at" => "2024-01-01 14:00:00"
        //   ]
        // ]
        $comments = MockComment::all(); // 5 comments

        //  0 => array:2 [
        //     "id" => 1
        //     "name" => "PHP"
        //   ]
        //   1 => array:2 [
        //     "id" => 2
        //     "name" => "Laravel"
        //   ]
        //   2 => array:2 [
        //     "id" => 3
        //     "name" => "Testing"
        //   ]
        //   3 => array:2 [
        //     "id" => 4
        //     "name" => "Database"
        //   ]
        // ]
        $tags = MockTag::all(); // 4 tags


        $this->assertCount(3, $users); // we have 3 users
        $this->assertCount(4, $posts); // we have 4 posts
        $this->assertCount(5, $comments); // we have 5 comments
        $this->assertCount(4, $tags); // we have 4 tags

        $this->assertInstanceOf(Collection::class, $users); // we have 3 users
        $this->assertInstanceOf(Collection::class, $posts); // we have 4 posts
        $this->assertInstanceOf(Collection::class, $comments); // we have 5 comments
        $this->assertInstanceOf(Collection::class, $tags); // we have 4 tags

        // Since this is a collection, we can do collection operation like calling count()
        $this->assertEquals(3, $users->count()); // we have 3 users
        $this->assertEquals(4, $posts->count()); // we have 4 posts
        $this->assertEquals(5, $comments->count()); // we have 5 comments
        $this->assertEquals(4, $tags->count()); // we have 5 comments

        // Since this is a collection, we can convert this collection to array
        $this->assertIsArray($users->toArray());
        $this->assertIsArray($posts->toArray());
        $this->assertIsArray($comments->toArray());
        $this->assertIsArray($tags->toArray());

        // Since this is a collection, we should access this collection properties as an object
        foreach ($users->takeLast(1) as $user) {
            $this->assertEquals('Bob Wilson', $user->name); // Last user is = Bob Wilson
            $this->assertEquals(3, $user->id);
        }
    }

    public function testOrderByWithLimit()
    {
        $users = MockUser::where('status', 'active')
            ->orderBy('name')
            ->limit(10)
            ->get();

        // Jane Smith should come first then John Doe
        // And we have 2 active users
        $this->assertCount(2, $users);
        foreach ($users[0] as $user) {
            $this->assertEquals('Jane Smith', $user->name);
        }
    }

    public function testFirstWithWhere()
    {
        $user = MockUser::where('status', 'active')->orderBy('name')->first();
        $this->assertEquals('Jane Smith', $user->name);
    }

    public function testDebug()
    {
        $user = MockUser::where('status', 'active')->orderBy('name')->debug();
        $this->assertIsArray($user, 'Debug output should be an array.');

        // Check that all expected keys exist
        $expectedKeys = [
            'sql',
            'bindings',
            'select',
            'where',
            'order',
            'group',
            'limit',
            'offset',
            'joins',
            'eager_load'
        ];

        // Validate SQL structure
        $this->assertEquals(
            'SELECT * FROM users WHERE status = ? ORDER BY name ASC',
            $user['sql'],
            'SQL statement does not match expected query.'
        );

        // Validate bindings
        $this->assertIsArray($user['bindings']);
        $this->assertCount(1, $user['bindings']);
        $this->assertEquals('active', $user['bindings'][0]);

        // Validate where clause
        $this->assertIsArray($user['where']);
        $this->assertEquals(['AND', 'status', '=', 'active'], $user['where'][0]);

        // Validate order clause
        $this->assertIsArray($user['order']);
        $this->assertEquals(['name', 'ASC'], $user['order'][0]);

        // Validate optional fields
        $this->assertEmpty($user['group']);
        $this->assertNull($user['limit']);
        $this->assertNull($user['offset']);
        $this->assertEmpty($user['joins']);
        $this->assertEmpty($user['eager_load']);
    }

    // public function testDumpSql()
    // {
    //     $builder = MockUser::where('status', 'active')->orderBy('name')->dumpSql();

    //     $this->assertInstanceOf(
    //         \Phaseolies\Database\Entity\Builder::class,
    //         $builder,
    //         'dumpSql() should return a Builder instance.'
    //     );
    // }

    public function testwithMemoryUsage()
    {
        $memoryUsages = MockUser::where('status', 'active')->orderBy('name')->get()->withMemoryUsage();

        $this->assertIsNotFloat($memoryUsages);
        $this->assertGreaterThan(2, $memoryUsages);
    }

    public function testToArray(): void
    {
        $user = MockUser::where('status', 'active')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->toArray();

        $this->assertIsArray($user);
    }

    public function testDynamicWhere(): void
    {
        $user = MockUser::whereName('John Doe')->first();

        $this->assertEquals('John Doe', $user->name);
    }

    public function testMultipleDynamicWhere(): void
    {
        $user = MockUser::whereName('John Doe')->whereStatus('active')->first();

        $this->assertEquals('John Doe', $user->name);
    }

    public function testFirst(): void
    {
        $user = MockUser::first(); // first() from model class
        $userFromBuilderClass = MockUser::where('id', 1)->first(); // first() calling from builder class

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('John Doe', $userFromBuilderClass->name);
    }

    public function testGroupBy(): void
    {
        $user = MockUser::orderBy('id', 'desc')->groupBy('name')->get();

        $this->assertCount(3, $user);
    }

    public function testRandom(): void
    {
        $user = MockUser::random(10)->get();

        $this->assertCount(3, $user);
    }

    public function testToSql(): void
    {
        $user = MockUser::where('status', 'active')->toSql();

        $this->assertEquals('SELECT * FROM users WHERE status = ?', $user);
    }

    public function testFind(): void
    {
        $user = MockUser::where('status', 'active')->find(1); // find() from builder class
        $userFromModel = MockUser::find(1); // find() from model class

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('John Doe', $userFromModel->name);
    }

    public function testFindWithArrayParams(): void
    {
        $users = MockUser::find([1, 2, 3]);

        $this->assertInstanceOf(Collection::class, $users);
        $this->assertIsArray($users->toArray());
    }

    public function testCount(): void
    {
        $user = MockUser::count(); // count() from model class
        $user2 = MockUser::orderBy('id', 'desc')->groupBy('name')->count(); // count() from builder class
        $user3 = MockUser::where('status', 'active')->count(); // count() from builder class

        $this->assertEquals(3, $user);
        $this->assertEquals(3, $user2);
        $this->assertEquals(2, $user3); // we have 2 active users
    }

    public function testNewest()
    {
        $newestFirst = MockUser::newest()->first();

        $this->assertEquals('Bob Wilson', $newestFirst->name);

        $newestFirstAsPerName = MockUser::newest('name')->first();

        $this->assertEquals('John Doe', $newestFirstAsPerName->name);
    }

    public function testOldest()
    {
        $oldestFirst = MockUser::oldest()->first();

        $this->assertEquals('John Doe', $oldestFirst->name);

        $oldestFirstAsPerName = MockUser::oldest('name')->first();

        $this->assertEquals('Bob Wilson', $oldestFirstAsPerName->name);
    }

    public function testSelect()
    {
        // Selecting specific columns using an array
        $users = MockUser::select(['name', 'email'])->get();

        // Selecting specific columns using multiple arguments
        $users2 = MockUser::select('name', 'email')->get();

        // Simulated expected output after applying select('name', 'email')
        $expectedSelected = [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['name' => 'Bob Wilson', 'email' => 'bob@example.com'],
        ];

        $this->assertIsArray($users->toArray(), 'Result of select()->get() should be an array.');
        $this->assertCount(3, $users, 'Should return three user records.');

        foreach ($users as $user) {
            $this->assertArrayHasKey('name', $user);
            $this->assertArrayHasKey('email', $user);

            // Make sure no extra keys are present
            $this->assertSame(['name', 'email'], array_keys($user->toArray()), 'Only name and email should be selected.');
        }

        $this->assertEquals($users, $users2, 'Selecting with array and multiple args should yield identical results.');
        $this->assertEquals($expectedSelected, $users->toArray(), 'Selected fields should match expected trimmed data.');
    }

    public function testOmit(): void
    {
        $users = MockUser::omit('created_at', 'status')->get();

        // -- Exclude age and email using array syntax
        $users2 = MockUser::omit(['age', 'email'])->get();

        // -- Expected results after omitting created_at and status
        $expectedOmit1 = [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => 25],
            ['id' => 3, 'name' => 'Bob Wilson', 'email' => 'bob@example.com', 'age' => 35],
        ];

        // -- Expected results after omitting age and email
        $expectedOmit2 = [
            ['id' => 1, 'name' => 'John Doe', 'status' => 'active', 'created_at' => '2024-01-01 10:00:00'],
            ['id' => 2, 'name' => 'Jane Smith', 'status' => 'active', 'created_at' => '2024-01-02 10:00:00'],
            ['id' => 3, 'name' => 'Bob Wilson', 'status' => 'inactive', 'created_at' => '2024-01-03 10:00:00'],
        ];

        // Ensure we get a collection or iterable object
        $this->assertIsIterable($users, 'omit()->get() should return iterable results.');
        $this->assertCount(3, $users, 'Should return three user records.');

        // Verify omitted fields are not present in first omit()
        foreach ($users as $user) {
            $data = method_exists($user, 'toArray') ? $user->toArray() : (array) $user;

            $this->assertArrayNotHasKey('created_at', $data, 'created_at should be omitted.');
            $this->assertArrayNotHasKey('status', $data, 'status should be omitted.');

            // Optional: make sure the remaining fields exist
            $this->assertArrayHasKey('id', $data);
            $this->assertArrayHasKey('name', $data);
            $this->assertArrayHasKey('email', $data);
            $this->assertArrayHasKey('age', $data);
        }

        // Verify omitted fields are not present in second omit()
        foreach ($users2 as $user) {
            $data = method_exists($user, 'toArray') ? $user->toArray() : (array) $user;

            $this->assertArrayNotHasKey('age', $data, 'age should be omitted.');
            $this->assertArrayNotHasKey('email', $data, 'email should be omitted.');

            $this->assertArrayHasKey('id', $data);
            $this->assertArrayHasKey('name', $data);
            $this->assertArrayHasKey('status', $data);
            $this->assertArrayHasKey('created_at', $data);
        }

        $this->assertEquals($expectedOmit1, $users->toArray(), 'First omit() result should match expected data.');
        $this->assertEquals($expectedOmit2, $users2->toArray(), 'Second omit() result should match expected data.');
    }

    public function testSelectRaw(): void
    {
        $user = MockUser::selectRaw('COUNT(*) as users_count')->first();

        $this->assertEquals(3, $user->users_count);
    }

    public function testGroupByRaw(): void
    {
        $user = MockUser::query()
            ->where('status', 'active')
            ->groupByRaw('status')
            ->get();

        // Should get one result (representing the “active” group).
        $this->assertCount(1, $user);
    }

    public function testWhereLike(): void
    {
        $users = MockUser::whereLike('name', 'j')->get();

        // We have jane and john
        $this->assertCount(2, $users);
    }

    public function testWhereRaw(): void
    {
        $users = MockUser::query()
            ->whereRaw('LOWER(name) LIKE LOWER(?)', ['%john%'])
            ->get();

        $this->assertCount(1, $users);
    }

    public function testOrderByRaw(): void
    {
        $user = MockUser::query()
            ->orderByRaw('id DESC, name ASC')
            ->get();

        $this->assertCount(3, $user);
    }

    public function testGroupByRawComplex(): void
    {
        $user = MockUser::query()
            ->selectRaw("COUNT(*) as total, strftime('%Y', created_at) as year, strftime('%m', created_at) as month")
            ->groupByRaw("strftime('%Y', created_at), strftime('%m', created_at)")
            ->orderByRaw('year DESC, month DESC')
            ->get();

        $expected = [
            [
                'total' => 3,
                'year' => '2024',
                'month' => '01',
            ],
        ];

        $this->assertIsIterable($user, 'Query should return iterable results.');
        $this->assertCount(1, $user, 'Should group into one year-month pair.');

        $row = $user[0] ?? (array) $user->first();
        $this->assertEquals($expected[0]['total'], $row['total']);
        $this->assertEquals($expected[0]['year'], $row['year']);
        $this->assertEquals($expected[0]['month'], $row['month']);
    }

    public function testExists(): void
    {
        $user = MockUser::where('id', 1)->exists();

        $this->assertTrue($user);

        $user = MockUser::where('id', 10)->exists();

        $this->assertFalse($user);
    }

    public function testWhereIn(): void
    {
        $users = MockUser::whereIn('id', [1, 2, 3])->get();

        $this->assertCount(3, $users);

        // With non-exists users
        $users = MockUser::whereIn('id', [1, 2, 3, 100])->get();

        $this->assertCount(3, $users);
    }

    public function testWhereBetween()
    {
        $users = MockUser::query()
            ->whereBetween('created_at', ['2025-02-29', '2025-04-29'])
            ->get();

        $this->assertCount(0, $users);

        $users = MockUser::query()
            ->whereBetween('created_at', ['2024-01-01', '2025-04-29'])
            ->get();

        $this->assertCount(3, $users);

        $users = MockUser::whereBetween('id', [1, 10])->get();

        $this->assertCount(3, $users);
    }

    public function testWhereNotBetween(): void
    {
        $users = MockUser::query()
            ->whereNotBetween('created_at', ['2025-02-29', '2025-04-29'])
            ->get();

        $this->assertCount(3, $users);

        $users = MockUser::query()
            ->whereNotBetween('created_at', ['2024-01-01', '2025-04-29'])
            ->get();

        $this->assertCount(0, $users);

        $users = MockUser::whereNotBetween('id', [1, 10])->get();

        $this->assertCount(0, $users);
    }

    public function testPluck(): void
    {
        $users = MockUser::query()->pluck('name');

        $this->assertInstanceOf(
            \Phaseolies\Support\Collection::class,
            $users,
            'pluck() should return a Collection.'
        );

        $this->assertEquals(
            ['John Doe', 'Jane Smith', 'Bob Wilson'],
            $users->toArray(),
            'pluck() should return an array of names in the correct order.'
        );

        $this->assertCount(3, $users, 'Collection should contain exactly three items.');

        // pluck with key value pair
        $users = MockUser::query()->pluck('name', 'email');
        $this->assertInstanceOf(
            \Phaseolies\Support\Collection::class,
            $users,
            'pluck() should return a Collection.'
        );

        $this->assertEquals(
            [
                'john@example.com' => 'John Doe',
                'jane@example.com' => 'Jane Smith',
                'bob@example.com' => 'Bob Wilson',
            ],
            $users->toArray(),
            'pluck() with key-value should return email => name mapping.'
        );

        $this->assertCount(3, $users, 'Collection should contain exactly three items.');
    }

    public function testWhereDate(): void
    {
        // Where date equals a specific date
        $users = MockUser::query()
            ->whereDate('created_at', '2024-01-02')
            ->get();

        // we have only 1 user that is created 2024-01-02
        $this->assertCount(1, $users);

        // Where date is greater than a specific date
        $users = MockUser::query()
            ->whereDate('created_at', '>', '2023-01-01')
            ->get();

        $this->assertCount(3, $users);

        $users = MockUser::query()
            ->whereDate('created_at', '<', '2023-01-01')
            ->get();

        $this->assertCount(0, $users);
    }

    public function testWhereMonth(): void
    {
        $users = MockUser::query()
            ->whereMonth('created_at', 1)
            ->get();

        $this->assertCount(3, $users);

        $users = MockUser::query()
            ->whereMonth('created_at', 2)
            ->get();

        $this->assertCount(0, $users);
    }

    public function testWhereYear(): void
    {
        // Where year is 2023
        $users = MockUser::query()
            ->whereYear('created_at', 2023)
            ->get();

        $this->assertCount(0, $users);

        // Where year is greater than 2020
        $users = MockUser::query()
            ->whereYear('created_at', '>', 2020)
            ->get();

        $this->assertCount(3, $users);
    }

    public function testWhereDay(): void
    {
        $users = MockUser::query()
            ->whereDay('created_at', 1)
            ->get();

        $this->assertCount(1, $users);

        $users = MockUser::query()
            ->whereDay('created_at',  2)
            ->get();

        $this->assertCount(1, $users);

        $users = MockUser::query()
            ->whereDay('created_at', 3)
            ->get();

        $this->assertCount(1, $users);

        $users = MockUser::query()
            ->whereDay('created_at', 4)
            ->get();

        $this->assertCount(0, $users);
    }

    public function testWhereTime(): void
    {
        $users = MockUser::query()
            ->whereTime('created_at', '>', '14:00:00')
            ->get();

        $this->assertCount(0, $users);

        $users = MockUser::query()
            ->whereTime('created_at', '=', '10:00:00')
            ->get();

        $this->assertCount(3, $users);

        $users = MockUser::query()
            ->whereTime('created_at', '>=', '10:00:00')
            ->get();

        $this->assertCount(3, $users);

        $users = MockUser::query()
            ->whereTime('created_at', '<=', '10:00:00')
            ->get();

        $this->assertCount(3, $users);

        $users = MockUser::query()
            ->whereTime('created_at', '<=', '11:00:00')
            ->get();

        $this->assertCount(3, $users);

        $users = MockUser::query()
            ->whereTime('created_at', '>=', '11:00:00')
            ->get();

        $this->assertCount(0, $users);
    }

    public function testWhereToday(): void
    {
        $users = MockUser::whereToday('created_at')->get();

        $this->assertCount(0, $users);
    }

    public function testWhereYesterday(): void
    {
        $users = MockUser::whereYesterday('created_at')->get();

        $this->assertCount(0, $users);
    }

    public function testWhereThisMonth(): void
    {
        $users = MockUser::whereThisMonth('created_at')->get();

        $this->assertCount(0, $users);
    }

    public function testWhereLastMonth(): void
    {
        $users = MockUser::whereLastMonth('created_at')->get();

        $this->assertCount(0, $users);
    }

    public function testWhereThisYear(): void
    {
        $users = MockUser::whereThisYear('created_at')->get();

        $this->assertCount(0, $users);
    }

    public function testWhereLastYear(): void
    {
        $users = MockUser::whereLastYear('created_at')->get();

        $this->assertCount(3, $users);
    }

    public function testWhereDateBetween(): void
    {
        $users = MockUser::query()
            ->whereDateBetween('created_at', '2023-01-01', '2025-01-31')
            ->get();

        $this->assertCount(3, $users);

        $users = MockUser::query()
            ->whereDateBetween('created_at', '2023-01-01', '2023-01-31')
            ->get();

        $this->assertCount(0, $users);
    }

    public function testWhereDateTimeBetween(): void
    {
        $users = MockUser::query()
            ->whereDateTimeBetween('created_at', '2025-01-01 00:00:00', '2025-10-31 13:59:59')
            ->get();

        $this->assertCount(0, $users);

        $users = MockUser::query()
            ->whereDateTimeBetween('created_at', '2023-01-01 00:00:00', '2025-10-31 13:59:59')
            ->get();

        $this->assertCount(3, $users);
    }

    public function testILike(): void
    {
        $users = MockUser::query()
            ->iLike('name', '%john%')
            ->orderBy('name', 'desc')
            ->get();

        $this->assertCount(1, $users);
    }

    public function testMatch(): void
    {
        $posts = MockPost::match(['user_id' => 1])->get();

        $this->assertCount(3, $posts);

        // We have 2 post views greater than 100 and status 1
        $posts = MockPost::match([
            'user_id' => [1, 2, 3],
            function ($query) {
                $query->where('views', '>', 100)
                    ->where('status', 1);
            }
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->assertCount(2, $posts);
    }

    public function testSearch(): void
    {
        $posts = MockPost::query()
            ->search(attributes: [
                'title',
                'user.name',
                'tags.name',
                'comments.body'
            ], searchTerm: 'Great post') // search by title has 1 post
            ->get();

        $this->assertCount(1, $posts);

        // INSERT INTO post_tag (post_id, tag_id, created_at) VALUES 
        //     (1, 1, '2024-01-01 11:00:00'),
        //     (1, 2, '2024-01-01 11:00:00'), // this should come in search, that means we will get post ID 1
        //     (2, 1, '2024-01-02 11:00:00'),
        //     (3, 3, '2024-01-03 11:00:00'),
        //     (4, 4, '2024-01-04 11:00:00')

        // Doppar tag ID is 2
        $posts = MockPost::query()
            ->search(attributes: [
                'title',
                'user.name',
                'tags.name',
                'comments.body'
            ], searchTerm: 'Doppar') // search by tag name, many to many relationship
            ->get();

        $this->assertCount(1, $posts);
        $this->assertEquals(1, $posts->toArray()[0]['id']);
    }

    public function testNestedWhere()
    {
        $posts = MockPost::query()
            ->where('status', true)
            ->where(function ($query) {
                $query->where('views', '>', 100)
                    ->orWhere(function ($q) {
                        $q->whereYear('created_at', 2024)
                            ->whereIn('user_id', [1, 2, 3]);
                    });
            })
            ->get();

        // All three posts (id 1, 3, 4) satisfy the query → count = 3
        $this->assertCount(3, $posts);
    }

    public function testIf(): void
    {
        $posts = MockPost::query()
            // Executes because true
            ->if(true, function ($query) {
                $query->whereDate('created_at', '2024-01-01');
            })
            ->get();

        $this->assertCount(1, $posts);

        $posts = MockPost::query()
            // Does not execute because 0 is falsy
            ->if(false, function ($query) {
                $query->whereDate('created_at', '2024-01-01');
            })
            ->get();

        $this->assertCount(4, $posts);

        $posts = MockPost::query()
            ->if(
                true,
                // If search is provided, filter by title
                fn($q) => $q->whereLike('title', 'First Post'),

                // If no search is provided, filter by featured status
                fn($q) => $q->where('status', '=', true)
            )
            ->get();

        $this->assertCount(1, $posts);
    }

    public function testQueryBinding(): void
    {
        // we have 3 active post
        $posts = MockPost::active()->get();

        $this->assertCount(3, $posts);

        // we have 3 inactive post
        $posts = MockPost::inactive()->get();

        $this->assertCount(1, $posts);

        // query binding with passing parameter
        $posts = MockPost::filter(true)->get();

        $this->assertCount(3, $posts);

        // query binding with passing parameter
        $posts = MockPost::filter(false)->get();

        $this->assertCount(1, $posts);
    }

    public function testSave(): void
    {
        $tag = new MockTag();
        $tag->name = 'Awesome Doppar';
        $tag->save();

        $this->assertEquals('Awesome Doppar', $tag->name);
    }

    public function testCreate(): void
    {
        $tag = MockTag::create([
            'name' => 'Awesome Doppar'
        ]);

        $this->assertEquals('Awesome Doppar', $tag->name);
    }

    public function testFirstOrCreate(): void
    {
        // First create tag
        MockTag::create([
            'name' => 'Awesome Doppar'
        ]);

        $tag = MockTag::firstOrCreate(
            ['name' => 'Awesome Doppar'],
            ['name' => 'Doppar']
        );

        $this->assertEquals('Awesome Doppar', $tag->name);
    }

    public function testFork(): void
    {
        $tag = MockTag::find(1);
        $newTag = $tag->fork();
        $newTag->name = 'Copy of ' . $tag->name;
        $newTag->save();

        $this->assertEquals('Copy of PHP', $newTag->name);
    }

    public function testUpdate(): void
    {
        $tag = MockTag::find(1);
        $tag->name = 'Nure';
        $tag->save();

        $this->assertEquals('Nure', $tag->name);

        $tag = MockTag::find(1)
            ->update([
                'name' => 'Nure'
            ]);

        $this->assertTrue($tag);
    }

    public function testDirtyAttributes(): void
    {
        $tag = MockTag::find(1);
        $tag->name = 'Nure';
        $dirty = $tag->getDirtyAttributes();

        $this->assertEquals(['name' => 'Nure'], $dirty);

        $tag = MockTag::find(1);
        // No dirty attributes
        $dirty = $tag->getDirtyAttributes();

        $this->assertIsArray($dirty);
        $this->assertEquals([], $dirty);
    }

    public function testTap(): void
    {
        $tag = tap(
            MockTag::find(1),
            function ($tag) {
                $tag->update([
                    'name' => 'Aliba'
                ]);
            }
        );

        $this->assertEquals('Aliba', $tag->name);
    }

    public function testUpdateOrCreate(): void
    {
        // First create tag
        MockTag::create([
            'name' => 'Awesome Doppar'
        ]);

        $tag = MockTag::updateOrCreate(
            ['name' => 'Awesome Doppar'],
            ['name' => 'Doppar']
        );

        // Should updated with the value "Doppar"
        $this->assertEquals('Doppar', $tag->name);

        $tag = MockTag::updateOrIgnore(
            ['name' => 'Doppar'],
            ['name' => 'For Ignore Test']
        );

        // Should ignore
        $this->assertEquals('For Ignore Test', $tag->name);
        $this->assertNotEquals('Doppar', $tag->name);
    }

    public function testFill(): void
    {
        $tag = MockTag::find(1);
        $tag->fill([
            'name' => 'Doppar Updated',
        ]);
        $tag->save();

        $this->assertEquals('Doppar Updated', $tag->name);
    }

    public function testUpsert(): void
    {
        $tag = [
            [
                "name" => "Mahedi"
            ],
            [
                "name" => "Aaliba"
            ],
        ];

        $affectedRows = MockTag::query()->upsert(
            $tag,
            "id",  // Unique constraint
            ["name"], // Only update the "name" column if exists
            true      // Ignore errors (e.g., duplicate key)
        );

        $this->assertEquals(2, $affectedRows);
    }

    public function testSaveMany(): void
    {
        $tag = MockTag::saveMany([
            ['name' => 'John'],
            ['name' => 'John Another'],
        ]);

        $this->assertEquals(2, $tag);

        $tag = MockTag::saveMany([
            ['name' => 'John'],
            ['name' => 'John Another'],
            ['name' => 'John Another One'],
        ]);

        $this->assertEquals(3, $tag);

        $tag = MockTag::saveMany([
            ['name' => 'John'],
            ['name' => 'John Another'],
            ['name' => 'John Another One'],
        ], 1); // saveMany with chunk

        $this->assertEquals(3, $tag);
    }

    public function testDelete(): void
    {
        $tag = MockTag::saveMany([
            ['name' => 'John'],
            ['name' => 'John Another'],
            ['name' => 'John Another One'],
        ]);

        $tag = MockTag::newest()->first();

        $bool = $tag->delete();

        $this->assertTrue($bool);
    }

    public function testPurge(): void
    {
        $tag = MockTag::saveMany([
            ['name' => 'John'],
            ['name' => 'John Another'],
            ['name' => 'John Another One'],
        ]);

        $tag = MockTag::purge(7);

        $this->assertEquals(1, $tag);
    }

    public function testAggregate(): void
    {
        $sum = MockPost::sum('views'); // 500
        $avg = MockPost::avg('views'); // 125
        $max = MockPost::max('views'); // 200.0
        $min = MockPost::min('views'); // 50.0
        $stdDev = MockPost::stdDev('views'); // 55.901699437495
        $variance = MockPost::variance('views'); // 3125.0

        $this->assertEquals(500, $sum);
        $this->assertEquals(125, $avg);
        $this->assertEquals(200.0, $max);
        $this->assertEquals(50.0, $min);
        $this->assertEquals(55.9, number_format($stdDev, 1));
        $this->assertEquals(3125.0, $variance);
    }

    public function testDistinct(): void
    {
        $posts = MockPost::query()->distinct('user_id');

        $this->assertEquals([1, 2], $posts->toArray());
    }

    public function testConditionalGroupBy(): void
    {
        $posts = MockPost::query()
            ->select(['user_id', 'SUM(views * views) as total_views'])
            ->groupBy('user_id')
            ->get();

        $this->assertEquals([
            ['user_id' => 1, 'total_views' => 35000],
            ['user_id' => 2, 'total_views' => 40000],
        ], $posts->toArray());
    }

    public function testColumnIncrement(): void
    {
        $post = MockPost::find(1);
        $post->increment('views'); // Increments by 1 by default

        $this->assertEquals(101, $post->views);
        $post->increment('views', 10); // Increments by 10 + 1 = 11

        $this->assertEquals(111, $post->views); // 111
    }

    public function testColumnDecrement(): void
    {
        $post = MockPost::find(1);
        $post->decrement('views');

        $this->assertEquals(99, $post->views);
        $post->decrement('views', 10);

        $this->assertEquals(89, $post->views);

        $this->assertEquals(1, $post->user_id);

        $post->decrement('views', 1, [
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => 2
        ]);

        $this->assertEquals(2, $post->user_id);

        $post->decrement('views', 1, [
            'created_at' => '2024-01-01 11:00:00',
            'user_id' => 1
        ]);

        $this->assertEquals(1, $post->user_id);
        $this->assertEquals('2024-01-01 11:00:00', $post->created_at);
    }

    public function testWithCollection(): void
    {
        $users = MockUser::all()
            ->map(function ($item) {
                return [
                    'name' => $item->name
                ];
            });

        $this->assertEquals([
            ['name' => 'John Doe'],
            ['name' => 'Jane Smith'],
            ['name' => 'Bob Wilson'],
        ], $users->toArray());
    }

    public function testMapWithPropertySortcut(): void
    {
        // map() with Property Shortcut
        $users = MockUser::all();
        $names = $users->map->name;

        $this->assertEquals([
            'John Doe',
            'Jane Smith',
            'Bob Wilson',
        ], $names->toArray());
    }

    public function testCollectionFilter(): void
    {
        $posts = MockPost::all()
            ->map(function ($item) {
                return [
                    'title' => $item->title,
                    'status' => $item->status
                ];
            })
            ->filter(function ($item) {
                return $item['status'] === 1;
            });

        $this->assertEquals([
            ['title' => 'First Post', 'status' => 1],
            ['title' => 'Jane Post',  'status' => 1],
            ['title' => 'Third Post', 'status' => 1],
        ], $posts->toArray());
    }

    public function testOffsetQuery(): void
    {
        $users = MockUser::query()
            ->select('name')
            ->offset(1)  // Skip the first 1 record
            ->limit(2)  // Retrieve the next 2 records
            ->get();

        $this->assertEquals([
            // ['name' => 'John Doe'], // Should missing from result
            ['name' => 'Jane Smith'],
            ['name' => 'Bob Wilson'],
        ], $users->toArray());
    }

    public function testEntityORMJoin(): void
    {
        $users = MockUser::query()
            ->select('posts.user_id', 'users.name as user_name')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->get();

        $this->assertEquals([
            ['user_id' => 1, 'user_name' => 'John Doe'],
            ['user_id' => 1, 'user_name' => 'John Doe'],
            ['user_id' => 2, 'user_name' => 'Jane Smith'],
            ['user_id' => 1, 'user_name' => 'John Doe'],
        ], $users->toArray());

        $users = MockUser::query()
            ->select('posts.user_id', 'users.name as user_name', 'comments.body')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->join('comments', 'posts.id', '=', 'comments.post_id')
            ->get();

        $this->assertEquals([
            ['user_id' => 1, 'user_name' => 'John Doe',  'body' => 'Great post!'],
            ['user_id' => 1, 'user_name' => 'John Doe',  'body' => 'Nice work'],
            ['user_id' => 1, 'user_name' => 'John Doe',  'body' => 'Interesting'],
            ['user_id' => 2, 'user_name' => 'Jane Smith', 'body' => 'Amazing'],
            ['user_id' => 1, 'user_name' => 'John Doe',  'body' => 'Awesome'],
        ], $users->toArray());
    }

    public function testChunk(): void
    {
        $processed = collect();

        MockUser::query()
            ->chunk(1, function (Collection $users) use (&$processed) {
                foreach ($users as $user) {
                    $processed->push($user->id);
                }
            });

        // We have 3 users
        $this->assertCount(3, $processed);

        // $mock = Mockery::mock();
        // $mock->shouldReceive('handle')->times(3);

        // MockUser::query()
        //     ->where('status', true)
        //     ->chunk(1, function (Collection $users) use ($mock) {
        //         $mock->handle($users);
        //     });

        // Fibar based chunk with concurrency
        $processed = collect();

        MockUser::query()
            ->fchunk(
                chunkSize: 100,
                processor: function (Collection $users) use (&$processed) {
                    foreach ($users as $user) {
                        $processed->push($user->id);
                    }
                },
                concurrency: 4
            );

        // We have 3 users
        $this->assertCount(3, $processed);
    }

    public function testCursor(): void
    {
        $processed = collect();

        MockUser::query()
            ->cursor(function ($user) use (&$processed) {
                $processed->push($user->id);
            });

        // Assert that all 3 users were processed
        $this->assertCount(3, $processed);

        // Fibar based cursor
        $processed = collect();

        MockUser::query()
            ->fcursor(function ($user) use (&$processed) {
                $processed->push($user->id);
            });

        // Assert that all 3 users were processed
        $this->assertCount(3, $processed);
    }

    public function testStream(): void
    {
        $processed = collect();

        $users = MockUser::query()
            ->stream(3);

        foreach ($users as $user) {
            $processed->push($user->id);
        }

        $this->assertCount(3, $processed);

        // Fibar based stream
        $processed = collect();
        foreach (
            MockUser::query()
                ->fstream(3, fn($user) => strtoupper($user->name))
            as $userName
        ) {
            $processed->push($user->id);
        }

        $this->assertCount(3, $processed);
    }

    public function testBatch(): void
    {
        $processed = collect();

        MockUser::query()
            ->batch(
                chunkSize: 500,
                batchProcessor: function ($batch) use (&$processed) {
                    foreach ($batch as $user) {
                        $processed->push($user->id);
                    }
                },
                batchSize: 1000
            );

        $this->assertCount(3, $processed);
    }

    public function testTransactionWhenNoException()
    {
        // Run transaction
        DB::transaction(function () {
            // Update all active users
            MockUser::where('status', 'active')->get()->each(function ($user) {
                $user->update(['age' => $user->age + 1]);
            });

            // Approve all comments
            MockComment::where('approved', 0)->update(['approved' => 1]);

            // Increment views of all posts
            MockPost::get()->each(function ($post) {
                $post->increment('views', 10);
            });
        }, 3); // Retry up to 3 times if deadlock occurs

        // Assert: Users updated
        $updatedAges = MockUser::where('status', 'active')->pluck('age')->toArray();
        $this->assertEquals([31, 26], $updatedAges); // John:30→31, Jane:25→26

        // Assert: Comments approved
        $approvedComments = MockComment::where('approved', 0)->count();
        $this->assertEquals(0, $approvedComments);

        // Assert: Posts views incremented
        $postViews = MockPost::query()->pluck('views')->toArray();
        $this->assertEquals([110, 60, 210, 160], $postViews);
    }

    public function testTransactionOnException()
    {
        $initialUserAges = MockUser::query()->pluck('age')->toArray();
        $initialCommentStatus = MockComment::query()->pluck('approved')->toArray();

        // Run transaction and throw exception
        try {
            DB::transaction(function () {
                // Update users
                MockUser::where('status', 'active')->get()->each(function ($user) {
                    $user->update(['age' => $user->age + 10]);
                });

                // Approve comments
                MockComment::where('approved', 0)->update(['approved' => 1]);

                // Simulate failure
                throw new \Exception('Simulated failure');
            }, 3);
        } catch (\Exception $e) {
            // Expected exception
        }

        // Changes rolled back
        $currentUserAges = MockUser::query()->pluck('age')->toArray();
        $this->assertEquals($initialUserAges, $currentUserAges);

        $currentCommentStatus = MockComment::query()->pluck('approved')->toArray();
        $this->assertEquals($initialCommentStatus, $currentCommentStatus);
    }

    // =====================================================
    // TEST ENTITY RELATIONSHIP
    // =====================================================

    public function testLinkOneRelationship(): void
    {
        // Test Eager Loading
        $user = MockUser::embed('posts')->find(1);
        $this->assertEquals([
            "id" => 1,
            "name" => "John Doe",
            "email" => "john@example.com",
            "age" => 30,
            "status" => "active",
            "created_at" => "2024-01-01 10:00:00",
            "posts" => [
                [
                    "id" => 1,
                    "user_id" => 1,
                    "title" => "First Post",
                    "content" => "Content 1",
                    "status" => 1,
                    "views" => 100,
                    "created_at" => "2024-01-01 11:00:00",
                ],
                [
                    "id" => 2,
                    "user_id" => 1,
                    "title" => "Second Post",
                    "content" => "Content 2",
                    "status" => 0,
                    "views" => 50,
                    "created_at" => "2024-01-02 11:00:00",
                ],
                [
                    "id" => 4,
                    "user_id" => 1,
                    "title" => "Third Post",
                    "content" => "Content 4",
                    "status" => 1,
                    "views" => 150,
                    "created_at" => "2024-01-04 11:00:00",
                ],
            ]
        ], $user->toArray());

        // Test Eager Loading with specific column selection
        // ID, USER_ID should automatically load
        $user = MockUser::embed('posts:title')->find(1);
        $this->assertEquals([
            "id" => 1,
            "name" => "John Doe",
            "email" => "john@example.com",
            "age" => 30,
            "status" => "active",
            "created_at" => "2024-01-01 10:00:00",
            "posts" => [
                [
                    "id" => 1,
                    "user_id" => 1,
                    "title" => "First Post",
                ],
                [
                    "id" => 2,
                    "user_id" => 1,
                    "title" => "Second Post",
                ],
                [
                    "id" => 4,
                    "user_id" => 1,
                    "title" => "Third Post",
                ],
            ]
        ], $user->toArray());


        // Test Nested Eager Loading with multiple relationship and specific column selection
        $user = MockUser::omit('created_at')->embed(['comments:body', 'posts.comments:body'])->find(1);
        $this->assertEquals([
            "id" => 1,
            "name" => "John Doe",
            "email" => "john@example.com",
            "age" => 30,
            "status" => "active",
            // "created_at" => "2024-01-01 10:00:00", // created_at should be ommited
            "comments" => [
                ["body" => "Great post!", "user_id" => 1, "id" => 1],
                ["body" => "Interesting", "user_id" => 1, "id" => 3],
            ],
            "posts" => [
                [
                    "id" => 1,
                    "user_id" => 1,
                    "title" => "First Post",
                    "content" => "Content 1",
                    "status" => 1,
                    "views" => 100,
                    "created_at" => "2024-01-01 11:00:00",
                    "comments" => [
                        ["body" => "Great post!", "post_id" => 1, "id" => 1],
                        ["body" => "Nice work", "post_id" => 1, "id" => 2],
                        ["body" => "Awesome", "post_id" => 1, "id" => 5],
                    ],
                ],
                [
                    "id" => 2,
                    "user_id" => 1,
                    "title" => "Second Post",
                    "content" => "Content 2",
                    "status" => 0,
                    "views" => 50,
                    "created_at" => "2024-01-02 11:00:00",
                    "comments" => [
                        ["body" => "Interesting", "post_id" => 2, "id" => 3],
                    ],
                ],
                [
                    "id" => 4,
                    "user_id" => 1,
                    "title" => "Third Post",
                    "content" => "Content 4",
                    "status" => 1,
                    "views" => 150,
                    "created_at" => "2024-01-04 11:00:00",
                    "comments" => [],
                ],
            ],
        ], $user->toArray());

        // Eager loading with relationship count
        $user = MockUser::omit('created_at')
            ->embedCount('comments')
            ->embed(['comments:body', 'posts.comments:body'])
            ->find(1)
            ->toArray();
        $this->assertEquals([
            "id" => 1,
            "name" => "John Doe",
            "email" => "john@example.com",
            "age" => 30,
            "status" => "active",
            "comments_count" => 2,
            "comments" => [
                ["body" => "Great post!", "user_id" => 1, "id" => 1],
                ["body" => "Interesting", "user_id" => 1, "id" => 3],
            ],
            "posts" => [
                [
                    "id" => 1,
                    "user_id" => 1,
                    "title" => "First Post",
                    "content" => "Content 1",
                    "status" => 1,
                    "views" => 100,
                    "created_at" => "2024-01-01 11:00:00",
                    "comments" => [
                        ["body" => "Great post!", "post_id" => 1, "id" => 1],
                        ["body" => "Nice work", "post_id" => 1, "id" => 2],
                        ["body" => "Awesome", "post_id" => 1, "id" => 5],
                    ],
                ],
                [
                    "id" => 2,
                    "user_id" => 1,
                    "title" => "Second Post",
                    "content" => "Content 2",
                    "status" => 0,
                    "views" => 50,
                    "created_at" => "2024-01-02 11:00:00",
                    "comments" => [
                        ["body" => "Interesting", "post_id" => 2, "id" => 3],
                    ],
                ],
                [
                    "id" => 4,
                    "user_id" => 1,
                    "title" => "Third Post",
                    "content" => "Content 4",
                    "status" => 1,
                    "views" => 150,
                    "created_at" => "2024-01-04 11:00:00",
                    "comments" => [],
                ],
            ],
        ], $user);
    }

    public function testLinkOneLazyLoad(): void
    {
        $user = MockUser::find(1);

        $posts = $user->posts;
        $this->assertCount(3, $posts);

        $this->assertEquals([
            [
                "id" => 1,
                "user_id" => 1,
                "title" => "First Post",
                "content" => "Content 1",
                "status" => 1,
                "views" => 100,
                "created_at" => "2024-01-01 11:00:00",
            ],
            [
                "id" => 2,
                "user_id" => 1,
                "title" => "Second Post",
                "content" => "Content 2",
                "status" => 0,
                "views" => 50,
                "created_at" => "2024-01-02 11:00:00",
            ],
            [
                "id" => 4,
                "user_id" => 1,
                "title" => "Third Post",
                "content" => "Content 4",
                "status" => 1,
                "views" => 150,
                "created_at" => "2024-01-04 11:00:00",
            ],
        ], $posts->toArray());


        $activePosts = $user->posts()->where('status', true)->get(); // should get 2
        $this->assertCount(2, $activePosts);

        $activePosts = $user->posts()->where('status', false)->get(); // should get 1
        $this->assertCount(1, $activePosts);
    }

    public function testBindToRelationship(): void
    {
        $post = MockPost::embed('user.comments')->find(1);
        $this->assertEquals([
            "id" => 1,
            "user_id" => 1,
            "title" => "First Post",
            "content" => "Content 1",
            "status" => 1,
            "views" => 100,
            "created_at" => "2024-01-01 11:00:00",
            "user" => [
                "id" => 1,
                "name" => "John Doe",
                "email" => "john@example.com",
                "age" => 30,
                "status" => "active",
                "created_at" => "2024-01-01 10:00:00",
                "comments" => [
                    [
                        "id" => 1,
                        "post_id" => 1,
                        "user_id" => 1,
                        "body" => "Great post!",
                        "approved" => 1,
                        "created_at" => "2024-01-01 12:00:00",
                    ],
                    [
                        "id" => 3,
                        "post_id" => 2,
                        "user_id" => 1,
                        "body" => "Interesting",
                        "approved" => 1,
                        "created_at" => "2024-01-02 12:00:00",
                    ],
                ],
            ],
        ], $post->toArray());

        $post = MockPost::embed('user.comments:body')->find(1);
        $this->assertEquals([
            "id" => 1,
            "user_id" => 1,
            "title" => "First Post",
            "content" => "Content 1",
            "status" => 1,
            "views" => 100,
            "created_at" => "2024-01-01 11:00:00",
            "user" => [
                "id" => 1,
                "name" => "John Doe",
                "email" => "john@example.com",
                "age" => 30,
                "status" => "active",
                "created_at" => "2024-01-01 10:00:00",
                "comments" => [
                    [
                        "id" => 1,
                        "user_id" => 1,
                        "body" => "Great post!",
                    ],
                    [
                        "id" => 3,
                        "user_id" => 1,
                        "body" => "Interesting",
                    ],
                ],
            ],
        ], $post->toArray());

        $posts = MockPost::embed('user.comments:body')->get();
        $this->assertEquals([
            [
                "id" => 1,
                "user_id" => 1,
                "title" => "First Post",
                "content" => "Content 1",
                "status" => 1,
                "views" => 100,
                "created_at" => "2024-01-01 11:00:00",
                "user" => [
                    "id" => 1,
                    "name" => "John Doe",
                    "email" => "john@example.com",
                    "age" => 30,
                    "status" => "active",
                    "created_at" => "2024-01-01 10:00:00",
                    "comments" => [
                        ["body" => "Great post!", "user_id" => 1, "id" => 1],
                        ["body" => "Interesting", "user_id" => 1, "id" => 3],
                    ],
                ],
            ],
            [
                "id" => 2,
                "user_id" => 1,
                "title" => "Second Post",
                "content" => "Content 2",
                "status" => 0,
                "views" => 50,
                "created_at" => "2024-01-02 11:00:00",
                "user" => [
                    "id" => 1,
                    "name" => "John Doe",
                    "email" => "john@example.com",
                    "age" => 30,
                    "status" => "active",
                    "created_at" => "2024-01-01 10:00:00",
                    "comments" => [
                        ["body" => "Great post!", "user_id" => 1, "id" => 1],
                        ["body" => "Interesting", "user_id" => 1, "id" => 3],
                    ],
                ],
            ],
            [
                "id" => 3,
                "user_id" => 2,
                "title" => "Jane Post",
                "content" => "Content 3",
                "status" => 1,
                "views" => 200,
                "created_at" => "2024-01-03 11:00:00",
                "user" => [
                    "id" => 2,
                    "name" => "Jane Smith",
                    "email" => "jane@example.com",
                    "age" => 25,
                    "status" => "active",
                    "created_at" => "2024-01-02 10:00:00",
                    "comments" => [
                        ["body" => "Nice work", "user_id" => 2, "id" => 2],
                        ["body" => "Amazing", "user_id" => 2, "id" => 4],
                    ],
                ],
            ],
            [
                "id" => 4,
                "user_id" => 1,
                "title" => "Third Post",
                "content" => "Content 4",
                "status" => 1,
                "views" => 150,
                "created_at" => "2024-01-04 11:00:00",
                "user" => [
                    "id" => 1,
                    "name" => "John Doe",
                    "email" => "john@example.com",
                    "age" => 30,
                    "status" => "active",
                    "created_at" => "2024-01-01 10:00:00",
                    "comments" => [
                        ["body" => "Great post!", "user_id" => 1, "id" => 1],
                        ["body" => "Interesting", "user_id" => 1, "id" => 3],
                    ],
                ],
            ],
        ], $posts->toArray());

        $posts = MockPost::omit('created_at')->embedCount('comments')->get();
        $this->assertEquals([
            [
                "id" => 1,
                "user_id" => 1,
                "title" => "First Post",
                "content" => "Content 1",
                "status" => 1,
                "views" => 100,
                // "created_at" => "2024-01-01 11:00:00",
                "comments_count" => 3,
            ],
            [
                "id" => 2,
                "user_id" => 1,
                "title" => "Second Post",
                "content" => "Content 2",
                "status" => 0,
                "views" => 50,
                // "created_at" => "2024-01-02 11:00:00",
                "comments_count" => 1,
            ],
            [
                "id" => 3,
                "user_id" => 2,
                "title" => "Jane Post",
                "content" => "Content 3",
                "status" => 1,
                "views" => 200,
                // "created_at" => "2024-01-03 11:00:00",
                "comments_count" => 1,
            ],
            [
                "id" => 4,
                "user_id" => 1,
                "title" => "Third Post",
                "content" => "Content 4",
                "status" => 1,
                "views" => 150,
                // "created_at" => "2024-01-04 11:00:00",
                "comments_count" => 0,
            ],
        ], $posts->toArray());

        // Count published posts and approved comments for each user
        $posts = MockPost::omit('views', 'created_at', 'status')
            ->embedCount([
                'comments' => fn($q) => $q->where('approved', true),
                'tags',
            ])->get();

        $this->assertEquals([
            [
                "id" => 1,
                "user_id" => 1,
                "title" => "First Post",
                "content" => "Content 1",
                "comments_count" => 2,
                "tags_count" => 2,
            ],
            [
                "id" => 2,
                "user_id" => 1,
                "title" => "Second Post",
                "content" => "Content 2",
                "comments_count" => 1,
                "tags_count" => 1,
            ],
            [
                "id" => 3,
                "user_id" => 2,
                "title" => "Jane Post",
                "content" => "Content 3",
                "comments_count" => 1,
                "tags_count" => 1,
            ],
            [
                "id" => 4,
                "user_id" => 1,
                "title" => "Third Post",
                "content" => "Content 4",
                "comments_count" => 0,
                "tags_count" => 1,
            ],
        ], $posts->toArray());
    }

    public function testLinkMany(): void
    {
        // Complex linkMany relationship and relatinship count
        $posts = MockPost::query()
            ->where('id', 1)
            ->select('id', 'title', 'user_id')
            ->embed([
                'comments:id,body,created_at' => function ($query) {
                    $query->where('approved', true)
                        ->limit(1) // we have 2 comments but should get 1
                        ->oldest('created_at');
                },
                'tags',
                'user:id,name',
            ])
            ->embedCount('comments')
            ->where('status', true)
            ->first();

        $this->assertEquals([
            "id" => 1,
            "title" => "First Post",
            "user_id" => 1,
            "comments_count" => 3,
            "comments" => [
                [
                    "id" => 1,
                    "body" => "Great post!",
                    "created_at" => "2024-01-01 12:00:00",
                    "post_id" => 1,
                ],
            ],
            "tags" => [
                [
                    "id" => 1,
                    "name" => "PHP",
                    "pivot" => (object)[
                        "post_id" => 1,
                        "tag_id" => 1,
                        "created_at" => "2024-01-01 11:00:00",
                    ],
                ],
                [
                    "id" => 2,
                    "name" => "Doppar",
                    "pivot" => (object)[
                        "post_id" => 1,
                        "tag_id" => 2,
                        "created_at" => "2024-01-01 11:00:00",
                    ],
                ],
            ],
            "user" => [
                "id" => 1,
                "name" => "John Doe",
            ],
        ], $posts->toArray());
    }

    public function testBindToMany(): void
    {
        // Complex linkMany relationship and relatinship count
        $posts = MockPost::query()
            ->select('id', 'title', 'user_id')
            ->embed('tags')
            ->embedCount('comments')
            ->get();

        $this->assertEquals([
            [
                "id" => 1,
                "title" => "First Post",
                "user_id" => 1,
                "comments_count" => 3,
                "tags" => [
                    [
                        "id" => 1,
                        "name" => "PHP",
                        "pivot" => (object)[
                            "post_id" => 1,
                            "tag_id" => 1,
                            "created_at" => "2024-01-01 11:00:00",
                        ],
                    ],
                    [
                        "id" => 2,
                        "name" => "Doppar",
                        "pivot" => (object)[
                            "post_id" => 1,
                            "tag_id" => 2,
                            "created_at" => "2024-01-01 11:00:00",
                        ],
                    ],
                ],
            ],
            [
                "id" => 2,
                "title" => "Second Post",
                "user_id" => 1,
                "comments_count" => 1,
                "tags" => [
                    [
                        "id" => 1,
                        "name" => "PHP",
                        "pivot" => (object)[
                            "post_id" => 2,
                            "tag_id" => 1,
                            "created_at" => "2024-01-02 11:00:00",
                        ],
                    ],
                ],
            ],
            [
                "id" => 3,
                "title" => "Jane Post",
                "user_id" => 2,
                "comments_count" => 1,
                "tags" => [
                    [
                        "id" => 3,
                        "name" => "Testing",
                        "pivot" => (object)[
                            "post_id" => 3,
                            "tag_id" => 3,
                            "created_at" => "2024-01-03 11:00:00",
                        ],
                    ],
                ],
            ],
            [
                "id" => 4,
                "title" => "Third Post",
                "user_id" => 1,
                "comments_count" => 0,
                "tags" => [
                    [
                        "id" => 4,
                        "name" => "Database",
                        "pivot" => (object)[
                            "post_id" => 4,
                            "tag_id" => 4,
                            "created_at" => "2024-01-04 11:00:00",
                        ],
                    ],
                ],
            ],
        ], $posts->toArray());
    }

    public function testBindToManyWithTags(): void
    {
        $tag = MockTag::embed('posts')->find(1);
        $this->assertEquals([
            "id" => 1,
            "name" => "PHP",
            "posts" => [
                [
                    "id" => 1,
                    "user_id" => 1,
                    "title" => "First Post",
                    "content" => "Content 1",
                    "status" => 1,
                    "views" => 100,
                    "created_at" => "2024-01-01 11:00:00",
                    "pivot" => (object)[
                        "post_id" => 1,
                        "tag_id" => 1,
                        "created_at" => "2024-01-01 11:00:00",
                    ],
                ],
                [
                    "id" => 2,
                    "user_id" => 1,
                    "title" => "Second Post",
                    "content" => "Content 2",
                    "status" => 0,
                    "views" => 50,
                    "created_at" => "2024-01-02 11:00:00",
                    "pivot" => (object)[
                        "post_id" => 2,
                        "tag_id" => 1,
                        "created_at" => "2024-01-02 11:00:00",
                    ],
                ],
            ],
        ], $tag->toArray());

        $tag = MockTag::embedCount('posts')->find(1);
        $this->assertEquals([
            "id" => 1,
            "name" => "PHP",
            "posts_count" => 2,
        ], $tag->toArray());

        $tag = MockTag::embedCount('posts.comments')->find(1);
        $this->assertEquals([
            "id" => 1,
            "name" => "PHP",
            "posts" => [
                [
                    "id" => 1,
                    "user_id" => 1,
                    "title" => "First Post",
                    "content" => "Content 1",
                    "status" => 1,
                    "views" => 100,
                    "created_at" => "2024-01-01 11:00:00",
                    "pivot" => (object)[
                        "post_id" => 1,
                        "tag_id" => 1,
                        "created_at" => "2024-01-01 11:00:00",
                    ],
                    "comments_count" => 3,
                ],
                [
                    "id" => 2,
                    "user_id" => 1,
                    "title" => "Second Post",
                    "content" => "Content 2",
                    "status" => 0,
                    "views" => 50,
                    "created_at" => "2024-01-02 11:00:00",
                    "pivot" => (object)[
                        "post_id" => 2,
                        "tag_id" => 1,
                        "created_at" => "2024-01-02 11:00:00",
                    ],
                    "comments_count" => 1,
                ],
            ],
        ], $tag->toArray());
    }

    public function testPresent()
    {
        // Retrieve all posts that have at least one comment...
        // post id 4 comment has missing
        $posts = MockPost::query()->present('comments')->get();
        $this->assertEquals([
            [
                "id" => 1,
                "user_id" => 1,
                "title" => "First Post",
                "content" => "Content 1",
                "status" => 1,
                "views" => 100,
                "created_at" => "2024-01-01 11:00:00",
            ],
            [
                "id" => 2,
                "user_id" => 1,
                "title" => "Second Post",
                "content" => "Content 2",
                "status" => 0,
                "views" => 50,
                "created_at" => "2024-01-02 11:00:00",
            ],
            [
                "id" => 3,
                "user_id" => 2,
                "title" => "Jane Post",
                "content" => "Content 3",
                "status" => 1,
                "views" => 200,
                "created_at" => "2024-01-03 11:00:00",
            ],
        ], $posts->toArray());

        $posts = MockPost::query()->absent('comments')->get();
        $this->assertEquals([
            [
                "id" => 4,
                "user_id" => 1,
                "title" => "Third Post",
                "content" => "Content 4",
                "status" => 1,
                "views" => 150,
                "created_at" => "2024-01-04 11:00:00",
            ],
        ], $posts->toArray());

        $posts = MockPost::query()
            ->present('comments', function ($query) {
                $query->where('body', 'Great post!');
            })
            ->get();

        $this->assertEquals([
            [
                "id" => 1,
                "user_id" => 1,
                "title" => "First Post",
                "content" => "Content 1",
                "status" => 1,
                "views" => 100,
                "created_at" => "2024-01-01 11:00:00",
            ],
        ], $posts->toArray());
    }

    public function testIfExists()
    {
        // Retrieve all posts that have at least one comment...
        // post id 4 comment has missing
        $posts = MockPost::query()->ifExists('comments')->get();
        $this->assertEquals([
            [
                "id" => 1,
                "user_id" => 1,
                "title" => "First Post",
                "content" => "Content 1",
                "status" => 1,
                "views" => 100,
                "created_at" => "2024-01-01 11:00:00",
            ],
            [
                "id" => 2,
                "user_id" => 1,
                "title" => "Second Post",
                "content" => "Content 2",
                "status" => 0,
                "views" => 50,
                "created_at" => "2024-01-02 11:00:00",
            ],
            [
                "id" => 3,
                "user_id" => 2,
                "title" => "Jane Post",
                "content" => "Content 3",
                "status" => 1,
                "views" => 200,
                "created_at" => "2024-01-03 11:00:00",
            ],
        ], $posts->toArray());

        $posts = MockPost::query()->ifNotExists('comments')->get();
        $this->assertEquals([
            [
                "id" => 4,
                "user_id" => 1,
                "title" => "Third Post",
                "content" => "Content 4",
                "status" => 1,
                "views" => 150,
                "created_at" => "2024-01-04 11:00:00",
            ],
        ], $posts->toArray());

        $posts = MockPost::query()
            ->ifExists('comments', function ($query) {
                $query->where('body', 'Great post!');
            })
            ->get();

        $this->assertEquals([
            [
                "id" => 1,
                "user_id" => 1,
                "title" => "First Post",
                "content" => "Content 1",
                "status" => 1,
                "views" => 100,
                "created_at" => "2024-01-01 11:00:00",
            ],
        ], $posts->toArray());
    }

    public function testNestedEmbedCondition(): void
    {
        $users = MockUser::query()
            ->ifExists('posts.comments', function ($query) {
                $query->where('body', 'Great post!'); // user_id 1 did this comment
            })
            ->get();

        $this->assertEquals([
            [
                "id" => 1,
                "name" => "John Doe",
                "email" => "john@example.com",
                "age" => 30,
                "status" => "active",
                "created_at" => "2024-01-01 10:00:00",
            ],
        ], $users->toArray());
    }

    public function testWhereLinked(): void
    {
        // Find users who have at least one published post
        // User ID 1 and 2 have posts only
        $users = MockUser::query()
            ->whereLinked('posts', 'status', true)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertEquals([
            [
                "id" => 1,
                "name" => "John Doe",
                "email" => "john@example.com",
                "age" => 30,
                "status" => "active",
                "created_at" => "2024-01-01 10:00:00",
            ],
            [
                "id" => 2,
                "name" => "Jane Smith",
                "email" => "jane@example.com",
                "age" => 25,
                "status" => "active",
                "created_at" => "2024-01-02 10:00:00",
            ],
        ], $users->toArray());

        // Only User ID 1 have inactive posts only
        $users = MockUser::whereLinked('posts', 'status', false)->get();

        $this->assertEquals([
            [
                "id" => 1,
                "name" => "John Doe",
                "email" => "john@example.com",
                "age" => 30,
                "status" => "active",
                "created_at" => "2024-01-01 10:00:00",
            ],
        ], $users->toArray());
    }

    public function testLink(): void
    {
        $post = MockPost::find(1);
        $post->tags()->link([1, 2, 3]);

        $tagIds = $post->tags->pluck('id')->sort()->values()->toArray();
        $this->assertEquals([1, 1, 2, 2, 3], $tagIds);

        // previouly 2 now will be 5
        $post = MockPost::find(1);
        $this->assertCount(5, $post->tags);

        $post = MockPost::find(1);
        $post->tags()->link([1, 2, 3]);
        $this->assertCount(8, $post->tags);
    }

    public function testUnlink(): void
    {
        $post = MockPost::find(1);
        $post->tags()->unlink([1, 2, 3]);

        $tagIds = $post->tags->pluck('id')->sort()->values()->toArray();

        $this->assertEquals([], $tagIds);

        // previouly 2 now will be 5
        $post = MockPost::find(1);
        $this->assertCount(0, $post->tags);

        $post = MockPost::find(1);
        $post->tags()->link([1, 2, 3]);
        $this->assertCount(3, $post->tags);
    }

    public function testPostTagRelateMethod()
    {
        $post = MockPost::find(1);
        $post->tags()->relate([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $post->tags->pluck('id')->sort()->values()->toArray());
        $this->assertCount(1, $post->tags);

        $changes = $post->tags()->relate([1, 2, 4]);
        $this->assertEquals([4], array_keys($changes['attached']));
        $this->assertEquals([2], array_keys($changes['detached']));
        $this->assertEquals([], $changes['updated']);
    }
}
