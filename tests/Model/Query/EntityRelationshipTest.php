<?php

namespace Tests\Unit\Model\Query;

use PDO;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Phaseolies\Http\Request;
use Phaseolies\Support\Collection;
use Phaseolies\Support\Facades\DB;
use Phaseolies\Support\UrlGenerator;
use PHPUnit\Framework\TestCase;
use Tests\Support\MockContainer;
use Tests\Support\Model\MockComment;
use Tests\Support\Model\MockPost;
use Tests\Support\Model\MockTag;
use Tests\Support\Model\MockUser;

/**
 * Comprehensive Relationship Tests for Entity ORM
 *
 * Covers:
 *  - linkMany (one-to-many)
 *  - bindTo (inverse one-to-many)
 *  - bindToMany (many-to-many with pivot)
 *  - linkMany through nested dot notation
 *  - Eager loading (embed)
 *  - Lazy loading (__get magic)
 *  - Constrained eager loading (embed with closure)
 *  - Column-scoped eager loading (embed with :column syntax)
 *  - embedCount (aggregate count on relation)
 *  - embedCount with closure constraint
 *  - embedCount on nested relation (dot notation)
 *  - present / absent (whereHas / whereDoesntHave equivalent)
 *  - ifExists / ifNotExists
 *  - Nested ifExists (dot notation)
 *  - whereLinked
 *  - link / unlink / relate (many-to-many sync helpers)
 *  - Nested eager loading (multi-level)
 *  - Mixed omit + embed
 *  - toArray pivot serialisation
 *  - Relation on collection (get() + embed)
 *  - Chained builder conditions on lazy-loaded relation
 */
class EntityRelationshipTest extends TestCase
{
    private $pdo;

    // =========================================================================
    // SETUP / TEARDOWN
    // =========================================================================

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());
        $container = new Container();
        $container->bind('request', fn() => new Request());
        $container->bind('url',     fn() => UrlGenerator::class);
        $container->bind('db',      fn() => new Database('default'));

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createSchema();
        $this->seedData();
        $this->setupDatabaseConnections();
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->tearDownDatabaseConnections();
    }

    // =========================================================================
    // SCHEMA & SEED
    // =========================================================================

    private function createSchema(): void
    {
        // users
        $this->pdo->exec("
            CREATE TABLE users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT    NOT NULL,
                email      TEXT    UNIQUE,
                age        INTEGER,
                status     TEXT    DEFAULT 'active',
                created_at TEXT
            )
        ");

        // posts
        $this->pdo->exec("
            CREATE TABLE posts (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER,
                title      TEXT    NOT NULL,
                content    TEXT,
                status     BOOLEAN DEFAULT 1,
                views      INTEGER DEFAULT 0,
                created_at TEXT
            )
        ");

        // comments
        $this->pdo->exec("
            CREATE TABLE comments (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id    INTEGER,
                user_id    INTEGER,
                body       TEXT    NOT NULL,
                approved   BOOLEAN DEFAULT 0,
                created_at TEXT
            )
        ");

        // tags
        $this->pdo->exec("
            CREATE TABLE tags (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )
        ");

        // post_tag pivot
        $this->pdo->exec("
            CREATE TABLE post_tag (
                post_id    INTEGER,
                tag_id     INTEGER,
                created_at TEXT
            )
        ");
    }

    private function seedData(): void
    {
        // Users: 3 rows
        //   id=1  John Doe    active   age=30
        //   id=2  Jane Smith  active   age=25
        //   id=3  Bob Wilson  inactive age=35
        $this->pdo->exec("
            INSERT INTO users (name, email, age, status, created_at) VALUES
            ('John Doe',   'john@example.com', 30, 'active',   '2024-01-01 10:00:00'),
            ('Jane Smith', 'jane@example.com', 25, 'active',   '2024-01-02 10:00:00'),
            ('Bob Wilson', 'bob@example.com',  35, 'inactive', '2024-01-03 10:00:00')
        ");

        // Posts: 4 rows
        //   id=1  user_id=1  First Post   status=1  views=100
        //   id=2  user_id=1  Second Post  status=0  views=50
        //   id=3  user_id=2  Jane Post    status=1  views=200
        //   id=4  user_id=1  Third Post   status=1  views=150
        $this->pdo->exec("
            INSERT INTO posts (user_id, title, content, status, views, created_at) VALUES
            (1, 'First Post',  'Content 1', 1, 100, '2024-01-01 11:00:00'),
            (1, 'Second Post', 'Content 2', 0, 50,  '2024-01-02 11:00:00'),
            (2, 'Jane Post',   'Content 3', 1, 200, '2024-01-03 11:00:00'),
            (1, 'Third Post',  'Content 4', 1, 150, '2024-01-04 11:00:00')
        ");

        // Comments: 5 rows
        //   id=1  post_id=1  user_id=1  Great post!  approved=1
        //   id=2  post_id=1  user_id=2  Nice work    approved=0
        //   id=3  post_id=2  user_id=1  Interesting  approved=1
        //   id=4  post_id=3  user_id=2  Amazing      approved=1
        //   id=5  post_id=1  user_id=3  Awesome      approved=1
        $this->pdo->exec("
            INSERT INTO comments (post_id, user_id, body, approved, created_at) VALUES
            (1, 1, 'Great post!', 1, '2024-01-01 12:00:00'),
            (1, 2, 'Nice work',   0, '2024-01-01 13:00:00'),
            (2, 1, 'Interesting', 1, '2024-01-02 12:00:00'),
            (3, 2, 'Amazing',     1, '2024-01-03 12:00:00'),
            (1, 3, 'Awesome',     1, '2024-01-01 14:00:00')
        ");

        // Tags: 4 rows
        $this->pdo->exec("
            INSERT INTO tags (name) VALUES ('PHP'), ('Doppar'), ('Testing'), ('Database')
        ");

        // post_tag pivot
        //   post 1 → tags 1,2
        //   post 2 → tag  1
        //   post 3 → tag  3
        //   post 4 → tag  4
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
        $this->setStaticProperty(Database::class, 'connections',  []);
        $this->setStaticProperty(Database::class, 'transactions', []);
        $this->setStaticProperty(Database::class, 'connections', [
            'default' => $this->pdo,
            'sqlite'  => $this->pdo,
        ]);
    }

    private function tearDownDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections',  []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function setStaticProperty(string $class, string $prop, $value): void
    {
        $r = new \ReflectionClass($class);
        $p = $r->getProperty($prop);
        $p->setValue(null, $value);
    }

    // =========================================================================
    // 1. LAZY LOADING — linkMany
    // =========================================================================

    /**
     * A User has many Posts; accessing $user->posts triggers a lazy load
     * and returns a Collection of all matching posts.
     */
    public function testLinkManyLazyLoad(): void
    {
        $user  = MockUser::find(1);
        $posts = $user->posts;

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(3, $posts);

        // Verify IDs belong to user 1
        foreach ($posts as $post) {
            $this->assertEquals(1, $post->user_id);
        }
    }

    /**
     * A User with no posts (Bob Wilson, id=3) returns an empty Collection,
     * not null.
     */
    public function testLinkManyLazyLoadReturnsEmptyCollectionWhenNoChildren(): void
    {
        $user  = MockUser::find(3); // Bob Wilson – no posts
        $posts = $user->posts;

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(0, $posts);
    }

    /**
     * Lazy-loaded relation can be further constrained via the method call syntax
     * $user->posts()->where(...)->get().
     */
    public function testLinkManyLazyLoadWithConstraint(): void
    {
        $user = MockUser::find(1);

        $active   = $user->posts()->where('status', true)->get();
        $inactive = $user->posts()->where('status', false)->get();

        $this->assertCount(2, $active);
        $this->assertCount(1, $inactive);
    }

    // =========================================================================
    // 2. LAZY LOADING — bindTo
    // =========================================================================

    /**
     * A Post belongs to a User; accessing $post->user triggers a lazy load
     * and returns the parent model.
     */
    public function testBindToLazyLoad(): void
    {
        $post = MockPost::find(1);
        $user = $post->user;

        $this->assertNotNull($user);
        $this->assertEquals(1, $user->id);
        $this->assertEquals('John Doe', $user->name);
    }

    /**
     * bindTo for a different post owner (Jane Smith, user_id=2).
     */
    public function testBindToLazyLoadDifferentOwner(): void
    {
        $post = MockPost::find(3); // Jane Post
        $user = $post->user;

        $this->assertEquals(2, $user->id);
        $this->assertEquals('Jane Smith', $user->name);
    }

    // =========================================================================
    // 3. LAZY LOADING — bindToMany (many-to-many)
    // =========================================================================

    /**
     * Post 1 has 2 tags via post_tag pivot.
     * Each tag entity must include a `pivot` object.
     */
    public function testBindToManyLazyLoad(): void
    {
        $post = MockPost::find(1);
        $tags = $post->tags;

        $this->assertInstanceOf(Collection::class, $tags);
        $this->assertCount(2, $tags);

        foreach ($tags as $tag) {
            $this->assertEquals(1, $tag->pivot->post_id);
        }
    }

    /**
     * Post 4 has exactly 1 tag (Database).
     */
    public function testBindToManyLazyLoadSingleTag(): void
    {
        $post = MockPost::find(4);
        $tags = $post->tags;

        $this->assertCount(1, $tags);
        $this->assertEquals('Database', $tags[0]->name);
    }

    /**
     * Inverse: Tag 1 (PHP) belongs to many Posts.
     */
    public function testBindToManyInverseLazyLoad(): void
    {
        $tag   = MockTag::find(1);
        $posts = $tag->posts;

        $this->assertCount(2, $posts);

        $postIds = array_map(fn($p) => $p['id'], $posts->toArray());

        sort($postIds);

        $this->assertEquals([1, 2], $postIds);
    }

    /**
     * Pivot data must carry all pivot-table columns, including created_at.
     */
    public function testBindToManyPivotContainsTimestamp(): void
    {
        $post = MockPost::find(1);
        $tag  = $post->tags->first();

        $this->assertEquals('2024-01-01 11:00:00', $tag->pivot->created_at);
    }

    // =========================================================================
    // 4. EAGER LOADING (embed) — basic
    // =========================================================================

    /**
     * embed('posts') on a single find returns user with nested posts array.
     */
    public function testEagerLoadLinkManyOnFind(): void
    {
        $user = MockUser::embed('posts')->find(1);

        $array = $user->toArray();
        $this->assertArrayHasKey('posts', $array);
        $this->assertCount(3, $array['posts']);
        $this->assertEquals('First Post', $array['posts'][0]['title']);
    }

    /**
     * embed('posts') on get() returns every user with their posts nested.
     */
    public function testEagerLoadLinkManyOnCollection(): void
    {
        $users = MockUser::embed('posts')->get();

        $this->assertCount(3, $users);

        // John (id=1) has 3 posts
        $this->assertCount(3, $users[0]->posts);
        // Jane (id=2) has 1 post
        $this->assertCount(1, $users[1]->posts);
        // Bob (id=3) has 0 posts
        $this->assertCount(0, $users[2]->posts);
    }

    /**
     * embed('user') on posts loads the bindTo parent.
     */
    public function testEagerLoadBindTo(): void
    {
        $post = MockPost::embed('user')->find(1);

        $array = $post->toArray();
        $this->assertArrayHasKey('user', $array);
        $this->assertEquals('John Doe', $array['user']['name']);
    }

    /**
     * embed('tags') loads the many-to-many relation with pivot data.
     */
    public function testEagerLoadBindToMany(): void
    {
        $post = MockPost::embed('tags')->find(1);

        $array = $post->toArray();
        $this->assertArrayHasKey('tags', $array);
        $this->assertCount(2, $array['tags']);

        // Each tag must have a pivot key that is an object
        foreach ($array['tags'] as $tag) {
            $this->assertArrayHasKey('pivot', $tag);
            $this->assertIsObject($tag['pivot']);
        }
    }

    // =========================================================================
    // 5. COLUMN-SCOPED EAGER LOADING (embed with :col syntax)
    // =========================================================================

    /**
     * embed('posts:title') should auto-include id and foreign key alongside
     * the requested column.
     */
    public function testEagerLoadColumnScoped(): void
    {
        $user  = MockUser::embed('posts:title')->find(1);
        $posts = $user->toArray()['posts'];

        foreach ($posts as $post) {
            $this->assertArrayHasKey('id',      $post);
            $this->assertArrayHasKey('user_id', $post);
            $this->assertArrayHasKey('title',   $post);
            // Non-selected columns must be absent
            $this->assertArrayNotHasKey('content',    $post);
            $this->assertArrayNotHasKey('views',      $post);
            $this->assertArrayNotHasKey('created_at', $post);
        }
    }

    /**
     * embed('comments:body') inside a nested relation also respects column scope.
     */
    public function testEagerLoadNestedColumnScoped(): void
    {
        $post     = MockPost::embed('user.comments:body')->find(1);
        $comments = $post->toArray()['user']['comments'];

        foreach ($comments as $comment) {
            $this->assertArrayHasKey('id',      $comment);
            $this->assertArrayHasKey('user_id', $comment);
            $this->assertArrayHasKey('body',    $comment);
            $this->assertArrayNotHasKey('approved',   $comment);
            $this->assertArrayNotHasKey('created_at', $comment);
        }
    }

    // =========================================================================
    // 6. CONSTRAINED EAGER LOADING (embed with closure)
    // =========================================================================

    /**
     * A closure passed to embed() filters the related records.
     */
    public function testConstrainedEagerLoad(): void
    {
        $post = MockPost::query()
            ->where('id', 1)
            ->embed([
                'comments' => fn($q) => $q->where('approved', true),
            ])
            ->first();

        // Post 1 has 3 comments total but 2 are approved (id=1 and id=5), id=2 is not
        $comments = $post->toArray()['comments'];
        foreach ($comments as $c) {
            $this->assertEquals(1, $c['approved']);
        }
    }

    /**
     * Closure can also limit the number of related records loaded.
     */
    public function testConstrainedEagerLoadWithLimit(): void
    {
        $post = MockPost::query()
            ->where('id', 1)
            ->embed([
                'comments:id,body,created_at' => fn($q) => $q
                    ->where('approved', true)
                    ->limit(1)
                    ->oldest('created_at'),
            ])
            ->first();

        $comments = $post->toArray()['comments'];
        $this->assertCount(1, $comments);
        $this->assertEquals('Great post!', $comments[0]['body']);
    }

    /**
     * Closure on many-to-many embed (tags) applies WHERE to the tag query.
     */
    public function testConstrainedEagerLoadBindToMany(): void
    {
        $post = MockPost::query()
            ->where('id', 1)
            ->embed([
                'tags' => fn($q) => $q->where('tags.id', 1),
            ])
            ->first();

        $tags = $post->toArray()['tags'];
        $this->assertCount(1, $tags);
        $this->assertEquals('PHP', $tags[0]['name']);
    }

    // =========================================================================
    // 7. NESTED EAGER LOADING (dot notation)
    // =========================================================================

    /**
     * embed('user.comments') loads post → user → comments (two levels deep).
     */
    public function testTwoLevelNestedEagerLoad(): void
    {
        $post  = MockPost::embed('user.comments')->find(1);
        $array = $post->toArray();

        $this->assertArrayHasKey('user', $array);
        $this->assertArrayHasKey('comments', $array['user']);
        $this->assertCount(2, $array['user']['comments']); // John's comments
    }

    /**
     * embed(['posts.comments']) on a User loads posts each with their comments.
     */
    public function testTwoLevelNestedEagerLoadFromUser(): void
    {
        $user  = MockUser::embed('posts.comments')->find(1);
        $array = $user->toArray();

        $this->assertArrayHasKey('posts', $array);

        // Post 1 has 3 comments
        $post1Comments = collect($array['posts'])->first(fn($p) => $p['id'] === 1)['comments'];
        $this->assertCount(3, $post1Comments);
    }

    /**
     * Multiple simultaneous relations and nested relations via array syntax.
     */
    public function testMultipleEmbedRelationsAtOnce(): void
    {
        $user  = MockUser::omit('created_at')
            ->embed(['comments:body', 'posts.comments:body'])
            ->find(1);
        $array = $user->toArray();

        // Top-level comments (user_id=1): ids 1 and 3
        $this->assertCount(2, $array['comments']);
        $this->assertArrayNotHasKey('created_at', $array);

        // Nested posts → comments
        $this->assertArrayHasKey('posts', $array);
        foreach ($array['posts'] as $post) {
            $this->assertArrayHasKey('comments', $post);
        }
    }

    /**
     * Three-level: Tag → posts → comments (embedCount on the deepest level).
     */
    public function testThreeLevelNestedViaEmbedCount(): void
    {
        $tag  = MockTag::embedCount('posts.comments')->find(1);
        $array = $tag->toArray();

        $this->assertArrayHasKey('posts', $array);

        foreach ($array['posts'] as $post) {
            $this->assertArrayHasKey('comments_count', $post);
        }

        // Post 1 (First Post) has 3 comments
        $post1 = collect($array['posts'])->first(fn($p) => $p['id'] === 1);
        $this->assertEquals(3, $post1['comments_count']);
    }

    // =========================================================================
    // 8. embedCount
    // =========================================================================

    /**
     * embedCount('comments') appends comments_count without loading records.
     */
    public function testEmbedCountBasic(): void
    {
        $posts = MockPost::omit('created_at')->embedCount('comments')->get();

        $counts = collect($posts->toArray())->mapWithKeys(fn($p) => [$p['id'] => $p['comments_count']]);

        $this->assertEquals(3, $counts[1]); // post 1
        $this->assertEquals(1, $counts[2]); // post 2
        $this->assertEquals(1, $counts[3]); // post 3
        $this->assertEquals(0, $counts[4]); // post 4
    }

    /**
     * embedCount with a closure constrains what is counted.
     */
    public function testEmbedCountWithConstraint(): void
    {
        $posts = MockPost::embedCount([
            'comments' => fn($q) => $q->where('approved', true),
        ])->get();

        // Post 1: comments 1,5 are approved (id=2 is NOT approved) → 2
        $post1 = collect($posts->toArray())->first(fn($p) => $p['id'] === 1);
        $this->assertEquals(2, $post1['comments_count']);
    }

    /**
     * embedCount('tags') on posts counts many-to-many related records.
     */
    public function testEmbedCountBindToMany(): void
    {
        $posts = MockPost::embedCount('tags')->get();

        $counts = collect($posts->toArray())->mapWithKeys(fn($p) => [$p['id'] => $p['tags_count']]);

        $this->assertEquals(2, $counts[1]); // post 1 has 2 tags
        $this->assertEquals(1, $counts[2]);
        $this->assertEquals(1, $counts[3]);
        $this->assertEquals(1, $counts[4]);
    }

    /**
     * embedCount mixed: count comments (filtered) AND tags simultaneously.
     */
    public function testEmbedCountMultipleRelationsAtOnce(): void
    {
        $posts = MockPost::omit('views', 'created_at', 'status')
            ->embedCount([
                'comments' => fn($q) => $q->where('approved', true),
                'tags',
            ])->get();

        $p1 = collect($posts->toArray())->first(fn($p) => $p['id'] === 1);

        $this->assertEquals(2, $p1['comments_count']); // 2 approved
        $this->assertEquals(2, $p1['tags_count']);      // PHP + Doppar
    }

    /**
     * embedCount on user counts posts_count (linkMany).
     */
    public function testEmbedCountLinkManyOnUser(): void
    {
        $user = MockUser::embedCount('posts')->find(1);

        $this->assertEquals(3, $user->toArray()['posts_count']);
    }

    /**
     * embedCount combined with embed on same request (count + data together).
     */
    public function testEmbedCountCombinedWithEmbed(): void
    {
        $user = MockUser::omit('created_at')
            ->embedCount('comments')
            ->embed(['comments:body', 'posts.comments:body'])
            ->find(1)
            ->toArray();

        $this->assertEquals(2, $user['comments_count']);
        $this->assertCount(2, $user['comments']);
        $this->assertArrayHasKey('posts', $user);
    }

    // =========================================================================
    // 9. present / absent
    // =========================================================================

    /**
     * present('comments') returns only posts that have at least one comment.
     */
    public function testPresentFiltersPostsWithComments(): void
    {
        $posts = MockPost::query()->present('comments')->get();

        // Post 4 has no comments, so only 3 posts
        $this->assertCount(3, $posts);
        $ids = $posts->map->id->toArray();
        $this->assertNotContains(4, $ids);
    }

    /**
     * absent('comments') returns only posts without any comments.
     */
    public function testAbsentFiltersPostsWithoutComments(): void
    {
        $posts = MockPost::query()->absent('comments')->get();

        $this->assertCount(1, $posts);
        $this->assertEquals(4, $posts->first()->id);
    }

    /**
     * present() with a closure further filters the related model.
     */
    public function testPresentWithConstraintClosure(): void
    {
        $posts = MockPost::query()
            ->present('comments', fn($q) => $q->where('body', 'Great post!'))
            ->get();

        // Only post 1 has a comment with that exact body
        $this->assertCount(1, $posts);
        $this->assertEquals(1, $posts->first()->id);
    }

    /**
     * present() on a many-to-many relation (tags).
     */
    public function testPresentOnBindToMany(): void
    {
        // All 4 posts have at least 1 tag
        $posts = MockPost::query()->present('tags')->get();
        $this->assertCount(4, $posts);
    }

    // =========================================================================
    // 10. ifExists / ifNotExists
    // =========================================================================

    /**
     * ifExists('comments') is equivalent to present() — posts with ≥1 comment.
     */
    public function testIfExistsEquivalentToPresent(): void
    {
        $postsPresent  = MockPost::query()->present('comments')->get();
        $postsIfExists = MockPost::query()->ifExists('comments')->get();

        $this->assertEquals(
            $postsPresent->map->id->toArray(),
            $postsIfExists->map->id->toArray()
        );
    }

    /**
     * ifNotExists('comments') is equivalent to absent().
     */
    public function testIfNotExistsEquivalentToAbsent(): void
    {
        $postsAbsent      = MockPost::query()->absent('comments')->get();
        $postsIfNotExists = MockPost::query()->ifNotExists('comments')->get();

        $this->assertEquals(
            $postsAbsent->map->id->toArray(),
            $postsIfNotExists->map->id->toArray()
        );
    }

    /**
     * ifExists() with a closure constraint.
     */
    public function testIfExistsWithConstraint(): void
    {
        $posts = MockPost::query()
            ->ifExists('comments', fn($q) => $q->where('body', 'Great post!'))
            ->get();

        $this->assertCount(1, $posts);
        $this->assertEquals(1, $posts->first()->id);
    }

    // =========================================================================
    // 11. NESTED ifExists (dot notation)
    // =========================================================================

    /**
     * ifExists('posts.comments') on Users returns users whose posts have
     * at least one comment matching the closure.
     */
    public function testNestedIfExistsWithDotNotation(): void
    {
        $users = MockUser::query()
            ->ifExists('posts.comments', fn($q) => $q->where('body', 'Great post!'))
            ->get();

        // Only John Doe (user_id=1) authored the post that has that comment
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users->first()->name);
    }

    /**
     * ifExists('posts.comments') without a closure returns all users who
     * have at least one post with at least one comment.
     */
    public function testNestedIfExistsNoConstraint(): void
    {
        $users = MockUser::query()
            ->ifExists('posts.comments')
            ->get();

        // John (posts 1,2,4 – posts 1 & 2 have comments) ✓
        // Jane  (post 3 has comment) ✓
        // Bob   (no posts) ✗
        $this->assertCount(2, $users);
    }

    // =========================================================================
    // 12. whereLinked
    // =========================================================================

    /**
     * whereLinked('posts', 'status', true) returns users who have at least
     * one active post.
     */
    public function testWhereLinkedActivePost(): void
    {
        $users = MockUser::query()
            ->whereLinked('posts', 'status', true)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(2, $users);
        $ids = $users->map->id->toArray();
        $this->assertEquals([1, 2], $ids);
    }

    /**
     * whereLinked('posts', 'status', false) returns users who have at least
     * one inactive post.
     */
    public function testWhereLinkedInactivePost(): void
    {
        $users = MockUser::query()
            ->whereLinked('posts', 'status', false)
            ->get();

        // Only John Doe has an inactive post (id=2)
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users->first()->name);
    }

    // =========================================================================
    // 13. link (attach) — many-to-many
    // =========================================================================

    /**
     * link() attaches new pivot rows without removing existing ones.
     */
    public function testLinkAttachesNewPivotRows(): void
    {
        $post = MockPost::find(1); // already has tags 1,2
        $post->tags()->link([3]);  // attach tag 3

        $tagIds = MockPost::find(1)->tags->pluck('id')->sort()->values()->toArray();
        $this->assertContains(3, $tagIds);
        $this->assertCount(3, $tagIds); // 1,2,3
    }

    /**
     * link() is additive — calling it twice does NOT replace previous links.
     */
    public function testLinkIsAdditive(): void
    {
        $post = MockPost::find(2); // has tag 1
        $post->tags()->link([3]);
        $post->tags()->link([4]);

        $tagIds = MockPost::find(2)->tags->pluck('id')->sort()->values()->toArray();
        // Should have 1, 3, 4 (tag 1 was already there, 3 and 4 added)
        $this->assertContains(1, $tagIds);
        $this->assertContains(3, $tagIds);
        $this->assertContains(4, $tagIds);
    }

    // =========================================================================
    // 14. unlink (detach) — many-to-many
    // =========================================================================

    /**
     * unlink() removes specific pivot rows.
     */
    public function testUnlinkRemovesPivotRows(): void
    {
        $post = MockPost::find(1); // tags: 1, 2
        $post->tags()->unlink([1]);

        $tagIds = MockPost::find(1)->tags->pluck('id')->toArray();
        $this->assertNotContains(1, $tagIds);
        $this->assertContains(2, $tagIds);
    }

    /**
     * unlink() all tags leaves an empty collection.
     */
    public function testUnlinkAllLeavesEmpty(): void
    {
        $post = MockPost::find(1);
        $post->tags()->unlink([1, 2]);

        $tags = MockPost::find(1)->tags;
        $this->assertCount(0, $tags);
    }

    /**
     * After unlink, we can link again cleanly.
     */
    public function testUnlinkThenRelink(): void
    {
        $post = MockPost::find(1);
        $post->tags()->unlink([1, 2]);
        $post->tags()->link([3, 4]);

        $tagIds = MockPost::find(1)->tags->pluck('id')->sort()->values()->toArray();
        $this->assertEquals([3, 4], $tagIds);
    }

    // =========================================================================
    // 15. relate (sync) — many-to-many
    // =========================================================================

    /**
     * relate() replaces all pivot rows to exactly match the given IDs.
     */
    public function testRelateSyncsToExactSet(): void
    {
        $post = MockPost::find(1); // currently tags: 1, 2
        $post->tags()->relate([1, 3]);

        $tagIds = MockPost::find(1)->tags->pluck('id')->sort()->values()->toArray();
        $this->assertEquals([1, 3], $tagIds);
    }

    /**
     * relate() returns a diff array with attached / detached / updated keys.
     */
    public function testRelateDiffReport(): void
    {
        $post = MockPost::find(1);

        // Sync to [1, 3, 4] from [1, 2]
        $changes = $post->tags()->relate([1, 3, 4]);

        $this->assertArrayHasKey('attached', $changes);
        $this->assertArrayHasKey('detached', $changes);
        $this->assertArrayHasKey('updated',  $changes);

        // Tag 3 and 4 should be attached; tag 2 should be detached
        $this->assertContains(3, array_keys($changes['attached']));
        $this->assertContains(4, array_keys($changes['attached']));

        // detached stores IDs as values (not keys)
        $this->assertContains(2, array_values($changes['detached']));
    }

    /**
     * Calling relate() twice is idempotent for the same set.
     */
    public function testRelateIsIdempotentForSameSet(): void
    {
        $post = MockPost::find(1);
        $post->tags()->relate([1, 2]);
        $changes = $post->tags()->relate([1, 2]);

        $this->assertEquals([], $changes['attached']);
        $this->assertEquals([], $changes['detached']);
    }

    // =========================================================================
    // 16. MIXED omit + embed
    // =========================================================================

    /**
     * omit() on the parent model does not strip columns from eager-loaded children.
     */
    public function testOmitDoesNotAffectEagerLoadedChildren(): void
    {
        $user  = MockUser::omit('created_at')->embed('posts')->find(1);
        $array = $user->toArray();

        // Parent must NOT have created_at
        $this->assertArrayNotHasKey('created_at', $array);

        // Children (posts) SHOULD still have created_at
        $this->assertArrayHasKey('created_at', $array['posts'][0]);
    }

    /**
     * omit() + embed + embedCount all cooperate on the same query.
     */
    public function testOmitPlusEmbedPlusEmbedCount(): void
    {
        $user  = MockUser::omit('created_at')
            ->embedCount('comments')
            ->embed('posts')
            ->find(1)
            ->toArray();

        $this->assertArrayNotHasKey('created_at', $user);
        $this->assertArrayHasKey('comments_count', $user);
        $this->assertArrayHasKey('posts', $user);
        $this->assertEquals(2, $user['comments_count']);
    }

    // =========================================================================
    // 17. toArray serialisation — pivot, null relation, nested recursion
    // =========================================================================

    /**
     * toArray() must include pivot as a plain object (not array) for pivot data.
     */
    public function testToArrayIncludesPivotAsObject(): void
    {
        $post  = MockPost::embed('tags')->find(1);
        $array = $post->toArray();

        $firstTag = $array['tags'][0];
        $this->assertArrayHasKey('pivot', $firstTag);
        $this->assertIsObject($firstTag['pivot']);
        $this->assertEquals(1, $firstTag['pivot']->post_id);
        $this->assertEquals(1, $firstTag['pivot']->tag_id);
    }

    /**
     * toArray() includes null relations as null (not missing).
     */
    public function testToArrayNullRelationIsPreserved(): void
    {
        $user = MockUser::find(1);
        $user->setRelation('profile', null);

        $array = $user->toArray();
        $this->assertArrayHasKey('profile', $array);
        $this->assertNull($array['profile']);
    }

    /**
     * toArray() recursively converts nested relations.
     */
    public function testToArrayRecursesIntoNestedRelation(): void
    {
        $post  = MockPost::embed('user')->find(1);
        $array = $post->toArray();

        $this->assertIsArray($array['user']);
        $this->assertEquals('John Doe', $array['user']['name']);
    }

    /**
     * toArray() on a collection correctly serialises many-to-many with pivot.
     */
    public function testToArrayOnCollectionWithPivot(): void
    {
        $posts = MockPost::query()
            ->select('id', 'title', 'user_id')
            ->embed('tags')
            ->get();

        $array = $posts->toArray();

        foreach ($array as $post) {
            foreach ($post['tags'] as $tag) {
                $this->assertArrayHasKey('pivot', $tag);
                $this->assertIsObject($tag['pivot']);
            }
        }
    }

    // =========================================================================
    // 18. COMPLEX COMBINED QUERIES
    // =========================================================================

    /**
     * Full complex query: select, embed (with column scope + closure + m2m),
     * embedCount, where, first.
     */
    public function testFullComplexEagerLoad(): void
    {
        $post = MockPost::query()
            ->where('id', 1)
            ->select('id', 'title', 'user_id')
            ->embed([
                'comments:id,body,created_at' => fn($q) => $q
                    ->where('approved', true)
                    ->limit(1)
                    ->oldest('created_at'),
                'tags',
                'user:id,name',
            ])
            ->embedCount('comments')
            ->where('status', true)
            ->first();

        $array = $post->toArray();

        // Selected columns only
        $this->assertArrayHasKey('id',      $array);
        $this->assertArrayHasKey('title',   $array);
        $this->assertArrayHasKey('user_id', $array);

        // embedCount
        $this->assertEquals(3, $array['comments_count']);

        // Constrained comments (1 oldest approved comment)
        $this->assertCount(1, $array['comments']);
        $this->assertEquals('Great post!', $array['comments'][0]['body']);

        // Many-to-many tags with pivot
        $this->assertCount(2, $array['tags']);
        $this->assertIsObject($array['tags'][0]['pivot']);

        // Column-scoped user (only id + name)
        $this->assertArrayHasKey('id',   $array['user']);
        $this->assertArrayHasKey('name', $array['user']);
        $this->assertArrayNotHasKey('email',      $array['user']);
        $this->assertArrayNotHasKey('created_at', $array['user']);
    }

    /**
     * Collect all posts, each with tags and comment counts, then filter
     * by present() — a realistic "list posts with engagement" query.
     */
    public function testListPostsWithTagsAndCommentCounts(): void
    {
        $posts = MockPost::query()
            ->select('id', 'title', 'user_id')
            ->embed('tags')
            ->embedCount([
                'comments' => fn($q) => $q->where('approved', true),
            ])
            ->present('comments')
            ->orderBy('id')
            ->get();

        // Post 4 has no comments so should be excluded
        $this->assertCount(3, $posts);

        $ids = $posts->map->id->toArray();
        $this->assertNotContains(4, $ids);

        foreach ($posts->toArray() as $post) {
            $this->assertArrayHasKey('tags',           $post);
            $this->assertArrayHasKey('comments_count', $post);
        }
    }

    /**
     * Users → posts → tags: eager-load two levels, combine with whereLinked
     * so only users who have at least one published post are included.
     */
    public function testUsersWithPublishedPostsAndTags(): void
    {
        $users = MockUser::query()
            ->whereLinked('posts', 'status', true)
            ->embed('posts.tags')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $users);

        foreach ($users->toArray() as $user) {
            $this->assertArrayHasKey('posts', $user);
            foreach ($user['posts'] as $post) {
                $this->assertArrayHasKey('tags', $post);
            }
        }
    }

    /**
     * Tag → posts (inverse many-to-many) with embedCount on nested comments.
     */
    public function testTagWithPostsAndNestedCommentCounts(): void
    {
        $tag   = MockTag::embedCount('posts.comments')->find(1);
        $array = $tag->toArray();

        $this->assertArrayHasKey('posts', $array);

        $post1 = collect($array['posts'])->first(fn($p) => $p['id'] === 1);
        $this->assertEquals(3, $post1['comments_count']);
    }

    /**
     * Tag with embedCount on posts (top-level many-to-many count).
     */
    public function testTagEmbedCountPosts(): void
    {
        $tag   = MockTag::embedCount('posts')->find(1);
        $array = $tag->toArray();

        $this->assertArrayNotHasKey('posts', $array); // count only, no records
        $this->assertEquals(2, $array['posts_count']);
    }

    /**
     * embed() collection result: all posts with their user and the user's
     * comments. Tests N+1 prevention at the collection level.
     */
    public function testCollectionEmbedTwoLevels(): void
    {
        $posts = MockPost::embed('user.comments:body')->get();

        $this->assertCount(4, $posts);

        foreach ($posts->toArray() as $post) {
            $this->assertArrayHasKey('user', $post);
            $this->assertArrayHasKey('comments', $post['user']);
        }

        // Jane's post (id=3) → user=Jane (id=2) → 2 comments (ids 2, 4)
        $janePost = collect($posts->toArray())->first(fn($p) => $p['id'] === 3);
        $this->assertCount(2, $janePost['user']['comments']);
    }

    /**
     * When embed is applied to a collection and some parents share the same
     * child (Jane's post and John's posts share different users), results
     * must be correctly partitioned.
     */
    public function testEagerLoadDoesNotCrossContaminateUsers(): void
    {
        $posts = MockPost::embed('user')->get();

        foreach ($posts->toArray() as $post) {
            if ($post['id'] === 3) {
                // Jane Post must point to Jane
                $this->assertEquals('Jane Smith', $post['user']['name']);
            } else {
                // All other posts belong to John Doe
                $this->assertEquals('John Doe', $post['user']['name']);
            }
        }
    }
}
