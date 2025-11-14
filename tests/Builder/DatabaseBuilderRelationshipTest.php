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

class DatabaseBuilderRelationshipTest extends TestCase
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
    private function createBuilder(string $table = 'users', string $model = MockUser::class): Builder
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

    public function testEmbedWithSingleRelation()
    {
        $builder = $this->createBuilder();
        $builder->embed('posts');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('posts', $eagerLoad);
        $this->assertNull($eagerLoad['posts']);
    }

    public function testEmbedWithMultipleRelations()
    {
        $builder = $this->createBuilder();
        $builder->embed(['posts', 'comments']);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('posts', $eagerLoad);
        $this->assertArrayHasKey('comments', $eagerLoad);
    }

    public function testEmbedWithCallback()
    {
        $builder = $this->createBuilder();
        $callback = function ($query) {
            $query->where('approved', 1);
        };

        $builder->embed('comments', $callback);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['comments']);
    }

    public function testEmbedCountWithSingleRelation()
    {
        $builder = $this->createBuilder();
        $builder->embedCount('posts');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('count:posts', $eagerLoad);
        $this->assertNull($eagerLoad['count:posts']);
    }

    public function testPresentAddsExistsCondition()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->present('comments');

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['type']);
        $this->assertEquals('AND', $conditions[0]['boolean']);
    }

    public function testPresentWithCallback()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->present('comments', function ($query) {
            $query->where('approved', 1);
        });

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['type']);
    }

    public function testAbsentAddsNotExistsCondition()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->absent('comments');

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('NOT EXISTS', $conditions[0]['type']);
        $this->assertEquals('AND', $conditions[0]['boolean']);
    }

    public function testWhereLinkedWithSimpleRelation()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->whereLinked('comments', 'approved', 1);

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['type']);
    }

    public function testSearchWithSimpleAttributes()
    {
        $builder = $this->createBuilder();
        $builder->search(['name', 'email'], 'john');

        $conditions = $this->getBuilderConditions($builder);

        // Should have a nested WHERE condition
        $this->assertCount(1, $conditions);
        $this->assertEquals('NESTED', $conditions[0]['type']);
    }

    public function testSetRelationInfo()
    {
        $builder = $this->createBuilder();
        $relationInfo = [
            'pivotTable' => 'user_roles',
            'foreignKey' => 'user_id',
            'relatedKey' => 'role_id'
        ];

        $result = $builder->setRelationInfo($relationInfo);

        $this->assertInstanceOf(Builder::class, $result);

        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('relationInfo');
        $property->setAccessible(true);
        $storedInfo = $property->getValue($builder);

        $this->assertEquals($relationInfo, $storedInfo);
    }

    public function testRelationshipMethodsReturnBuilder()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

        $this->assertInstanceOf(Builder::class, $builder->embed('comments'));
        $this->assertInstanceOf(Builder::class, $builder->embedCount('likes'));
        $this->assertInstanceOf(Builder::class, $builder->present('comments'));
        $this->assertInstanceOf(Builder::class, $builder->whereLinked('comments', 'approved', 1));
        $this->assertInstanceOf(Builder::class, $builder->search(['title'], 'test'));
    }

    public function testPresentWithNonExistentRelation()
    {
        $this->expectException(\BadMethodCallException::class);

        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->present('nonExistent');
    }

    public function testWhereLinkedWithNonExistentRelation()
    {
        $this->expectException(\BadMethodCallException::class);

        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->whereLinked('nonExistent', 'field', 'value');
    }

    public function testNestedRelationWithInvalidPath()
    {
        $this->expectException(\BadMethodCallException::class);

        $builder = $this->createBuilder('users', MockUser::class);
        $builder->present('posts.nonExistent');
    }

    public function testEmbedWithEmptyArray()
    {
        $builder = $this->createBuilder();
        $builder->embed([]);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        // Should remain empty
        $this->assertEmpty($eagerLoad);
    }

    public function testPresentWithEmptyResult()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->present('comments');

        // Delete all comments to simulate empty result
        $this->pdo->exec("DELETE FROM comments");

        $results = $builder->get(); // Assuming get() exists
        $this->assertEmpty($results);
    }

    public function testAbsentWithEmptyRelation()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->absent('comments');

        // Delete all comments to simulate no related rows
        $this->pdo->exec("DELETE FROM comments");

        $results = $builder->get();
        $this->assertCount(3, $results); // all posts should appear
    }

    public function testEmbedWithComplexCallback()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->embed('comments', function ($query) {
            $query->where('approved', 1)->orderBy('id', 'DESC');
        });

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['comments']);
    }

    public function testWhereLinkedWithCallback()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->whereLinked('comments', function ($query) {
            $query->where('approved', 1);
        });

        $conditions = $this->getBuilderConditions($builder);
        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['type']);
    }

    public function testEmbedCountWithEmptyRelation()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->embedCount('likes');

        $eagerLoad = $this->getBuilderEagerLoad($builder);
        $this->assertArrayHasKey('count:likes', $eagerLoad);
        $this->assertNull($eagerLoad['count:likes']);
    }

    public function testEmbedCountWithMultipleRelations()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->embedCount(['likes', 'comments']);

        $eagerLoad = $this->getBuilderEagerLoad($builder);
        $this->assertArrayHasKey('count:likes', $eagerLoad);
        $this->assertArrayHasKey('count:comments', $eagerLoad);
    }

    public function testEmbedWithConstraintModifiesQuery()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $builder->embed('posts', function ($query) {
            $query->where('title', 'LIKE', '%First%');
        });

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('posts', $eagerLoad);
        $this->assertIsCallable($eagerLoad['posts']);
    }

    public function testEmbedWithArrayOfRelationsAndCallbacks()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $builder->embed([
            'posts' => function ($query) {
                $query->where('title', 'LIKE', '%Test%');
            },
            'comments' => function ($query) {
                $query->where('approved', 1);
            }
        ]);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('posts', $eagerLoad);
        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['posts']);
        $this->assertIsCallable($eagerLoad['comments']);
    }

    public function testEmbedCountWithCallback()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $callback = function ($query) {
            $query->where('approved', 1);
        };

        $builder->embedCount('comments', $callback);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('count:comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['count:comments']);
    }

    public function testEmbedCountWithArrayOfRelationsAndCallbacks()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $builder->embedCount([
            'posts' => function ($query) {
                $query->where('status', 'published');
            },
            'comments' => function ($query) {
                $query->where('approved', 1);
            }
        ]);

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('count:posts', $eagerLoad);
        $this->assertArrayHasKey('count:comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['count:posts']);
        $this->assertIsCallable($eagerLoad['count:comments']);
    }

    public function testOrPresentAddsExistsConditionWithOrBoolean()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->orPresent('comments');

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['type']);
        $this->assertEquals('OR', $conditions[0]['boolean']);
    }

    public function testOrPresentWithCallback()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->orPresent('comments', function ($query) {
            $query->where('approved', 1);
        });

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['type']);
        $this->assertEquals('OR', $conditions[0]['boolean']);
    }

    public function testOrAbsentAddsNotExistsConditionWithOrBoolean()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->orAbsent('comments');

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('NOT EXISTS', $conditions[0]['type']);
        $this->assertEquals('OR', $conditions[0]['boolean']);
    }

    public function testWhereLinkedWithDifferentOperators()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->whereLinked('comments', 'approved', '!=', 1);

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['type']);
    }

    public function testWhereLinkedWithLikeOperator()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->whereLinked('comments', 'body', 'LIKE', '%great%');

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['type']);
    }

    public function testPresentWithNestedRelation()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        // This should trigger the nested relationship handling
        $builder->present('posts.comments');

        $conditions = $this->getBuilderConditions($builder);

        // Should have EXISTS condition for nested relation
        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['type']);
    }

    public function testWhereLinkedWithNestedRelation()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        $builder->whereLinked('posts.comments', 'approved', 1);

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['type']);
    }

    public function testSearchWithRelationshipAttributes()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $builder->search(['name', 'posts.title'], 'john');

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('NESTED', $conditions[0]['type']);
    }

    public function testSearchWithEmptyTermReturnsSameBuilder()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $originalBuilder = clone $builder;

        $result = $builder->search(['name', 'email'], '');

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertEquals($originalBuilder, $result);
    }

    public function testSearchWithNullTermReturnsSameBuilder()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $originalBuilder = clone $builder;

        $result = $builder->search(['name', 'email'], null);

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertEquals($originalBuilder, $result);
    }

    public function testEmbedCountWithNestedRelation()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $builder->embedCount('posts.comments');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('count:posts.comments', $eagerLoad);
    }

    public function testWithoutEagerLoadClearsEagerLoad()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $builder->embed('posts')->embed('comments');

        $eagerLoadBefore = $this->getBuilderEagerLoad($builder);
        $this->assertCount(2, $eagerLoadBefore);

        $builderWithoutEager = $builder->withoutEagerLoad();
        $eagerLoadAfter = $this->getBuilderEagerLoad($builderWithoutEager);

        $this->assertCount(0, $eagerLoadAfter);
    }

    public function testWithoutEagerLoadSetsSuppressFlag()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $builderWithoutEager = $builder->withoutEagerLoad();

        $reflection = new \ReflectionClass($builderWithoutEager);
        $property = $reflection->getProperty('suppressEagerLoad');
        $property->setAccessible(true);
        $suppressEagerLoad = $property->getValue($builderWithoutEager);
        $property->setAccessible(false);

        $this->assertTrue($suppressEagerLoad);
    }

    public function testLoadMethodWithSingleRelation()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $result = $builder->load('posts');

        $this->assertInstanceOf(Builder::class, $result);
    }

    public function testLoadMethodWithMultipleRelations()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $result = $builder->load(['posts', 'comments']);

        $this->assertInstanceOf(Builder::class, $result);
    }

    public function testLoadMethodWithCallbacks()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $result = $builder->load([
            'posts' => function ($query) {
                $query->where('status', 'published');
            },
            'comments'
        ]);

        $this->assertInstanceOf(Builder::class, $result);
    }

    public function testIfExistsAliasForPresent()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->ifExists('comments');

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('EXISTS', $conditions[0]['type']);
    }

    public function testIfNotExistsAliasForAbsent()
    {
        $builder = $this->createBuilder('posts', MockPost::class);
        $builder->ifNotExists('comments');

        $conditions = $this->getBuilderConditions($builder);

        $this->assertCount(1, $conditions);
        $this->assertEquals('NOT EXISTS', $conditions[0]['type']);
    }

    public function testComplexRelationshipQueryChaining()
    {
        $builder = $this->createBuilder('users', MockUser::class)
            ->where('active', 1)
            ->embed('posts', function ($query) {
                $query->where('status', 'published');
            })
            ->embedCount('comments')
            ->present('posts')
            ->orderBy('name', 'ASC')
            ->limit(10);

        $this->assertInstanceOf(Builder::class, $builder);

        $conditions = $this->getBuilderConditions($builder);
        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertGreaterThan(0, count($conditions));
        $this->assertArrayHasKey('posts', $eagerLoad);
        $this->assertArrayHasKey('count:comments', $eagerLoad);
    }

    public function testMultipleWhereLinkedConditions()
    {
        $builder = $this->createBuilder('users', MockUser::class)
            ->whereLinked('posts', 'status', 'published')
            ->whereLinked('comments', 'approved', 1);

        $conditions = $this->getBuilderConditions($builder);

        $this->assertGreaterThan(0, count($conditions));

        // Should have multiple EXISTS conditions
        $existsCount = 0;
        foreach ($conditions as $condition) {
            if (isset($condition['type']) && $condition['type'] === 'EXISTS') {
                $existsCount++;
            }
        }
        $this->assertGreaterThan(1, $existsCount);
    }

    public function testEmptyEmbedArrayDoesNothing()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $originalBuilder = clone $builder;

        $result = $builder->embed([]);

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertEquals($originalBuilder, $result);
    }

    public function testEmptyEmbedCountArrayDoesNothing()
    {
        $builder = $this->createBuilder('users', MockUser::class);
        $originalBuilder = clone $builder;

        $result = $builder->embedCount([]);

        $this->assertInstanceOf(Builder::class, $result);
        $this->assertEquals($originalBuilder, $result);
    }

    public function testRelationshipMethodsMaintainBuilderState()
    {
        $builder = $this->createBuilder('users', MockUser::class)
            ->where('active', 1)
            ->orderBy('name')
            ->limit(5);

        $originalConditions = $this->getBuilderConditions($builder);
        $originalOrderBy = $this->getBuilderOrderBy($builder);
        $originalLimit = $this->getBuilderLimit($builder);

        $builder->embed('posts')->present('comments');

        $newConditions = $this->getBuilderConditions($builder);
        $newOrderBy = $this->getBuilderOrderBy($builder);
        $newLimit = $this->getBuilderLimit($builder);

        // Original conditions should still be there plus new ones
        $this->assertGreaterThan(count($originalConditions), count($newConditions));
        $this->assertEquals($originalOrderBy, $newOrderBy);
        $this->assertEquals($originalLimit, $newLimit);
    }

    private function getBuilderOrderBy(Builder $builder): array
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('orderBy');
        $property->setAccessible(true);
        $orderBy = $property->getValue($builder);
        $property->setAccessible(false);
        return $orderBy;
    }

    private function getBuilderLimit(Builder $builder): ?int
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('limit');
        $property->setAccessible(true);
        $limit = $property->getValue($builder);
        $property->setAccessible(false);
        return $limit;
    }
}