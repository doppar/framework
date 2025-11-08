<?php

namespace Tests\Unit\Model\Query;

use Tests\Support\Presenter\MockUserPresenter;
use Tests\Support\Presenter\MockPostPresenter;
use Tests\Support\Model\MockUser;
use Tests\Support\Model\MockPost;
use Tests\Support\MockContainer;
use Phaseolies\Support\Presenter\PresenterBundle;
use Phaseolies\Support\Collection;
use Phaseolies\Http\Request;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use PDO;
use Phaseolies\Support\UrlGenerator;
use Tests\Support\Model\MockComment;
use Tests\Support\Model\MockTag;

class EntityModelQueryTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());
        $container = new Container();
        $container->bind('request', fn() => new Request());
        $container->bind('url', fn() => UrlGenerator::class);

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
            ('Laravel'),
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

    public function testAllMethod()
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

    public function testOrderByWithLimitTest()
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
}
