<?php

namespace Tests\Unit\Builder\Query;

use Tests\Support\MockContainer;
use Phaseolies\Support\UrlGenerator;
use Phaseolies\Support\Collection;
use Phaseolies\Http\Request;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Phaseolies\Auth\Security\PasswordHashing;
use PHPUnit\Framework\TestCase;
use PDO;

class EntityBuilderQueryTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());
        $container = new Container();
        $container->bind('request', fn() => new Request());
        $container->bind('url', fn() => UrlGenerator::class);
        $container->bind('db', fn() => new Database('default'));
        $container->bind('hash', PasswordHashing::class);

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
        $users = db()->bucket('users')->get(); // 3 users


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
        $posts = db()->bucket('posts')->get(); // 4 posts

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
        $comments = db()->bucket('comments')->get(); // 5 comments

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
        $tags = db()->bucket('tags')->get(); // 4 tags


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
    }

    public function testOrderByWithLimit()
    {
        $users = db()->bucket('users')
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(10)
            ->get();

        // And we have 2 active users
        $this->assertCount(2, $users);
    }

    public function testFirstWithWhere()
    {
        $user = db()->bucket('users')->where('status', 'active')->orderBy('name')->first();
        $this->assertEquals('Jane Smith', $user->name);
    }

    public function testToArray(): void
    {
        $user = db()->bucket('users')
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->toArray();

        $this->assertIsArray($user);
    }

    public function testDynamicWhere(): void
    {
        $user = db()->bucket('users')->whereName('John Doe')->first();

        $this->assertEquals('John Doe', $user->name);
    }

    public function testMultipleDynamicWhere(): void
    {
        $user = db()->bucket('users')
            ->whereName('John Doe')->whereStatus('active')->first();

        $this->assertEquals('John Doe', $user->name);
    }

    public function testFirst(): void
    {
        $user = db()->bucket('users')->first();
        $userFromBuilderClass = db()->bucket('users')->where('id', 1)->first();
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('John Doe', $userFromBuilderClass->name);
    }

    public function testGroupBy(): void
    {
        $user = db()->bucket('users')
            ->orderBy('id', 'desc')->groupBy('name')->get();

        $this->assertCount(3, $user);
    }

    public function testToSql(): void
    {
        $user = db()->bucket('users')->where('status', 'active')->toSql();

        $this->assertEquals('SELECT * FROM users WHERE status = ?', $user);
    }

    public function testCount(): void
    {
        $user = db()->bucket('users')->count(); // count() from model class
        $user2 = db()->bucket('users')->orderBy('id', 'desc')->groupBy('name')->count(); // count() from builder class
        $user3 = db()->bucket('users')->where('status', 'active')->count(); // count() from builder class

        $this->assertEquals(3, $user);
        $this->assertEquals(3, $user2);
        $this->assertEquals(2, $user3); // we have 2 active users
    }

    public function testNewest()
    {
        $newestFirst = db()->bucket('users')->newest()->first();

        $this->assertEquals('Bob Wilson', $newestFirst->name);

        $newestFirstAsPerName = db()->bucket('users')->newest('name')->first();

        $this->assertEquals('John Doe', $newestFirstAsPerName->name);
    }

    public function testOldest()
    {
        $oldestFirst = db()->bucket('users')->oldest()->first();

        $this->assertEquals('John Doe', $oldestFirst->name);

        $oldestFirstAsPerName = db()->bucket('users')->oldest('name')->first();

        $this->assertEquals('Bob Wilson', $oldestFirstAsPerName->name);
    }

    public function testSelect()
    {
        // Selecting specific columns using an array
        $users = db()->bucket('users')->select(['name', 'email'])->get();

        // Selecting specific columns using multiple arguments
        $users2 = db()->bucket('users')->select('name', 'email')->get();

        // Simulated expected output after applying select('name', 'email')
        $expectedSelected = [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['name' => 'Bob Wilson', 'email' => 'bob@example.com'],
        ];

        $this->assertCount(3, $users, 'Should return three user records.');

        foreach ($users as $user) {
            $this->assertArrayHasKey('name', $user);
            $this->assertArrayHasKey('email', $user);
        }

        $this->assertEquals($users, $users2, 'Selecting with array and multiple args should yield identical results.');
        $this->assertEquals($expectedSelected, $users->toArray(), 'Selected fields should match expected trimmed data.');
    }

    public function testOmit(): void
    {
        $users = db()->bucket('users')->omit('created_at', 'status')->get();

        // -- Exclude age and email using array syntax
        $users2 = db()->bucket('users')->omit(['age', 'email'])->get();

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
            $data = $user;

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
            $data = $user;

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
        $user = db()->bucket('users')->selectRaw('COUNT(*) as users_count')->first();

        $this->assertEquals(3, $user->users_count);
    }

    public function testGroupByRaw(): void
    {
        $user = db()->bucket('users')
            ->where('status', 'active')
            ->groupByRaw('status')
            ->get();

        // Should get one result (representing the “active” group).
        $this->assertCount(1, $user);
    }

    public function testWhereLike(): void
    {
        $users = db()->bucket('users')->whereLike('name', 'j')->get();

        // We have jane and john
        $this->assertCount(2, $users);
    }

    public function testWhereRaw(): void
    {
        $users = db()->bucket('users')
            ->whereRaw('LOWER(name) LIKE LOWER(?)', ['%john%'])
            ->get();

        $this->assertCount(1, $users);
    }

    public function testOrderByRaw(): void
    {
        $user = db()->bucket('users')
            ->orderByRaw('id DESC, name ASC')
            ->get();

        $this->assertCount(3, $user);
    }

    public function testGroupByRawComplex(): void
    {
        $user = db()->bucket('users')
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
        $user = db()->bucket('users')->where('id', 1)->exists();

        $this->assertTrue($user);

        $user = db()->bucket('users')->where('id', 10)->exists();

        $this->assertFalse($user);
    }

    public function testWhereIn(): void
    {
        $users = db()->bucket('users')->whereIn('id', [1, 2, 3])->get();

        $this->assertCount(3, $users);

        // With non-exists users
        $users = db()->bucket('users')->whereIn('id', [1, 2, 3, 100])->get();

        $this->assertCount(3, $users);
    }

    public function testWhereBetween()
    {
        $users = db()->bucket('users')
            ->whereBetween('created_at', ['2025-02-29', '2025-04-29'])
            ->get();

        $this->assertCount(0, $users);

        $users = db()->bucket('users')
            ->whereBetween('created_at', ['2024-01-01', '2025-04-29'])
            ->get();

        $this->assertCount(3, $users);

        $users = db()->bucket('users')->whereBetween('id', [1, 10])->get();

        $this->assertCount(3, $users);
    }

    public function testWhereNotBetween(): void
    {
        $users = db()->bucket('users')
            ->whereNotBetween('created_at', ['2025-02-29', '2025-04-29'])
            ->get();

        $this->assertCount(3, $users);

        $users = db()->bucket('users')
            ->whereNotBetween('created_at', ['2024-01-01', '2025-04-29'])
            ->get();

        $this->assertCount(0, $users);

        $users = db()->bucket('users')->whereNotBetween('id', [1, 10])->get();

        $this->assertCount(0, $users);
    }

    public function testWhereDate(): void
    {
        // Where date equals a specific date
        $users = db()->bucket('users')
            ->whereDate('created_at', '2024-01-02')
            ->get();

        // we have only 1 user that is created 2024-01-02
        $this->assertCount(1, $users);

        // Where date is greater than a specific date
        $users = db()->bucket('users')
            ->whereDate('created_at', '>', '2023-01-01')
            ->get();

        $this->assertCount(3, $users);

        $users = db()->bucket('users')
            ->whereDate('created_at', '<', '2023-01-01')
            ->get();

        $this->assertCount(0, $users);
    }

    public function testWhereMonth(): void
    {
        $users = db()->bucket('users')
            ->whereMonth('created_at', 1)
            ->get();

        $this->assertCount(3, $users);

        $users = db()->bucket('users')
            ->whereMonth('created_at', 2)
            ->get();

        $this->assertCount(0, $users);
    }

    public function testWhereYear(): void
    {
        // Where year is 2023
        $users = db()->bucket('users')
            ->whereYear('created_at', 2023)
            ->get();

        $this->assertCount(0, $users);

        // Where year is greater than 2020
        $users = db()->bucket('users')
            ->whereYear('created_at', '>', 2020)
            ->get();

        $this->assertCount(3, $users);
    }

    public function testWhereDay(): void
    {
        $users = db()->bucket('users')
            ->whereDay('created_at', 1)
            ->get();

        $this->assertCount(1, $users);

        $users = db()->bucket('users')
            ->whereDay('created_at',  2)
            ->get();

        $this->assertCount(1, $users);

        $users = db()->bucket('users')
            ->whereDay('created_at', 3)
            ->get();

        $this->assertCount(1, $users);

        $users = db()->bucket('users')
            ->whereDay('created_at', 4)
            ->get();

        $this->assertCount(0, $users);
    }

    public function testWhereTime(): void
    {
        $users = db()->bucket('users')
            ->whereTime('created_at', '>', '14:00:00')
            ->get();

        $this->assertCount(0, $users);

        $users = db()->bucket('users')
            ->whereTime('created_at', '=', '10:00:00')
            ->get();

        $this->assertCount(3, $users);

        $users = db()->bucket('users')
            ->whereTime('created_at', '>=', '10:00:00')
            ->get();

        $this->assertCount(3, $users);

        $users = db()->bucket('users')
            ->whereTime('created_at', '<=', '10:00:00')
            ->get();

        $this->assertCount(3, $users);

        $users = db()->bucket('users')
            ->whereTime('created_at', '<=', '11:00:00')
            ->get();

        $this->assertCount(3, $users);

        $users = db()->bucket('users')
            ->whereTime('created_at', '>=', '11:00:00')
            ->get();

        $this->assertCount(0, $users);
    }

    public function testWhereToday(): void
    {
        $users = db()->bucket('users')->whereToday('created_at')->get();

        $this->assertCount(0, $users);
    }

    public function testWhereYesterday(): void
    {
        $users = db()->bucket('users')->whereYesterday('created_at')->get();

        $this->assertCount(0, $users);
    }

    public function testWhereThisMonth(): void
    {
        $users = db()->bucket('users')->whereThisMonth('created_at')->get();

        $this->assertCount(0, $users);
    }

    public function testWhereLastMonth(): void
    {
        $users = db()->bucket('users')->whereLastMonth('created_at')->get();

        $this->assertCount(0, $users);
    }

    public function testWhereThisYear(): void
    {
        $users = db()->bucket('users')->whereThisYear('created_at')->get();

        $this->assertCount(0, $users);
    }

    public function testWhereLastYear(): void
    {
        $users = db()->bucket('users')->whereLastYear('created_at')->get();

        $this->assertCount(3, $users);
    }

    public function testWhereDateBetween(): void
    {
        $users = db()->bucket('users')
            ->whereDateBetween('created_at', '2023-01-01', '2025-01-31')
            ->get();

        $this->assertCount(3, $users);

        $users = db()->bucket('users')
            ->whereDateBetween('created_at', '2023-01-01', '2023-01-31')
            ->get();

        $this->assertCount(0, $users);
    }

    public function testWhereDateTimeBetween(): void
    {
        $users = db()->bucket('users')
            ->whereDateTimeBetween('created_at', '2025-01-01 00:00:00', '2025-10-31 13:59:59')
            ->get();

        $this->assertCount(0, $users);

        $users = db()->bucket('users')
            ->whereDateTimeBetween('created_at', '2023-01-01 00:00:00', '2025-10-31 13:59:59')
            ->get();

        $this->assertCount(3, $users);
    }

    public function testNestedWhere()
    {
        $posts = db()->bucket('posts')
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
        $posts = db()->bucket('posts')
            // Executes because true
            ->if(true, function ($query) {
                $query->whereDate('created_at', '2024-01-01');
            })
            ->get();

        $this->assertCount(1, $posts);

        $posts = db()->bucket('posts')
            // Does not execute because 0 is falsy
            ->if(false, function ($query) {
                $query->whereDate('created_at', '2024-01-01');
            })
            ->get();

        $this->assertCount(4, $posts);

        $posts = db()->bucket('posts')
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

    public function testInsert(): void
    {
        db()->bucket('tags')
            ->insert([
                'name' => 'Bucket'
            ]);

        $tag = db()->bucket('tags')->newest()->first();

        $this->assertEquals('Bucket', $tag->name);
    }

    public function testInsertMany(): void
    {
        db()->bucket('tags')->insertMany([
            [
                'name' => 'Introducing Doppar',
            ],
            [
                'name' => 'Entity Builder Deep Dive',
            ],
            [
                'name' => 'Working with Multiple Connections',
            ],
        ], 1);

        $tag = db()->bucket('tags')->newest()->first();

        $this->assertEquals('Working with Multiple Connections', $tag->name);
    }

    public function testUpdate(): void
    {
        db()->bucket('tags')
            ->where('id', 1)
            ->update([
                'name' => 'Bucket Updated'
            ]);

        $tag = db()->bucket('tags')->whereName('Bucket Updated')->exists();

        $this->assertTrue($tag);
    }

    public function testUpsert(): void
    {
        $users = [
            [
                "name" => "Mahedi",
                "email" => "mahedi@doppar.com",
                "age" => 20,
                'status' => 1,
                'created_at' => now()
            ],
            [
                "name" => "Aaliba",
                "email" => "aliba@doppar.com",
                "age" => 20,
                'status' => 1,
                'created_at' => now()
            ],
            [
                "name" => "Mahedi",
                "email" => "mahedi@doppar.com",
                "age" => 20,
                'status' => 1,
                'created_at' => now()
            ],
        ];

        $affectedRows = db()->bucket('users')
            ->upsert(
                $users,
                "email",  // Unique constraint
                ["name"], // Only update the "name" column if exists
                true      // Ignore errors (e.g., duplicate key)
            );

        $this->assertEquals(2, $affectedRows);
    }

    public function testDelete(): void
    {
        $tag = db()->bucket('comments')->where('id', 1)->delete();

        $this->assertTrue($tag);
    }

    public function testAggregate(): void
    {
        $sum = db()->bucket('posts')->sum('views'); // 500
        $avg = db()->bucket('posts')->avg('views'); // 125
        $max = db()->bucket('posts')->max('views'); // 200.0
        $min = db()->bucket('posts')->min('views'); // 50.0
        $stdDev = db()->bucket('posts')->stdDev('views'); // 55.901699437495
        $variance = db()->bucket('posts')->variance('views'); // 3125.0

        $this->assertEquals(500, $sum);
        $this->assertEquals(125, $avg);
        $this->assertEquals(200.0, $max);
        $this->assertEquals(50.0, $min);
        $this->assertEquals(55.9, number_format($stdDev, 1));
        $this->assertEquals(3125.0, $variance);
    }

    public function testDistinct(): void
    {
        $posts = db()->bucket('posts')->distinct('user_id');

        $this->assertEquals([1, 2], $posts->toArray());
    }

    public function testConditionalGroupBy(): void
    {
        $posts = db()->bucket('posts')
            ->select(['user_id', 'SUM(views * views) as total_views'])
            ->groupBy('user_id')
            ->get();

        $this->assertEquals([
            ['user_id' => 1, 'total_views' => 35000],
            ['user_id' => 2, 'total_views' => 40000],
        ], $posts->toArray());
    }

    public function testWithCollection(): void
    {
        $users = db()->bucket('users')->get()
            ->map(function ($item) {
                return [
                    'name' => $item['name']
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
        $users = db()->bucket('users')->get();
        $names = $users->map->name;

        $this->assertEquals([
            'John Doe',
            'Jane Smith',
            'Bob Wilson',
        ], $names->toArray());
    }

    public function testCollectionFilter(): void
    {
        $posts = db()->bucket('posts')->get()
            ->map(function ($item) {
                return [
                    'title' => $item['title'],
                    'status' => $item['status']
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
        $users = db()->bucket('users')
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

    public function testEntityBuilderJoin(): void
    {
        $users = db()->bucket('users')
            ->select('posts.user_id', 'users.name as user_name')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->get();

        $this->assertEquals([
            ['user_id' => 1, 'user_name' => 'John Doe'],
            ['user_id' => 1, 'user_name' => 'John Doe'],
            ['user_id' => 2, 'user_name' => 'Jane Smith'],
            ['user_id' => 1, 'user_name' => 'John Doe'],
        ], $users->toArray());

        $users = db()->bucket('users')
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
}
