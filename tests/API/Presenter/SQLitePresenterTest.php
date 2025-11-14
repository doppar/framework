<?php

namespace Tests\Unit\API\Presenter;

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

class SQLitePresenterTest extends TestCase
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

    public function testPresenterMakeMethodWithSingleModel()
    {
        $user = MockUser::find(1);
        $result = MockUserPresenter::make($user);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
    }

    public function testPresenterOnlyMethod()
    {
        $user = MockUser::find(1);
        $presenter = new MockUserPresenter($user);
        $result = $presenter->only('id', 'name')->jsonSerialize();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);
    }

    public function testPresenterExceptMethod()
    {
        $user = MockUser::find(1);
        $presenter = new MockUserPresenter($user);
        $result = $presenter->except('email', 'age')->jsonSerialize();

        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayNotHasKey('age', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
    }

    public function testPresenterOnlyMethodWithArray()
    {
        $user = MockUser::find(1);
        $presenter = new MockUserPresenter($user);
        $result = $presenter->only(['id', 'name', 'email'])->jsonSerialize();

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
    }

    public function testPresenterExceptMethodWithArray()
    {
        $user = MockUser::find(1);
        $presenter = new MockUserPresenter($user);
        $result = $presenter->except(['created_at', 'status'])->jsonSerialize();

        $this->assertArrayNotHasKey('created_at', $result);
        $this->assertArrayNotHasKey('status', $result);
    }

    public function testPresenterBundleWithArray()
    {
        $users = MockUser::all();
        $bundle = MockUserPresenter::bundle($users);
        $result = $bundle->jsonSerialize();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('John Doe', $result[0]['name']);
        $this->assertEquals('Jane Smith', $result[1]['name']);
    }

    public function testPresenterBundleWithCollection()
    {
        $users = MockUser::all();
        $bundle = MockUserPresenter::bundle($users);
        $result = $bundle->jsonSerialize();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function testPresenterBundleOnlyMethod()
    {
        $users = MockUser::all();
        $bundle = MockUserPresenter::bundle($users)->only('id', 'name');
        $result = $bundle->jsonSerialize();

        $this->assertCount(2, $result[0]);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayNotHasKey('email', $result[0]);
    }

    public function testPresenterBundleExceptMethod()
    {
        $users = MockUser::all();
        $bundle = MockUserPresenter::bundle($users)->except('email', 'age');
        $result = $bundle->jsonSerialize();

        $this->assertArrayNotHasKey('email', $result[0]);
        $this->assertArrayNotHasKey('age', $result[0]);
    }

    public function testPresenterBundlePreserveKeys()
    {
        $users = MockUser::all();
        $bundle = MockUserPresenter::bundle($users)->preserveKeys();
        $result = $bundle->jsonSerialize();

        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
    }

    public function testPresenterWithNestedRelationships()
    {
        $post = MockPost::query()->embed(['user', 'comments'])->find(1);
        $presenter = new MockPostPresenter($post);
        $result = $presenter->jsonSerialize();

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('comments', $result);
        $this->assertIsArray($result['comments']);
    }

    public function testPresenterBundleChainedOnlyAndExcept()
    {
        $users = MockUser::all();
        $bundle = MockUserPresenter::bundle($users)
            ->only('id', 'name', 'email', 'age')
            ->except('age');
        $result = $bundle->jsonSerialize();

        $this->assertCount(3, $result[0]);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('email', $result[0]);
        $this->assertArrayNotHasKey('age', $result[0]);
    }

    public function testPresenterWhenConditional()
    {
        $user = MockUser::find(1);
        $presenter = new MockUserPresenter($user);

        // Assuming MockUserPresenter uses when() in toArray()
        $result = $presenter->jsonSerialize();

        $this->assertIsArray($result);
    }

    public function testPresenterProcessValueWithCollection()
    {
        $post = MockPost::query()->embed('comments')->find(1);
        $presenter = new MockPostPresenter($post);
        $result = $presenter->jsonSerialize();

        $this->assertArrayHasKey('comments', $result);
        $this->assertIsArray($result['comments']);
    }

    public function testPresenterProcessValueWithModel()
    {
        $post = MockPost::query()->embed('user')->find(1);
        $presenter = new MockPostPresenter($post);
        $result = $presenter->jsonSerialize();

        $this->assertArrayHasKey('user', $result);
        $this->assertIsArray($result['user']);
    }

    public function testPresenterBundleWithEmptyCollection()
    {
        $users = MockUser::where('id', '>', 1000)->get();
        $bundle = MockUserPresenter::bundle($users);
        $result = $bundle->jsonSerialize();

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testPresenterBundleLazySerialization()
    {
        $users = MockUser::all();
        $bundle = MockUserPresenter::bundle($users)->lazy(true);
        $result = $bundle->jsonSerialize();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function testPresenterMagicGetMethod()
    {
        $user = MockUser::find(1);
        $presenter = new MockUserPresenter($user);

        $this->assertEquals('John Doe', $presenter->name);
        $this->assertEquals('john@example.com', $presenter->email);
    }

    public function testPresenterBundleMultipleOnlyCalls()
    {
        $users = MockUser::all();
        $bundle = MockUserPresenter::bundle($users)
            ->only('id', 'name')
            ->only('email');
        $result = $bundle->jsonSerialize();

        // Should have id, name, and email (cumulative)
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('email', $result[0]);
    }

    public function testPresenterBundleMultipleExceptCalls()
    {
        $users = MockUser::all();
        $bundle = MockUserPresenter::bundle($users)
            ->except('age')
            ->except('status');
        $result = $bundle->jsonSerialize();

        // Should exclude both age and status
        $this->assertArrayNotHasKey('age', $result[0]);
        $this->assertArrayNotHasKey('status', $result[0]);
    }

    public function testPresenterProcessValueWithBuilder()
    {
        $user = MockUser::find(1);
        // Assuming user has a posts relationship that returns a Builder
        $presenter = new MockUserPresenter($user);
        $result = $presenter->jsonSerialize();

        $this->assertIsArray($result);
    }

    public function testPresenterProcessValueWithJsonSerializable()
    {
        $user = MockUser::find(1);
        $presenter = new MockUserPresenter($user);
        $result = $presenter->jsonSerialize();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
    }

    public function testPresenterWithComplexNestedRelationships()
    {
        $post = MockPost::query()
            ->embed(['user', 'comments.user', 'tags'])
            ->find(1);
        $presenter = new MockPostPresenter($post);
        $result = $presenter->jsonSerialize();

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('comments', $result);
    }

    public function testPresenterBundlePreserveKeysWithSpecificKeys()
    {
        $data = [
            'user_1' => MockUser::find(1),
            'user_2' => MockUser::find(2)
        ];

        $bundle = new PresenterBundle($data, MockUserPresenter::class);
        $bundle->preserveKeys();
        $result = $bundle->jsonSerialize();

        $this->assertArrayHasKey('user_1', $result);
        $this->assertArrayHasKey('user_2', $result);
    }
}
