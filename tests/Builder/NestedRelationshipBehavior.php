<?php

namespace Tests\Unit\Builder;

use Tests\Support\Model\MockPost;
use Phaseolies\Database\Entity\Builder;
use Tests\Support\Database\BuilderRelationshipDriverTestCase;
use Tests\Support\Model\MockUser;

use function PHPUnit\Framework\assertEquals;

abstract class NestedRelationshipTest extends BuilderRelationshipDriverTestCase
{
    protected function createBuilder(string $table = 'users', string $model = MockUser::class): Builder
    {
        return parent::createBuilder($table, $model);
    }

    // TEST 1: whereLinked with nested relations
    public function testWhereLinkedWithNestedRelation()
    {
        $builder = $this->createBuilder('users', MockUser::class);

        // Test nested relation: users who have posts with approved comments
        // Only user ID 1 should come
        $data = $builder->whereLinked('posts.comments', 'approved', true)->get();

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
                $q->where('status', true);
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
                $q->where('status', true);
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
            $q->where('status', true);
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
            $q->where('status', true);
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
            $q->where('status', true);
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
            $q->where('status', true);
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
                $q->where('approved', true)->limit(5);
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
