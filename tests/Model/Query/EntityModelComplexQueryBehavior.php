<?php

namespace Tests\Unit\Model\Query;

use Phaseolies\Support\Collection;
use Phaseolies\Support\Facades\DB;
use Tests\Support\Database\ModelQueryDriverTestCase;
use Tests\Support\Model\MockAnotherUser;
use Tests\Support\Model\MockComment;
use Tests\Support\Model\MockPost;
use Tests\Support\Model\MockTag;
use Tests\Support\Model\MockUser;

abstract class EntityModelComplexQueryTest extends ModelQueryDriverTestCase
{
    protected function tableDefinitions(): array
    {
        return [
            'users' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'email', 'type' => 'string', 'nullable' => true],
                ['name' => 'age', 'type' => 'integer', 'nullable' => true],
                ['name' => 'status', 'type' => 'string', 'nullable' => true, 'default' => 'active'],
                ['name' => 'score', 'type' => 'real', 'nullable' => true, 'default' => 0],
                ['name' => 'bio', 'type' => 'text', 'nullable' => true],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true],
                ['name' => 'updated_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'userss' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'email', 'type' => 'string', 'nullable' => true, 'unique' => true],
                ['name' => 'age', 'type' => 'integer', 'nullable' => true],
                ['name' => 'status', 'type' => 'string', 'nullable' => true, 'default' => 'active'],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true],
                ['name' => 'updated_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'posts' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'user_id', 'type' => 'integer'],
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'content', 'type' => 'text', 'nullable' => true],
                ['name' => 'status', 'type' => 'string', 'nullable' => true, 'default' => 'published'],
                ['name' => 'views', 'type' => 'integer', 'nullable' => true, 'default' => 0],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true],
                ['name' => 'updated_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'comments' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'post_id', 'type' => 'integer'],
                ['name' => 'user_id', 'type' => 'integer'],
                ['name' => 'body', 'type' => 'text'],
                ['name' => 'approved', 'type' => 'integer', 'nullable' => true, 'default' => 0],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'tags' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'name', 'type' => 'string'],
            ],
            'post_tag' => [
                ['name' => 'post_id', 'type' => 'integer'],
                ['name' => 'tag_id', 'type' => 'integer'],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'product' => [
                ['name' => 'price', 'type' => 'integer', 'nullable' => true],
            ],
        ];
    }

    protected function seedData(): array
    {
        return [
            'users' => [
                ['name' => 'Alice Smith', 'email' => 'alice@example.com', 'age' => 30, 'status' => 'active', 'score' => 95.5, 'bio' => 'Developer', 'created_at' => '2024-01-01 10:00:00', 'updated_at' => '2024-01-01 10:00:00'],
                ['name' => 'Bob Jones', 'email' => 'bob@example.com', 'age' => 25, 'status' => 'active', 'score' => 72.0, 'bio' => 'Designer', 'created_at' => '2024-01-02 10:00:00', 'updated_at' => '2024-01-02 10:00:00'],
                ['name' => 'Carol White', 'email' => 'carol@example.com', 'age' => 35, 'status' => 'inactive', 'score' => 55.0, 'bio' => null, 'created_at' => '2024-01-03 10:00:00', 'updated_at' => '2024-01-03 10:00:00'],
                ['name' => 'Dave Brown', 'email' => null, 'age' => 40, 'status' => 'active', 'score' => 88.0, 'bio' => 'Manager', 'created_at' => '2024-01-04 10:00:00', 'updated_at' => '2024-01-04 10:00:00'],
                ['name' => 'Eve Davis', 'email' => 'eve@example.com', 'age' => 22, 'status' => 'inactive', 'score' => 40.0, 'bio' => 'Intern', 'created_at' => '2024-01-05 10:00:00', 'updated_at' => '2024-01-05 10:00:00'],
                ['name' => 'Frank Miller', 'email' => 'frank@example.com', 'age' => 45, 'status' => 'active', 'score' => 91.0, 'bio' => 'Architect', 'created_at' => '2024-01-06 10:00:00', 'updated_at' => '2024-01-06 10:00:00'],
                ['name' => 'Grace Lee', 'email' => 'grace@example.com', 'age' => 28, 'status' => 'active', 'score' => 63.0, 'bio' => null, 'created_at' => '2024-01-07 10:00:00', 'updated_at' => '2024-01-07 10:00:00'],
                ['name' => 'Henry Wilson', 'email' => 'henry@example.com', 'age' => 33, 'status' => 'inactive', 'score' => 77.0, 'bio' => 'DevOps', 'created_at' => '2024-01-08 10:00:00', 'updated_at' => '2024-01-08 10:00:00'],
                ['name' => 'Irene Clark', 'email' => 'irene@example.com', 'age' => 29, 'status' => 'active', 'score' => 82.0, 'bio' => 'QA', 'created_at' => '2024-01-09 10:00:00', 'updated_at' => '2024-01-09 10:00:00'],
                ['name' => 'James Scott', 'email' => 'james@example.com', 'age' => 50, 'status' => 'active', 'score' => 99.0, 'bio' => 'CTO', 'created_at' => '2024-01-10 10:00:00', 'updated_at' => '2024-01-10 10:00:00'],
            ],
            'posts' => [
                ['user_id' => 1, 'title' => 'Alice Post One', 'content' => 'Content 1', 'status' => 'published', 'views' => 1000, 'created_at' => '2024-02-01 10:00:00', 'updated_at' => '2024-02-01 10:00:00'],
                ['user_id' => 1, 'title' => 'Alice Post Two', 'content' => 'Content 2', 'status' => 'draft', 'views' => 500, 'created_at' => '2024-02-02 10:00:00', 'updated_at' => '2024-02-02 10:00:00'],
                ['user_id' => 2, 'title' => 'Bob Post One', 'content' => 'Content 3', 'status' => 'published', 'views' => 200, 'created_at' => '2024-02-03 10:00:00', 'updated_at' => '2024-02-03 10:00:00'],
                ['user_id' => 3, 'title' => 'Carol Post One', 'content' => 'Content 4', 'status' => 'published', 'views' => 750, 'created_at' => '2024-02-04 10:00:00', 'updated_at' => '2024-02-04 10:00:00'],
                ['user_id' => 4, 'title' => 'Dave Post One', 'content' => 'Content 5', 'status' => 'draft', 'views' => 300, 'created_at' => '2024-02-05 10:00:00', 'updated_at' => '2024-02-05 10:00:00'],
                ['user_id' => 6, 'title' => 'Frank Post One', 'content' => 'Content 6', 'status' => 'published', 'views' => 1500, 'created_at' => '2024-02-06 10:00:00', 'updated_at' => '2024-02-06 10:00:00'],
                ['user_id' => 6, 'title' => 'Frank Post Two', 'content' => 'Content 7', 'status' => 'published', 'views' => 100, 'created_at' => '2024-02-07 10:00:00', 'updated_at' => '2024-02-07 10:00:00'],
                ['user_id' => 9, 'title' => 'Irene Post One', 'content' => 'Content 8', 'status' => 'draft', 'views' => 600, 'created_at' => '2024-02-08 10:00:00', 'updated_at' => '2024-02-08 10:00:00'],
                ['user_id' => 10, 'title' => 'James Post One', 'content' => 'Content 9', 'status' => 'published', 'views' => 2000, 'created_at' => '2024-02-09 10:00:00', 'updated_at' => '2024-02-09 10:00:00'],
                ['user_id' => 10, 'title' => 'James Post Two', 'content' => 'Content 10', 'status' => 'published', 'views' => 900, 'created_at' => '2024-02-10 10:00:00', 'updated_at' => '2024-02-10 10:00:00'],
            ],
            'comments' => [
                ['post_id' => 1, 'user_id' => 2, 'body' => 'Great post!', 'approved' => 1, 'created_at' => '2024-03-01 10:00:00'],
                ['post_id' => 1, 'user_id' => 3, 'body' => 'Very helpful.', 'approved' => 1, 'created_at' => '2024-03-02 10:00:00'],
                ['post_id' => 1, 'user_id' => 4, 'body' => 'Thanks Alice!', 'approved' => 0, 'created_at' => '2024-03-03 10:00:00'],
                ['post_id' => 6, 'user_id' => 1, 'body' => 'Love it', 'approved' => 1, 'created_at' => '2024-03-04 10:00:00'],
                ['post_id' => 9, 'user_id' => 6, 'body' => 'Nice draft', 'approved' => 0, 'created_at' => '2024-03-05 10:00:00'],
                ['post_id' => 9, 'user_id' => 7, 'body' => 'Looking forward', 'approved' => 1, 'created_at' => '2024-03-06 10:00:00'],
            ],
            'tags' => [
                ['name' => 'php'],
                ['name' => 'orm'],
                ['name' => 'database'],
                ['name' => 'performance'],
                ['name' => 'testing'],
            ],
            'post_tag' => [
                ['post_id' => 1, 'tag_id' => 1, 'created_at' => '2024-02-01'],
                ['post_id' => 1, 'tag_id' => 2, 'created_at' => '2024-02-01'],
                ['post_id' => 1, 'tag_id' => 3, 'created_at' => '2024-02-01'],
                ['post_id' => 6, 'tag_id' => 1, 'created_at' => '2024-02-06'],
                ['post_id' => 6, 'tag_id' => 4, 'created_at' => '2024-02-06'],
                ['post_id' => 9, 'tag_id' => 5, 'created_at' => '2024-02-08'],
                ['post_id' => 10, 'tag_id' => 1, 'created_at' => '2024-02-09'],
                ['post_id' => 10, 'tag_id' => 2, 'created_at' => '2024-02-09'],
                ['post_id' => 10, 'tag_id' => 4, 'created_at' => '2024-02-09'],
            ],
        ];
    }

    public function testOrWhereNullOnLargeDataset(): void
    {
        $users = MockUser::where('status', 'active')
            ->orWhere('email', null)
            ->get();

        // 7 active users, Dave (null email) already among them → 7 total
        $this->assertCount(7, $users);
        $this->assertInstanceOf(Collection::class, $users);
    }

    // /**
    //  * orWhere(null) SQL must use IS NULL, never '= ?'.
    //  */
    public function testOrWhereNullSqlShape(): void
    {
        $sql = MockUser::where('status', 'active')
            ->orWhere('email', null)
            ->toSql();

        $this->assertStringContainsString('IS NULL', strtoupper($sql));
        $this->assertStringNotContainsString('email = ?', $sql);
    }

    /**
     * whereNull + whereIn combined — Fix 3 binding order.
     * Users in ids [1,2,3,4] AND email IS NULL → only Dave (id=4).
     */
    public function testWhereNullCombinedWithWhereIn(): void
    {
        $users = MockUser::whereIn('id', [1, 2, 3, 4])
            ->whereNull('email')
            ->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Dave Brown', $users->first()->name);
    }

    /**
     * orWhereNull alongside regular condition.
     * inactive OR bio IS NULL → Carol(inactive,null bio), Eve(inactive), Henry(inactive),
     * Grace(active,null bio) = 4
     */
    public function testOrWhereNullAlongsideWhereCondition(): void
    {
        $users = MockUser::where('status', 'inactive')
            ->orWhereNull('bio')
            ->get();

        // inactive: Carol,Eve,Henry + bio IS NULL: Grace(active) = 4 distinct
        $this->assertCount(4, $users);
        $names = $users->map->name->toArray();
        $this->assertContains('Grace Lee', $names);
        $this->assertContains('Carol White', $names);
    }

    /**
     * whereNotNull filters correctly through ORM.
     */
    public function testWhereNotNullFiltersNullRows(): void
    {
        $users = MockUser::whereNotNull('email')->get();

        $this->assertCount(9, $users); // Dave has no email → 9 results
        foreach ($users as $user) {
            $this->assertNotNull($user->email);
        }
    }

    /**
     * Nested OR inside AND — ORM Builder must bracket correctly.
     * active users where (age >= 40 OR score > 90)
     */
    public function testNestedOrInsideAndCondition(): void
    {
        $users = MockUser::where('status', 'active')
            ->where(function ($q) {
                $q->where('age', '>=', 40)
                    ->orWhere('score', '>', 90);
            })
            ->orderBy('id')
            ->get();

        // age>=40 active: Dave(40),Frank(45),James(50)
        // score>90 active: Alice(95.5),Frank(91),James(99)
        // union: Alice,Dave,Frank,James = 4
        $this->assertCount(4, $users);
        $names = $users->map->name->toArray();
        $this->assertContains('Alice Smith', $names);
        $this->assertContains('Dave Brown', $names);
        $this->assertContains('Frank Miller', $names);
        $this->assertContains('James Scott', $names);
        $this->assertNotContains('Bob Jones', $names);
    }

    /**
     * Triple nested conditions — deep nesting must not corrupt bindings.
     * published posts OR (views BETWEEN 100–500 AND status = 'draft')
     */
    public function testTripleNestedConditionsOnPosts(): void
    {
        $posts = MockPost::where('status', 'published')
            ->orWhere(function ($q) {
                $q->whereBetween('views', [100, 500])
                    ->where('status', 'draft');
            })
            ->get();

        // published: 7 posts | draft BETWEEN 100-500: Alice2(500),Dave(300) = 2 | total = 9
        $this->assertCount(9, $posts);
    }

    /**
     * whereIn + whereBetween combined — Fix 4 binding integrity.
     */
    public function testWhereInAndWhereBetweenCombined(): void
    {
        $posts = MockPost::whereIn('user_id', [1, 2, 6, 10])
            ->whereBetween('views', [200, 1500])
            ->orderBy('views')
            ->get();

        foreach ($posts as $post) {
            $this->assertContains((int)$post->user_id, [1, 2, 6, 10]);
            $this->assertGreaterThanOrEqual(200, (int)$post->views);
            $this->assertLessThanOrEqual(1500, (int)$post->views);
        }
        $this->assertGreaterThan(0, $posts->count());
    }

    /**
     * Multiple whereIn on different columns — no binding list overlap.
     */
    public function testMultipleWhereInOnDifferentColumns(): void
    {
        $users = MockUser::whereIn('status', ['active', 'inactive'])
            ->whereIn('age', [25, 30, 35])
            ->orderBy('age')
            ->get();

        // Alice(30,active), Bob(25,active), Carol(35,inactive)
        $this->assertCount(3, $users);
        $names = $users->map->name->toArray();
        $this->assertContains('Alice Smith', $names);
        $this->assertContains('Bob Jones', $names);
        $this->assertContains('Carol White', $names);
    }

    /**
     * distinct() with whereIn — uses buildWhereClause() path.
     */
    public function testDistinctWithWhereIn(): void
    {
        // distinct statuses from users with id in [1,3,5] → active + inactive
        $statuses = MockUser::query()
            ->whereIn('id', [1, 3, 5])
            ->distinct('status');

        $this->assertInstanceOf(Collection::class, $statuses);
        $this->assertCount(2, $statuses);
        $this->assertContains('active', $statuses->toArray());
        $this->assertContains('inactive', $statuses->toArray());
    }

    /**
     * distinct() with whereBetween.
     */
    public function testDistinctWithWhereBetween(): void
    {
        // distinct user_ids from posts where views BETWEEN 100 AND 600
        $userIds = MockPost::query()
            ->whereBetween('views', [100, 600])
            ->distinct('user_id');

        $this->assertInstanceOf(Collection::class, $userIds);
        $this->assertGreaterThan(0, $userIds->count());
    }

    /**
     * distinct() with nested OR — Fix 3 regression.
     */
    public function testDistinctWithNestedOrCondition(): void
    {
        // distinct user_ids from posts that are draft OR views > 900
        $userIds = MockPost::query()
            ->where('status', 'draft')
            ->orWhere('views', '>', 900)
            ->distinct('user_id');

        // draft: user 1,4,9 | views>900: user 1(1000),6(1500),10(2000) → distinct: 1,4,6,9,10 = 5
        $this->assertCount(5, $userIds);
    }

    /**
     * increment() with whereIn — binding order: amount then whereBindings.
     */
    public function testIncrementWithWhereIn(): void
    {
        $affected = MockPost::query()
            ->whereIn('id', [1, 3, 6])
            ->increment('views', 100);

        $this->assertEquals(3, $affected);
        $this->assertEquals(1100, (int)MockPost::find(1)->views); // 1000+100
        $this->assertEquals(300,  (int)MockPost::find(3)->views); // Bob 200+100
        $this->assertEquals(1600, (int)MockPost::find(6)->views); // Frank1 1500+100
    }

    /**
     * decrement() with whereBetween — Fix 4 binding integrity.
     */
    public function testDecrementWithWhereBetween(): void
    {
        $affected = MockPost::query()
            ->whereBetween('views', [400, 1000])
            ->decrement('views', 50);

        $this->assertEquals(5, $affected);
        $this->assertEquals(450, (int)MockPost::find(2)->views);  // Alice2: 500–50
        $this->assertEquals(700, (int)MockPost::find(4)->views);  // Carol: 750–50
        $this->assertEquals(850, (int)MockPost::find(10)->views); // James2: 900–50
    }

    /**
     * increment() with deep nested WHERE — Fix 4 + Fix 3 combined.
     */
    public function testIncrementWithNestedWhereCondition(): void
    {
        $affected = MockPost::query()
            ->where(function ($q) {
                $q->where('status', 'published')
                    ->whereIn('user_id', [6, 10]);
            })
            ->increment('views', 500);

        // Frank1(1500),Frank2(100),James1(2000),James2(900) = 4
        $this->assertEquals(4, $affected);
        $this->assertEquals(2000, (int)MockPost::find(6)->views);  // 1500+500
        $this->assertEquals(600,  (int)MockPost::find(7)->views);  // 100+500
        $this->assertEquals(2500, (int)MockPost::find(9)->views);  // 2000+500
        $this->assertEquals(1400, (int)MockPost::find(10)->views); // 900+500
    }

    /**
     * increment() with extra column updates + whereIn.
     */
    public function testIncrementWithExtraColumnsAndWhereIn(): void
    {
        $now = '2025-06-01 00:00:00';

        MockPost::query()
            ->whereIn('id', [1, 2])
            ->increment('views', 10, ['updated_at' => $now]);

        $post1 = MockPost::find(1);
        $post2 = MockPost::find(2);

        $this->assertEquals(1010, (int)$post1->views);
        $this->assertEquals($now, $post1->updated_at);
        $this->assertEquals(510,  (int)$post2->views);
        $this->assertEquals($now, $post2->updated_at);
    }

    /**
     * saveMany() injects created_at and updated_at for all rows.
     */
    public function testSaveManyInjectsTimestampsForAllRows(): void
    {
        MockAnotherUser::saveMany([
            ['name' => 'TS User A', 'email' => 'tsa@test.com', 'age' => 20, 'status' => 'active'],
            ['name' => 'TS User B', 'email' => 'tsb@test.com', 'age' => 21, 'status' => 'active'],
            ['name' => 'TS User C', 'email' => 'tsc@test.com', 'age' => 22, 'status' => 'active'],
        ]);

        foreach (['tsa@test.com', 'tsb@test.com', 'tsc@test.com'] as $email) {
            $user = MockAnotherUser::where('email', $email)->first();
            $this->assertNotNull($user->created_at, "created_at must be set for {$email}");
            $this->assertNotNull($user->updated_at, "updated_at must be set for {$email}");
        }
    }

    /**
     * saveMany() does not overwrite an explicit created_at.
     */
    public function testSaveManyDoesNotOverwriteExistingCreatedAt(): void
    {
        $fixed = '2019-12-31 23:59:59';

        MockAnotherUser::saveMany([
            ['name' => 'Legacy', 'email' => 'legacy@test.com', 'age' => 30, 'status' => 'active', 'created_at' => $fixed],
        ]);

        $user = MockAnotherUser::where('email', 'legacy@test.com')->first();
        $this->assertEquals($fixed, $user->created_at);
    }

    /**
     * saveMany() in chunks still injects timestamps for every row.
     */
    public function testSaveManyInChunksInjectsTimestamps(): void
    {
        $rows = [];
        for ($i = 1; $i <= 6; $i++) {
            $rows[] = ['name' => "Chunk User {$i}", 'email' => "chunk{$i}@test.com", 'age' => 20 + $i, 'status' => 'active'];
        }

        MockAnotherUser::saveMany($rows, 2); // chunk of 2 → 3 batches

        $count = MockAnotherUser::whereNotNull('created_at')
            ->whereLike('email', 'chunk')
            ->count();

        $this->assertEquals(6, $count);
    }

    /**
     * String DB value vs int PHP value — must not be falsely dirty.
     */
    public function testDirtyNotFalselyDirtyOnDbStringVsPhpInt(): void
    {
        $post = MockPost::find(1); // views = 1000 comes back as string from SQLite

        $post->setAttribute('views', 1000); // same value as int

        $this->assertArrayNotHasKey('views', $post->getDirtyAttributes());
    }

    /**
     * Actual value change IS detected as dirty.
     */
    public function testDirtyDetectsRealChange(): void
    {
        $user = MockUser::find(1);
        $user->name = 'Alice Johnson';

        $dirty = $user->getDirtyAttributes();
        $this->assertArrayHasKey('name', $dirty);
        $this->assertEquals('Alice Johnson', $dirty['name']);
    }

    /**
     * '' vs original string value IS dirty.
     */
    public function testDirtyEmptyStringVsOriginalValueIsDirty(): void
    {
        $user = MockUser::find(1); // name = 'Alice Smith'
        $user->setAttribute('name', '');

        $this->assertArrayHasKey('name', $user->getDirtyAttributes());
    }

    /**
     * null to '' IS dirty (null and empty string are distinct).
     */
    public function testDirtyNullToEmptyStringIsDirty(): void
    {
        $user = MockUser::find(4); // Dave has null email
        $user->setAttribute('email', '');

        $this->assertArrayHasKey('email', $user->getDirtyAttributes());
    }

    /**
     * '' to null IS dirty.
     */
    public function testDirtyEmptyStringToNullIsDirty(): void
    {
        $user = MockUser::find(1); // email = 'alice@example.com'
        $user->setAttribute('email', null);

        $this->assertArrayHasKey('email', $user->getDirtyAttributes());
    }

    /**
     * Both null — not dirty.
     */
    public function testDirtyBothNullNotDirty(): void
    {
        $user = MockUser::find(4); // Dave has null email
        $user->setAttribute('email', null); // set null again

        $this->assertArrayNotHasKey('email', $user->getDirtyAttributes());
    }

    /**
     * Only changed fields appear in dirty — untouched fields excluded.
     */
    public function testDirtyOnlyChangedFieldsReturned(): void
    {
        $user = MockUser::find(1);

        $user->name  = 'Alice Johnson'; // changed
        $user->score = 96.0;            // changed
        // email, age, status untouched

        $dirty = $user->getDirtyAttributes();
        $this->assertArrayHasKey('name', $dirty);
        $this->assertArrayHasKey('score', $dirty);
        $this->assertArrayNotHasKey('email', $dirty);
        $this->assertArrayNotHasKey('age', $dirty);
        $this->assertArrayNotHasKey('status', $dirty);
    }

    /**
     * After save(), getDirtyAttributes() is empty — model re-syncs originalAttributes.
     */
    public function testDirtyIsEmptyAfterSave(): void
    {
        $user = MockUser::find(1);
        $user->name = 'Alice Changed';
        $user->save();

        $this->assertEmpty($user->getDirtyAttributes());
    }

    /**
     * Empty string assigned to model attribute is preserved as '', not null.
     */
    public function testSanitizeEmptyStringPreservedOnModel(): void
    {
        $tag = new MockTag();
        $tag->name = '';

        $this->assertSame('', $tag->name);
        $this->assertNotNull($tag->name);
    }

    /**
     * Whitespace-only value trims to '' not null.
     */
    public function testSanitizeWhitespaceTrimmedToEmptyNotNull(): void
    {
        $tag = new MockTag();
        $tag->name = '    ';

        $this->assertSame('', $tag->name);
    }

    /**
     * Leading/trailing whitespace trimmed from normal string.
     */
    public function testSanitizeNormalStringTrimmed(): void
    {
        $tag = new MockTag();
        $tag->name = '  PHP  ';

        $this->assertSame('PHP', $tag->name);
    }

    /**
     * null stays null — not converted to ''.
     */
    public function testSanitizeNullRemainsNull(): void
    {
        $user = MockUser::find(4); // Dave has null email
        $user->setAttribute('email', null);

        $this->assertNull($user->email);
    }

    /**
     * '' persists to DB as '', not NULL.
     */
    public function testSanitizeEmptyStringPersistsAsEmptyNotNull(): void
    {
        $tag = MockTag::create(['name' => 'temp']);
        $tag->name = '';
        $tag->save();

        $reloaded = MockTag::find($tag->id);
        $this->assertSame('', $reloaded->name);
    }

    /**
     * toArray() serializes pivot data under 'pivot' key, not 'pivot_data'.
     */
    public function testToArrayIncludesPivotUnderCorrectKey(): void
    {
        $post  = MockPost::query()->select('id', 'title', 'user_id')->embed('tags')->find(1);
        $array = $post->toArray();

        $this->assertArrayHasKey('tags', $array);
        $this->assertNotEmpty($array['tags']);

        foreach ($array['tags'] as $tag) {
            $this->assertArrayHasKey('pivot', $tag, 'pivot key must exist — not pivot_data');
            $this->assertIsObject($tag['pivot']);
            $this->assertObjectHasProperty('post_id', $tag['pivot']);
        }
    }

    /**
     * toArray() with a null relation explicitly includes key => null.
     */
    public function testToArrayNullRelationExplicitlyNull(): void
    {
        $user = MockUser::find(1);
        $user->setRelation('profile', null);

        $array = $user->toArray();

        $this->assertArrayHasKey('profile', $array);
        $this->assertNull($array['profile']);
    }

    /**
     * toArray() recursively converts nested Model relation.
     */
    public function testToArrayRecursesIntoNestedModel(): void
    {
        $post  = MockPost::embed('user')->find(1);
        $array = $post->toArray();

        $this->assertArrayHasKey('user', $array);
        $this->assertIsArray($array['user']);
        $this->assertEquals('Alice Smith', $array['user']['name']);
        $this->assertArrayHasKey('email', $array['user']);
    }

    /**
     * toArray() on a collection with many-to-many — pivot preserved for all items.
     */
    public function testToArrayOnCollectionWithPivot(): void
    {
        $posts = MockPost::query()->whereIn('id', [1, 6])->embed('tags')->get();
        $array = $posts->toArray();

        $this->assertCount(2, $array);
        foreach ($array as $post) {
            $this->assertArrayHasKey('tags', $post);
            foreach ($post['tags'] as $tag) {
                $this->assertArrayHasKey('pivot', $tag);
            }
        }
    }

    /**
     * __get() returns attribute value correctly.
     */
    public function testMagicGetReturnsAttribute(): void
    {
        $user = MockUser::find(1);

        $this->assertEquals('Alice Smith',        $user->name);
        $this->assertEquals('alice@example.com',  $user->email);
        $this->assertEquals(30, (int)$user->age);
    }

    /**
     * __get() returns null for non-existent property — does not throw.
     */
    public function testMagicGetNonExistentPropertyReturnsNull(): void
    {
        $user = MockUser::find(1);

        $this->assertNull($user->nonExistentProperty);
        $this->assertNull($user->definitelyMissing);
    }

    /**
     * __get() lazy-loads a relation and caches it.
     */
    public function testMagicGetLazyLoadsRelation(): void
    {
        $user  = MockUser::find(1);
        $posts = $user->posts; // Alice has 2 posts

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(2, $posts);

        // Second access hits relation cache
        $this->assertCount(2, $user->posts);
    }

    /**
     * __get() on a relation with no results returns empty Collection, not null.
     */
    public function testMagicGetEmptyRelationReturnsEmptyCollection(): void
    {
        $user  = MockUser::find(5); // Eve has no posts
        $posts = $user->posts;

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(0, $posts);
    }

    /**
     * ORM update() with whereIn — only matching rows updated, others untouched.
     */
    public function testOrmUpdateWithWhereIn(): void
    {
        MockUser::query()
            ->whereIn('id', [1, 2, 3])
            ->update(['status' => 'vip']);

        $this->assertEquals('vip',    MockUser::find(1)->status);
        $this->assertEquals('vip',    MockUser::find(2)->status);
        $this->assertEquals('vip',    MockUser::find(3)->status);
        $this->assertEquals('active', MockUser::find(4)->status); // untouched
    }

    /**
     * ORM delete() with whereBetween — only rows in range deleted.
     */
    public function testOrmDeleteWithWhereBetween(): void
    {
        $before = MockUser::count();

        MockUser::query()->whereBetween('age', [40, 50])->delete();

        $after = MockUser::count();

        // Dave(40),Frank(45),James(50) = 3 deleted
        $this->assertEquals(3, $before - $after);
        $this->assertNull(MockUser::find(4));
        $this->assertNull(MockUser::find(6));
        $this->assertNull(MockUser::find(10));
    }

    /**
     * ORM update() with orWhere
     */
    public function testOrmUpdateWithOrWhereCondition(): void
    {
        MockPost::query()
            ->where('status', 'draft')
            ->orWhere('views', '>', 1500)
            ->update(['content' => 'UPDATED']);

        // drafts: posts 2,5,8 | views>1500: post 9(2000) | total = 4 distinct
        $updatedCount = MockPost::query()->where('content', 'UPDATED')->count();
        $this->assertEquals(4, $updatedCount);

        // Untouched: post 1 (published, 1000 views)
        $this->assertNotEquals('UPDATED', MockPost::find(1)->content);
    }

    /**
     * ORM delete() with whereIn + whereNotNull — Fix 11 regression.
     */
    public function testOrmDeleteWithWhereInAndWhereNotNull(): void
    {
        $before = MockUser::count();

        // Delete users id 1 and 4 who have non-null emails
        // Alice(1) has email → deleted; Dave(4) null email → kept
        MockUser::query()
            ->whereIn('id', [1, 4])
            ->whereNotNull('email')
            ->delete();

        $this->assertEquals($before - 1, MockUser::count());
        $this->assertNull(MockUser::find(1));    // Alice deleted
        $this->assertNotNull(MockUser::find(4)); // Dave kept
    }

    public function testSumWithWhereIn(): void
    {
        // Alice1(1000) + Frank1(1500) + Irene(600) = 3100
        $total = MockPost::query()->whereIn('id', [1, 6, 8])->sum('views');
        $this->assertEquals(3100, (int)$total);
    }

    public function testMaxWithWhereCondition(): void
    {
        // James Post One = 2000
        $max = MockPost::query()->where('status', 'published')->max('views');
        $this->assertEquals(2000.0, $max);
    }

    public function testMinPublishedViews(): void
    {
        // Frank Post Two has 100 views and is published → min = 100
        $min = MockPost::query()->where('status', 'published')->min('views');
        $this->assertEquals(100.0, $min);
    }

    public function testCountWithNestedCondition(): void
    {
        // active users with (age < 30 OR score > 90)
        $count = MockUser::where('status', 'active')
            ->where(function ($q) {
                $q->where('age', '<', 30)
                    ->orWhere('score', '>', 90);
            })
            ->count();

        // age<30 active: Bob(25),Grace(28),Irene(29) = 3
        // score>90 active: Alice(95.5),Frank(91),James(99) = 3
        // union: 6
        $this->assertEquals(6, $count);
    }

    public function testDistinctUserIdsFromPublishedPosts(): void
    {
        // Users who have at least one published post
        $userIds = MockPost::query()
            ->where('status', 'published')
            ->distinct('user_id');

        // user_ids 1,2,3,6,10 have published posts = 5 distinct
        $this->assertCount(5, $userIds);
    }

    /**
     * Eager load with constraint callback — callback runs exactly ONCE (Fix 16).
     */
    public function testEagerLoadConstraintRunsOnce(): void
    {
        $callCount = 0;

        $post = MockPost::query()
            ->embed([
                'comments' => function ($q) use (&$callCount) {
                    $callCount++;
                    $q->where('approved', 1);
                },
            ])
            ->find(1);

        $this->assertEquals(1, $callCount, 'Constraint callback must run exactly once');
        // Post 1 has 3 comments, 2 are approved
        $this->assertCount(2, $post->comments);
    }

    /**
     * present() with nested condition through ORM.
     */
    public function testPresentWithNestedCondition(): void
    {
        // Users who have published posts with views > 500
        $users = MockUser::query()
            ->present('posts', function ($q) {
                $q->where('status', 'published')
                    ->where('views', '>', 500);
            })
            ->orderBy('id')
            ->get();

        $names = $users->map->name->toArray();
        $this->assertContains('Alice Smith', $names);   // post 1: 1000
        $this->assertContains('Carol White', $names);   // post 4: 750
        $this->assertContains('Frank Miller', $names);  // post 6: 1500
        $this->assertContains('James Scott', $names);   // post 9: 2000
        $this->assertNotContains('Bob Jones', $names);  // Bob's post: 200 only
    }

    /**
     * embedCount() with whereIn — only selected posts counted.
     */
    public function testEmbedCountWithWhereIn(): void
    {
        $posts = MockPost::query()
            ->whereIn('id', [1, 6, 8])
            ->embedCount('comments')
            ->get();

        $this->assertCount(3, $posts);

        foreach ($posts->toArray() as $post) {
            $this->assertArrayHasKey('comments_count', $post);
        }

        $post1 = $posts->filter(fn($p) => (int)$p->id === 1)->first();
        $this->assertEquals(3, (int)$post1->comments_count);
    }

    /**
     * Many-to-many: all pivot data serialized correctly through toArray()
     */
    public function testManyToManyPivotDataSerialization(): void
    {
        $post  = MockPost::embed('tags')->find(1);
        $array = $post->toArray();

        // Post 1 has 3 tags
        $this->assertCount(3, $array['tags']);
        foreach ($array['tags'] as $tag) {
            $this->assertArrayHasKey('pivot', $tag);
            $this->assertIsObject($tag['pivot']);
            $this->assertObjectHasProperty('post_id', $tag['pivot']);
            $this->assertEquals(1, $tag['pivot']->post_id);
        }
    }

    /**
     * whereIn with a single element — must not generate syntax error.
     */
    public function testWhereInWithSingleElement(): void
    {
        $users = MockUser::whereIn('id', [1])->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Alice Smith', $users->first()->name);
    }

    /**
     * Chained: where + whereNotNull + orderBy + limit — all clauses intact.
     */
    public function testChainedConditionsWithOrderingAndLimit(): void
    {
        $users = MockUser::where('status', 'active')
            ->whereNotNull('email')
            ->orderBy('age', 'desc')
            ->limit(3)
            ->get();

        $this->assertCount(3, $users);
        foreach ($users as $user) {
            $this->assertEquals('active', $user->status);
            $this->assertNotNull($user->email);
        }

        // Must be descending age
        $ages = $users->map(fn($u) => (int)$u->age)->toArray();
        $this->assertGreaterThanOrEqual($ages[1], $ages[0]);
        $this->assertGreaterThanOrEqual($ages[2], $ages[1]);
    }

    /**
     * select() + whereIn — selected columns only, no bleed into where clause.
     */
    public function testSelectWithWhereInNoColumnBleed(): void
    {
        $users = MockUser::select('name', 'status', 'age')
            ->whereIn('status', ['active'])
            ->orderBy('age', 'asc')
            ->get();

        $this->assertCount(7, $users);
        foreach ($users->toArray() as $user) {
            $this->assertArrayHasKey('name', $user);
            $this->assertArrayNotHasKey('email', $user);
            $this->assertArrayNotHasKey('score', $user);
        }
    }

    /**
     * find([]) with multiple IDs returns correct Collection.
     */
    public function testFindWithMultipleIds(): void
    {
        $users = MockUser::find([1, 3, 5, 7, 9]);

        $this->assertInstanceOf(Collection::class, $users);
        $this->assertCount(5, $users);
    }

    /**
     * count() with nested OR inside AND — complex condition count.
     */
    public function testCountWithNestedOrInsideAnd(): void
    {
        $count = MockPost::where('status', 'published')
            ->where(function ($q) {
                $q->where('views', '>', 1000)
                    ->orWhereIn('user_id', [2, 3]);
            })
            ->count();

        // published AND (views>1000 OR user_id in [2,3])
        // views>1000 published: Frank1(1500),James1(2000) = 2
        // user_id 2,3 published: Bob(id=3),Carol(id=4) = 2
        // total: 4
        $this->assertEquals(4, $count);
    }

    /**
     * toSql() with complex conditions produces well-formed SQL string.
     */
    public function testToSqlWithComplexConditions(): void
    {
        $sql = MockPost::where('status', 'published')
            ->whereIn('user_id', [1, 6, 10])
            ->whereBetween('views', [100, 2000])
            ->toSql();

        $this->assertIsString($sql);
        $upperSql = strtoupper($sql);
        $this->assertStringContainsString('WHERE',   $upperSql);
        $this->assertStringContainsString('IN',      $upperSql);
        $this->assertStringContainsString('BETWEEN', $upperSql);
    }
}
