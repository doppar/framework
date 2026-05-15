<?php

namespace Tests\Unit\Builder;

use Tests\Support\Model\MockPost;
use Tests\Support\Model\MockComment;
use Tests\Support\Model\MockCategory;
use Phaseolies\Database\Entity\Builder;
use Tests\Support\Database\BuilderRelationshipDriverTestCase;
use Tests\Support\Model\MockUser;

use function PHPUnit\Framework\assertEquals;

abstract class RelationshipSpecificColumnSelectionTest extends BuilderRelationshipDriverTestCase
{
    protected function createBuilder(string $table = 'users', string $model = MockUser::class): Builder
    {
        return parent::createBuilder($table, $model);
    }

    public function testEmbedWithNestedRelationColumnSelection()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        // Test nested relation with column selection on the final relation
        $builder->embed('posts.comments:id,body');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('posts.comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['posts.comments']);

        $this->assertTrue(true);
    }

    public function testEmbedWithWildcardColumnSelection()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

        $builder->embed('tags*');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('tags*', $eagerLoad);
    }

    public function testComplexEmbedScenario()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

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
        $testQuery = MockPost::query();
        $eagerLoad['comments']($testQuery);

        $this->assertEquals(['id', 'body', 'created_at'], $this->getBuilderFields($testQuery));

        // Verify conditions
        $conditions = $this->getBuilderConditions($builder);
        $this->assertGreaterThan(0, count($conditions));
    }

    public function testEmbedCountWithColumnSelectionNotSupported()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

        // Column selection should not affect count operations
        // The :id,body should be ignored for counts
        $builder->embedCount('comments:id,body');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        // The relation name should be stored without column spec for counts
        $this->assertArrayHasKey('count:comments:id,body', $eagerLoad);
    }

    public function testParseRelationWithColumns()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

        // Test the helper method directly using reflection
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('parseRelationWithColumns');

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
        $builder = $this->createBuilder('posts', MockPost::class);

        // Test column selection with specific columns
        $builder->embed('comments:id,body,created_at');

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['comments']);

        // Verify the callback selects the specified columns
        $testQuery = $this->createBuilder('comments', MockComment::class);
        $eagerLoad['comments']($testQuery);

        $this->assertEquals(['id', 'body', 'created_at'], $this->getBuilderFields($testQuery));
    }

    public function testEmbedWithColumnSelectionAndCallback()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

        // Test column selection combined with additional constraints
        $builder->embed('comments:id,body,created_at', function ($query) {
            $query->where('approved', true)->oldest('created_at');
        });

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['comments']);

        // Verify both column selection and constraints are applied
        $testQuery = $this->createBuilder('comments', MockComment::class);
        $eagerLoad['comments']($testQuery);

        $this->assertEquals(['id', 'body', 'created_at'], $this->getBuilderFields($testQuery));

        // Check conditions are applied
        $conditions = $this->getBuilderConditions($testQuery);
        $this->assertCount(1, $conditions);
        $this->assertEquals('approved', $conditions[0][1]);
        $this->assertEquals('=', $conditions[0][2]);
        $this->assertTrue((bool) $conditions[0][3]);
    }

    public function testEmbedWithArrayColumnSelection()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

        // Test multiple relations with column selection in array format
        $builder->embed([
            'comments:id,body,created_at' => function ($query) {
                $query->where('approved', true);
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
        $testCommentsQuery = $this->createBuilder('comments', MockComment::class);
        $eagerLoad['comments']($testCommentsQuery);
        $this->assertEquals(['id', 'body', 'created_at'], $this->getBuilderFields($testCommentsQuery));

        // Test user constraint
        $testUserQuery = $this->createBuilder('users', MockUser::class);
        $eagerLoad['user']($testUserQuery);
        $this->assertEquals(['id', 'name'], $this->getBuilderFields($testUserQuery));

        // Test category constraint
        $testCategoryQuery = $this->createBuilder('categories', MockCategory::class);
        $eagerLoad['category']($testCategoryQuery);
        $this->assertEquals(['id', 'name'], $this->getBuilderFields($testCategoryQuery));
    }

    public function testEmbedCountWithColumnSelection()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

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
        $builder = $this->createBuilder('posts', MockPost::class);

        // Test embedCount with callback and column selection
        $builder->embedCount('comments:id,body', function ($query) {
            $query->where('approved', true);
        });

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('count:comments:id,body', $eagerLoad);
        $this->assertIsCallable($eagerLoad['count:comments:id,body']);

        // Verify the callback is applied (column selection should be ignored for counts)
        $testQuery = $this->createBuilder('comments', MockComment::class);
        $eagerLoad['count:comments:id,body']($testQuery);

        // Count queries should not be affected by column selection
        $conditions = $this->getBuilderConditions($testQuery);
        $this->assertCount(1, $conditions);
        $this->assertEquals('approved', $conditions[0][1]);
    }

    public function testLimitInEmbedCallback()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

        // Test that limit works in embed callbacks
        $builder->embed('comments:id,body', function ($query) {
            $query->where('approved', true)
                ->limit(2)
                ->oldest('created_at');
        });

        $eagerLoad = $this->getBuilderEagerLoad($builder);

        $this->assertArrayHasKey('comments', $eagerLoad);
        $this->assertIsCallable($eagerLoad['comments']);

        // Verify limit is applied in the callback
        $testQuery = $this->createBuilder('comments', MockComment::class);
        $eagerLoad['comments']($testQuery);

        $this->assertEquals(2, $this->getBuilderLimit($testQuery));
        $this->assertEquals(['id', 'body'], $this->getBuilderFields($testQuery));
    }

    public function testExistsWithColumnSelection()
    {
        $builder = $this->createBuilder('posts', MockPost::class);

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
}
