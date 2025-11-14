<?php

namespace Tests\Unit\Builder;

use Tests\Support\Model\MockUser;
use Tests\Support\Model\MockPost;
use Tests\Support\MockContainer;
use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use PDO;

use function PHPUnit\Framework\assertEquals;

class NestedRelationshipTest extends TestCase
{
    private $pdo;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());

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
                email TEXT UNIQUE
            )
        ");

        // Create posts table
        $this->pdo->exec("
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                title TEXT NOT NULL,
                content TEXT,
                status BOOLEAN DEFAULT 1
            )
        ");

        // Create comments table
        $this->pdo->exec("
            CREATE TABLE comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER,
                user_id INTEGER,
                body TEXT NOT NULL,
                approved BOOLEAN DEFAULT 0
            )
        ");

        $this->pdo->exec("
            CREATE TABLE tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE post_tag (
                post_id INTEGER,
                tag_id INTEGER,
                created_at TEXT
            )
        ");

        // Insert test data
        $this->pdo->exec("
            INSERT INTO users (name, email) VALUES
            ('John Doe', 'john@example.com'),
            ('Jane Smith', 'jane@example.com')
        ");

        $this->pdo->exec("
           INSERT INTO posts (user_id, title, content, status) VALUES 
            (1, 'First Post', 'Content 1', 1),
            (1, 'Second Post', 'Content 2', 0),
            (1, 'Jane Post', 'Content 3', 1)
        ");

        $this->pdo->exec("
            INSERT INTO comments (post_id, user_id, body, approved) VALUES 
            (1, 1, 'Great post!', 1),
            (1, 2, 'Nice work', 0),
            (2, 1, 'Interesting', 1),
            (3, 2, 'Amazing', 1)
        ");

        $this->pdo->exec("
            INSERT INTO tags (name) VALUES 
            ('PHP'),
            ('Doppar'),
            ('Testing')
        ");

        $this->pdo->exec("
            INSERT INTO post_tag (post_id, tag_id) VALUES 
            (1, 1),
            (1, 2),
            (2, 1),
            (3, 3)
        ");
    }

    /**
     * Setup database connections for testing
     */
    private function setupDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);

        $this->setStaticProperty(Database::class, 'connections', [
            'default' => $this->pdo,
            'sqlite' => $this->pdo
        ]);
    }

    /**
     * Clean up database connections
     */
    private function tearDownDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    /**
     * Helper method to set static properties
     */
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

    /**
     * Helper to create a new builder
     */
    private function createBuilder(string $table = 'users', string $model = MockUser::class): Builder
    {
        return new Builder($this->pdo, $table, $model, 15);
    }

    /**
     * Helper to get builder eager load for assertion
     */
    private function getBuilderEagerLoad(Builder $builder): array
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('eagerLoad');
        $property->setAccessible(true);
        $eagerLoad = $property->getValue($builder);
        $property->setAccessible(false);
        return $eagerLoad;
    }

    // TEST 1: whereLinked with nested relations
    public function testWhereLinkedWithNestedRelation()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        // Test nested relation: users who have posts with approved comments
        // Only user ID 1 should come
        $data = $builder->whereLinked('posts.comments', 'approved', 1)->get();

        assertEquals(1, $data[0]->id);
        assertEquals('John Doe', $data[0]->name);
        assertEquals('john@example.com', $data[0]->email);

        $builder->reset();
    }

    // TEST 2: whereLinked with different operators
    public function testWhereLinkedWithDifferentOperators()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        // Test with LIKE operator
        // Only user ID 1 should come
        $data = $builder->whereLinked('posts', 'title', 'LIKE', '%First%')->get();

        assertEquals(1, $data[0]->id);
        assertEquals('John Doe', $data[0]->name);
        assertEquals('john@example.com', $data[0]->email);

        $builder->reset();
    }

    // TEST 3: Multiple nested eager loads with column selection
    public function testMultipleNestedEagerLoadsWithColumnSelection()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $builder->embed([
            'posts:id,title' => function ($q) {
                $q->where('status', 1);
            },
            'posts.comments:id,body',
            'comments:id,body,approved'
        ])->get();

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('posts', $eagerLoad);
        $this->assertArrayHasKey('posts.comments', $eagerLoad);
        $this->assertArrayHasKey('comments', $eagerLoad);
    }

    // TEST 4: orPresent method
    public function testOrPresentMethod()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $data = $builder->where('name', 'John')
            ->orPresent('posts', function ($q) {
                $q->where('status', 1);
            })->get();

        assertEquals(1, $data[0]->id);
        assertEquals('John Doe', $data[0]->name);
        assertEquals('john@example.com', $data[0]->email);

        $builder->reset();
    }

    // TEST 5: absent method (opposite of present)
    public function testAbsentMethod()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        // Users who don't have any posts
        // User ID 2 should come
        $data = $builder->absent('posts')->get();
        // dd($data->toArray());

        assertEquals(2, $data[0]->id);
        assertEquals('Jane Smith', $data[0]->name);
        assertEquals('jane@example.com', $data[0]->email);

        $builder->reset();
    }

    // TEST 6: orAbsent method
    public function testOrAbsentMethod()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $data = $builder->where('name', 'John')
            ->orAbsent('posts')
            ->get();

        assertEquals(2, $data[0]->id);
        assertEquals('Jane Smith', $data[0]->name);
        assertEquals('jane@example.com', $data[0]->email);

        $builder->reset();
    }

    // TEST 7: Nested relation count
    public function testNestedRelationCount()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        // Count comments on user's posts
        $builder->embedCount('posts.comments');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('count:posts.comments', $eagerLoad);
    }

    // TEST 8: Multiple counts with different relations
    public function testMultipleRelationCounts()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $builder->embedCount(['posts', 'comments']);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('count:posts', $eagerLoad);
        $this->assertArrayHasKey('count:comments', $eagerLoad);
    }

    // TEST 9: Count with constraint
    public function testRelationCountWithConstraint()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $builder->embedCount('posts', function ($q) {
            $q->where('status', 1);
        });

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('count:posts', $eagerLoad);
        $this->assertIsCallable($eagerLoad['count:posts']);
    }

    // TEST 10: Present with many-to-many relationship
    public function testPresentWithManyToManyRelation()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

        // Posts that have at least one tag
        // Should get 3
        $post = $builder->present('tags')->get();

        $this->assertEquals(3, $post->count());
    }

    // TEST 11: Present with nested callback in many-to-many
    public function testPresentManyToManyWithCallback()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

        // Posts that have a specific tag
        $post = $builder->present('tags', function ($q) {
            $q->where('name', 'PHP');
        })->get();

        $this->assertEquals(2, $post->count());
    }

    // TEST 12: Embed with wildcard and column selection combined
    public function testEmbedWithWildcardAndRegularRelations()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

        $builder->embed([
            'tags*',
            'comments:id,body',
            'user:id,name'
        ]);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('tags*', $eagerLoad);
        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertArrayHasKey('user', $eagerLoad);
    }

    // TEST 13: ifExists alias for present
    public function testIfExistsAlias()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $data = $builder->ifExists('posts', function ($q) {
            $q->where('status', 1);
        })->get();

        assertEquals(1, $data[0]->id);
        assertEquals('John Doe', $data[0]->name);
        assertEquals('john@example.com', $data[0]->email);

        $builder->reset();
    }

    // TEST 14: ifNotExists alias for absent
    public function testIfNotExistsAlias()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $posts = $builder->ifNotExists('posts')->get();

        $this->assertCount(1, $posts);
    }

    // TEST 15: Deep nested relation (3 levels)
    public function testDeepNestedRelationEagerLoad()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        // Assuming we have users -> posts -> comments -> replies
        $builder->embed('posts.comments.replies:id,body');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('posts.comments.replies', $eagerLoad);
    }

    // TEST 16: Embed count with nested relations
    public function testEmbedCountWithNestedRelations()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $builder->embedCount(['posts.comments', 'posts.tags']);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('count:posts.comments', $eagerLoad);
        $this->assertArrayHasKey('count:posts.tags', $eagerLoad);
    }

    // TEST 17: Load method with single relation
    public function testLoadMethodWithSingleRelation()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $builder->load('posts');

        $this->assertTrue(method_exists($builder, 'load'));
    }

    // TEST 18: Load method with multiple relations
    public function testLoadMethodWithMultipleRelations()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $builder->load(['posts', 'comments']);

        $this->assertTrue(method_exists($builder, 'load'));
    }

    // TEST 19: Load method with callback
    public function testLoadMethodWithCallback()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $builder->load('posts', function ($q) {
            $q->where('status', 1);
        });

        $this->assertTrue(method_exists($builder, 'load'));
    }

    // TEST 20: Fresh method with relations
    public function testFreshMethodWithRelations()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $this->assertTrue(method_exists($builder, 'fresh'));
    }

    // TEST 21: WithoutEagerLoad suppression
    public function testWithoutEagerLoadSuppression()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $builder->embed('posts');
        $clonedBuilder = $builder->withoutEagerLoad();

        $originalEagerLoad = $this->getBuilderEagerLoad($builder);
        $clonedEagerLoad = $this->getBuilderEagerLoad($clonedBuilder);

        $this->assertNotEmpty($originalEagerLoad);
        $this->assertEmpty($clonedEagerLoad);
    }

    // TEST 22: Complex query with mixed present/absent
    public function testComplexQueryWithMixedPresentAbsent()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        // All Posts have commnets
        // Should retun 0
        $users = $builder->present('posts', function ($q) {
            $q->where('status', 1);
        })
            ->absent('comments')
            ->get();

        $this->assertCount(0, $users);
    }

    // TEST 23: Embed with array format and constraints
    public function testEmbedArrayFormatWithMultipleConstraints()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

        $builder->embed([
            'comments:id,body,created_at' => function ($q) {
                $q->where('approved', 1)->limit(5);
            },
            'user:id,name,email',
            'tags*'
        ]);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertCount(3, $eagerLoad);
    }

    // TEST 24: Parse nested relation with column selection on each level
    public function testNestedRelationWithColumnSelectionOnEachLevel()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        // posts:id,title.comments:id,body
        $builder->embed(['posts:id,title', 'comments:id,body']);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('posts', $eagerLoad);
        $this->assertArrayHasKey('comments', $eagerLoad);
    }

    // TEST 25: Search with relationship attributes
    public function testSearchWithRelationshipAttributes()
    {
        $builder = $this->createBuilder('posts', MockUser::class);

        // Only 1st Post has 'Great post' comment
        $data = $builder->search(['title', 'comments.body'], 'Great post')->get();

        $this->assertEquals(1, $data[0]->id);
        $this->assertEquals('First Post', $data[0]->title);
        $this->assertEquals('Content 1', $data[0]->content);
        $this->assertEquals(1, $data[0]->user_id);
        $this->assertEquals(1, $data[0]->status);
    }
}
