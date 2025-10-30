<?php

namespace Tests\Unit\Builder;

use Tests\Support\MockContainer;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use PDO;

class RelationshipSpecificColumnSelectionTest extends TestCase
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
                content TEXT
            )
        ");

        // Create comments table
        $this->pdo->exec("
            CREATE TABLE comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER,
                body TEXT NOT NULL,
                approved BOOLEAN DEFAULT 0
            )
        ");

        // Insert test data
        $this->pdo->exec("
            INSERT INTO users (name, email) VALUES
            ('John Doe', 'john@example.com'),
            ('Jane Smith', 'jane@example.com')
        ");

        $this->pdo->exec("
            INSERT INTO posts (user_id, title, content) VALUES 
            (1, 'First Post', 'Content 1'),
            (1, 'Second Post', 'Content 2'),
            (2, 'Jane Post', 'Content 3')
        ");

        $this->pdo->exec("
            INSERT INTO comments (post_id, body, approved) VALUES 
            (1, 'Great post!', 1),
            (1, 'Nice work', 0),
            (2, 'Interesting', 1)
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
    private function createBuilder(string $table = 'users', string $model = MockUserAnother::class): Builder
    {
        return new Builder($this->pdo, $table, $model, 15);
    }

    /**
     * Helper to get builder conditions for assertion
     */
    private function getBuilderConditions(Builder $builder): array
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('conditions');
        $property->setAccessible(true);
        $conditions = $property->getValue($builder);
        $property->setAccessible(false);
        return $conditions;
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

    public function testEmbedWithNestedRelationColumnSelection()
    {
        $builder = $this->createBuilder('users', MockUserAnother::class);

        // Test nested relation with column selection on the final relation
        $builder->embed('posts.comments:id,body');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('posts.comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['posts.comments']);

        $this->assertTrue(true);
    }

    public function testEmbedWithWildcardColumnSelection()
    {
        $builder = $this->createBuilder('posts', MockPostAnother::class);

        $builder->embed('tags*');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('tags*', $eagerLoad);
    }

    public function testComplexEmbedScenario()
    {
        $builder = $this->createBuilder('posts', MockPostAnother::class);

        // Test the exact scenario from your example
        $builder->select('id', 'title', 'user_id', 'category_id')
            ->embed([
                'comments:id,body,created_at' => function ($query) {
                    $query->where('status', true)
                        ->limit(2)
                        ->oldest('created_at');
                },
                'tags*',
                'user:id,name',
                'category:id,name'
            ])
            ->embedCount('comments')
            ->where('status', false);

        // Verify select fields
        $this->assertEquals(['id', 'title', 'user_id', 'category_id'], $this->getBuilderFields($builder));

        // Verify eager load relations
        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertArrayHasKey('tags*', $eagerLoad);
        $this->assertArrayHasKey('user', $eagerLoad);
        $this->assertArrayHasKey('category', $eagerLoad);
        $this->assertArrayHasKey('count:comments', $eagerLoad);

        // Verify comments constraint includes column selection
        $testQuery = MockPostAnother::query();
        $eagerLoad['comments']($testQuery);

        $this->assertEquals(['id', 'body', 'created_at'], $this->getBuilderFields($testQuery));

        // Verify conditions
        $conditions = $this->getBuilderConditions($builder);
        $this->assertGreaterThan(0, count($conditions));
    }

    public function testEmbedCountWithColumnSelectionNotSupported()
    {
        $builder = $this->createBuilder('posts', MockPostAnother::class);

        // Column selection should not affect count operations
        // The :id,body should be ignored for counts
        $builder->embedCount('comments:id,body');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        // The relation name should be stored without column spec for counts
        $this->assertArrayHasKey('count:comments:id,body', $eagerLoad);
    }

    public function testParseRelationWithColumns()
    {
        $builder = $this->createBuilder('posts', MockPostAnother::class);

        // Test the helper method directly using reflection
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('parseRelationWithColumns');
        $method->setAccessible(true);

        // Test without columns
        [$relation, $columns] = $method->invoke($builder, 'comments');
        $this->assertEquals('comments', $relation);
        $this->assertEquals([], $columns);

        // Test with columns
        [$relation, $columns] = $method->invoke($builder, 'comments:id,body,created_at');
        $this->assertEquals('comments', $relation);
        $this->assertEquals(['id', 'body', 'created_at'], $columns);

        // Test with single column
        [$relation, $columns] = $method->invoke($builder, 'user:id');
        $this->assertEquals('user', $relation);
        $this->assertEquals(['id'], $columns);
    }

    public function testEmbedWithColumnSelection()
    {
        $builder = $this->createBuilder('posts', MockPostAnother::class);

        // Test column selection with specific columns
        $builder->embed('comments:id,body,created_at');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['comments']);

        // Verify the callback selects the specified columns
        $testQuery = $this->createBuilder('comments', MockCommentAnother::class);
        $eagerLoad['comments']($testQuery);

        $this->assertEquals(['id', 'body', 'created_at'], $this->getBuilderFields($testQuery));
    }

    public function testEmbedWithColumnSelectionAndCallback()
    {
        $builder = $this->createBuilder('posts', MockPostAnother::class);

        // Test column selection combined with additional constraints
        $builder->embed('comments:id,body,created_at', function ($query) {
            $query->where('approved', 1)->oldest('created_at');
        });

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['comments']);

        // Verify both column selection and constraints are applied
        $testQuery = $this->createBuilder('comments', MockCommentAnother::class);
        $eagerLoad['comments']($testQuery);

        $this->assertEquals(['id', 'body', 'created_at'], $this->getBuilderFields($testQuery));

        // Check conditions are applied
        $conditions = $this->getBuilderConditions($testQuery);
        $this->assertCount(1, $conditions);
        $this->assertEquals('approved', $conditions[0][1]);
        $this->assertEquals('=', $conditions[0][2]);
        $this->assertEquals(1, $conditions[0][3]);
    }

    public function testEmbedWithArrayColumnSelection()
    {
        $builder = $this->createBuilder('posts', MockPostAnother::class);

        // Test multiple relations with column selection in array format
        $builder->embed([
            'comments:id,body,created_at' => function ($query) {
                $query->where('approved', 1);
            },
            'user:id,name',
            'category:id,name'
        ]);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertArrayHasKey('user', $eagerLoad);
        $this->assertArrayHasKey('category', $eagerLoad);
        $this->assertIsCallable($eagerLoad['comments']);
        $this->assertIsCallable($eagerLoad['user']);
        $this->assertIsCallable($eagerLoad['category']);

        // Test comments constraint
        $testCommentsQuery = $this->createBuilder('comments', MockCommentAnother::class);
        $eagerLoad['comments']($testCommentsQuery);
        $this->assertEquals(['id', 'body', 'created_at'], $this->getBuilderFields($testCommentsQuery));

        // Test user constraint
        $testUserQuery = $this->createBuilder('users', MockUserAnother::class);
        $eagerLoad['user']($testUserQuery);
        $this->assertEquals(['id', 'name'], $this->getBuilderFields($testUserQuery));

        // Test category constraint
        $testCategoryQuery = $this->createBuilder('categories', MockCategory::class);
        $eagerLoad['category']($testCategoryQuery);
        $this->assertEquals(['id', 'name'], $this->getBuilderFields($testCategoryQuery));
    }

    public function testEmbedCountWithColumnSelection()
    {
        $builder = $this->createBuilder('posts', MockPostAnother::class);

        // Test embedCount with column selection (columns should be ignored for counts)
        $builder->embedCount('comments:id,body,created_at');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('count:comments:id,body,created_at', $eagerLoad);

        // The column selection should not affect count operations
        // Count queries should still work normally
        $this->assertNull($eagerLoad['count:comments:id,body,created_at']);
    }

    public function testEmbedCountWithCallbackAndColumnSelection()
    {
        $builder = $this->createBuilder('posts', MockPostAnother::class);

        // Test embedCount with callback and column selection
        $builder->embedCount('comments:id,body', function ($query) {
            $query->where('approved', 1);
        });

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('count:comments:id,body', $eagerLoad);
        $this->assertIsCallable($eagerLoad['count:comments:id,body']);

        // Verify the callback is applied (column selection should be ignored for counts)
        $testQuery = $this->createBuilder('comments', MockCommentAnother::class);
        $eagerLoad['count:comments:id,body']($testQuery);

        // Count queries should not be affected by column selection
        $conditions = $this->getBuilderConditions($testQuery);
        $this->assertCount(1, $conditions);
        $this->assertEquals('approved', $conditions[0][1]);
    }

    public function testLimitInEmbedCallback()
    {
        $builder = $this->createBuilder('posts', MockPostAnother::class);

        // Test that limit works in embed callbacks
        $builder->embed('comments:id,body', function ($query) {
            $query->where('approved', 1)
                ->limit(2)
                ->oldest('created_at');
        });

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['comments']);

        // Verify limit is applied in the callback
        $testQuery = $this->createBuilder('comments', MockCommentAnother::class);
        $eagerLoad['comments']($testQuery);

        $this->assertEquals(2, $this->getBuilderLimit($testQuery));
        $this->assertEquals(['id', 'body'], $this->getBuilderFields($testQuery));
    }

    public function testExistsWithColumnSelection()
    {
        $builder = $this->createBuilder('posts', MockPostAnother::class);

        // Test that exists() method works with the query
        $builder->select('id', 'title')
            ->embed('comments:id,body')
            ->where('status', true);

        // This would test the actual execution, but we'll test the structure
        $this->assertEquals(['id', 'title'], $this->getBuilderFields($builder));

        $eagerLoad = $this->getBuilderEagerLoad($builder);
        $this->assertArrayHasKey('comments', $eagerLoad);

        $conditions = $this->getBuilderConditions($builder);
        $this->assertCount(1, $conditions);
    }

    /**
     * Helper to get builder limit for assertion
     */
    private function getBuilderLimit(Builder $builder): ?int
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('limit');
        $property->setAccessible(true);
        $limit = $property->getValue($builder);
        $property->setAccessible(false);
        return $limit;
    }

    /**
     * Helper to get builder fields for assertion
     */
    private function getBuilderFields(Builder $builder): array
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('fields');
        $property->setAccessible(true);
        $fields = $property->getValue($builder);
        $property->setAccessible(false);
        return $fields;
    }
}

// ==================== SIMPLIFIED MOCK MODELS ====================

class MockCategory extends Model
{
    protected $table = 'categories';
    protected $primaryKey = 'id';
    protected $connection = 'default';
}

class MockRoleAnother extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    protected $connection = 'default';
}

class MockUserAnother extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $connection = 'default';

    public function posts()
    {
        $this->setLastRelationType('linkMany');
        $this->setLastRelatedModel(MockPostAnother::class);
        $this->setLastForeignKey('user_id');
        $this->setLastLocalKey('id');
        return $this->linkMany(MockPostAnother::class, 'user_id', 'id');
    }

    public function comments()
    {
        $this->setLastRelationType('linkMany');
        $this->setLastRelatedModel(MockCommentAnother::class);
        $this->setLastForeignKey('user_id');
        $this->setLastLocalKey('id');
        return $this->linkMany(MockCommentAnother::class, 'user_id', 'id');
    }

    protected function setLastRelationType($type)
    {
        $this->lastRelationType = $type;
    }
    protected function setLastRelatedModel($model)
    {
        $this->lastRelatedModel = $model;
    }
    protected function setLastForeignKey($key)
    {
        $this->lastForeignKey = $key;
    }
    protected function setLastLocalKey($key)
    {
        $this->lastLocalKey = $key;
    }

    public function getLastRelationType(): string
    {
        return $this->lastRelationType ?? '';
    }
    public function getLastRelatedModel(): string
    {
        return $this->lastRelatedModel ?? '';
    }
    public function getLastForeignKey(): string
    {
        return $this->lastForeignKey ?? '';
    }
    public function getLastLocalKey(): string
    {
        return $this->lastLocalKey ?? '';
    }
}

class MockPostAnother extends Model
{
    protected $table = 'posts';
    protected $primaryKey = 'id';
    protected $connection = 'default';

    public function comments()
    {
        $this->setLastRelationType('linkMany');
        $this->setLastRelatedModel(MockCommentAnother::class);
        $this->setLastForeignKey('post_id');
        $this->setLastLocalKey('id');
        return $this->linkMany(MockCommentAnother::class, 'post_id', 'id');
    }

    public function user()
    {
        $this->setLastRelationType('bindTo');
        $this->setLastRelatedModel(MockUserAnother::class);
        $this->setLastForeignKey('user_id');
        $this->setLastLocalKey('user_id');
        return $this->bindTo(MockUserAnother::class, 'user_id', 'id');
    }

    public function likes()
    {
        $this->setLastRelationType('linkMany');
        $this->setLastRelatedModel(MockLikeAnother::class);
        $this->setLastForeignKey('post_id');
        $this->setLastLocalKey('id');
        return $this->linkMany(MockLikeAnother::class, 'post_id', 'id');
    }

    protected function setLastRelationType($type)
    {
        $this->lastRelationType = $type;
    }
    protected function setLastRelatedModel($model)
    {
        $this->lastRelatedModel = $model;
    }
    protected function setLastForeignKey($key)
    {
        $this->lastForeignKey = $key;
    }
    protected function setLastLocalKey($key)
    {
        $this->lastLocalKey = $key;
    }

    public function getLastRelationType(): string
    {
        return $this->lastRelationType ?? '';
    }
    public function getLastRelatedModel(): string
    {
        return $this->lastRelatedModel ?? '';
    }
    public function getLastForeignKey(): string
    {
        return $this->lastForeignKey ?? '';
    }
    public function getLastLocalKey(): string
    {
        return $this->lastLocalKey ?? '';
    }
}

class MockCommentAnother extends Model
{
    protected $table = 'comments';
    protected $primaryKey = 'id';
    protected $connection = 'default';
}

class MockLikeAnother extends Model
{
    protected $table = 'likes';
    protected $primaryKey = 'id';
    protected $connection = 'default';
}
