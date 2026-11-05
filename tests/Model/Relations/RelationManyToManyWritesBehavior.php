<?php

namespace Tests\Unit\Model\ManyToManyWrites;

use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Relations\BindToMany;
use Phaseolies\Support\Collection;
use Tests\Support\Database\ModelQueryDriverTestCase;

/**
 * Records are created and saved through a many-to-many relationship together
 * with their pivot row, in one transaction.
 */
abstract class RelationManyToManyWritesTest extends ModelQueryDriverTestCase
{
    protected function tableDefinitions(): array
    {
        return [
            'articles' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'title', 'type' => 'string'],
            ],
            'topics' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'name', 'type' => 'string'],
            ],
            'article_topic' => [
                ['name' => 'article_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'topic_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'note', 'type' => 'string', 'nullable' => true],
            ],
        ];
    }

    protected function seedData(): array
    {
        return [
            'articles' => [['title' => 'Alpha'], ['title' => 'Beta']],
            'topics' => [['name' => 'php'], ['name' => 'sql']],
            // Alpha: php | Beta: sql
            'article_topic' => [
                ['article_id' => 1, 'topic_id' => 1, 'note' => null],
                ['article_id' => 2, 'topic_id' => 2, 'note' => null],
            ],
        ];
    }

    private function scalar(string $sql)
    {
        return $this->pdo->query($sql)->fetchColumn();
    }

    private function names(iterable $models): array
    {
        $names = [];
        foreach ($models as $model) {
            $names[] = $model->name;
        }
        sort($names);

        return $names;
    }

    private function assertNoTransactionLeft(): void
    {
        $this->assertSame(0, Database::transactionLevel('default'));
        $this->assertFalse($this->pdo->inTransaction());
    }

    // ------------------------------------------------------------ create

    public function testCreateAddsTheRecordAndLinksItToTheParent(): void
    {
        $article = Article::find(1);

        $topic = $article->topics()->create(['name' => 'new']);

        $this->assertInstanceOf(Topic::class, $topic);
        $this->assertNotNull($topic->id);
        $this->assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM article_topic WHERE article_id = 1 AND topic_id = {$topic->id}"));
        $this->assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM article_topic WHERE topic_id = {$topic->id}"), 'linked to this article only');
        $this->assertSame(['new', 'php'], $this->names($article->topics()->get()));
        $this->assertSame(['new', 'php'], $this->names(Article::find(1)->topics), 'the property sees it too');
        $this->assertSame(['sql'], $this->names(Article::find(2)->topics()->get()), 'other articles are untouched');
        $this->assertSame(3, (int) $this->scalar('SELECT COUNT(*) FROM topics'));
        $this->assertNoTransactionLeft();
    }

    public function testCreateCanSetPivotColumns(): void
    {
        $topic = Article::find(1)->topics()->create(['name' => 'new'], ['note' => 'added by test']);

        $this->assertSame('added by test', $this->scalar("SELECT note FROM article_topic WHERE topic_id = {$topic->id}"));
    }

    public function testCreateWorksFromTheInverseSide(): void
    {
        $article = Topic::find(2)->articles()->create(['title' => 'Gamma']);

        $this->assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM article_topic WHERE topic_id = 2 AND article_id = {$article->id}"));
        $this->assertSame(['Beta', 'Gamma'], $this->titles(Topic::find(2)->articles()->get()));
    }

    public function testCreateWorksWithExplicitKeys(): void
    {
        $topic = Article::find(1)->explicitTopics()->create(['name' => 'explicit']);

        $this->assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM article_topic WHERE article_id = 1 AND topic_id = {$topic->id}"));
    }

    private function titles(iterable $models): array
    {
        $titles = [];
        foreach ($models as $model) {
            $titles[] = $model->title;
        }
        sort($titles);

        return $titles;
    }

    public function testCreateKeepsNothingWhenTheRecordCannotBeSaved(): void
    {
        try {
            Article::find(1)->lockedTopics()->create(['name' => 'nope']); // LockedTopic has no $creatable
            $this->fail('Expected an exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('creatable', $e->getMessage());
        }

        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM topics'));
        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM article_topic'));
        $this->assertNoTransactionLeft();
    }

    public function testCreateRollsBackTheRecordWhenTheLinkFails(): void
    {
        try {
            // the pivot table has no such column, so the link cannot be written
            Article::find(1)->topics()->create(['name' => 'orphan'], ['no_such_column' => 'x']);
            $this->fail('Expected an exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Failed to create relationship', $e->getMessage());
        }

        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM topics'), 'the topic was rolled back');
        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM article_topic'));
        $this->assertNoTransactionLeft();
    }

    // ------------------------------------------------------------ createMany

    public function testCreateManyReturnsCollectionAndLinksEveryRecord(): void
    {
        $topics = Article::find(2)->topics()->createMany([['name' => 'one'], ['name' => 'two']]);

        $this->assertInstanceOf(Collection::class, $topics);
        $this->assertCount(2, $topics);
        $this->assertSame(['one', 'sql', 'two'], $this->names(Article::find(2)->topics()->get()));
        $this->assertSame(3, (int) $this->scalar('SELECT COUNT(*) FROM article_topic WHERE article_id = 2'));
        $this->assertNoTransactionLeft();
    }

    public function testCreateManyIsAllOrNothing(): void
    {
        try {
            Article::find(1)->topics()->createMany([['name' => 'fine'], ['name' => 'bad'], ['name' => 'never']]);
            $this->fail('Expected the hook exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('bad topic', $e->getMessage());
        }

        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM topics'), 'no topic was kept');
        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM article_topic'), 'no link was kept');
        $this->assertNoTransactionLeft();
    }

    public function testCreateManyWithPivotDataAndNoRecords(): void
    {
        Article::find(1)->topics()->createMany([['name' => 'a'], ['name' => 'b']], ['note' => 'bulk']);

        $this->assertSame(2, (int) $this->scalar("SELECT COUNT(*) FROM article_topic WHERE note = 'bulk'"));
        $this->assertCount(0, Article::find(1)->topics()->createMany([]));
    }

    // ------------------------------------------------------------ store

    public function testStoreSavesANewModelAndLinksIt(): void
    {
        $topic = new Topic();
        $topic->name = 'stored';

        $this->assertSame($topic, Article::find(1)->topics()->store($topic));
        $this->assertNotNull($topic->id);
        $this->assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM article_topic WHERE article_id = 1 AND topic_id = {$topic->id}"));
    }

    public function testStoreLinksAnExistingModelOnce(): void
    {
        $article = Article::find(1);
        $sql = Topic::find(2); // linked to Beta, not to Alpha

        $article->topics()->store($sql);
        $article->topics()->store($sql);
        $article->topics()->store($sql);

        $this->assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM article_topic WHERE article_id = 1 AND topic_id = 2'));
        $this->assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM article_topic WHERE article_id = 2 AND topic_id = 2'), 'the other link stays');
        $this->assertSame(['php', 'sql'], $this->names($article->topics()->get()));
    }

    public function testStoreDoesNotDuplicateAnAlreadyLinkedModel(): void
    {
        $php = Topic::find(1);
        $php->name = 'php 8';

        Article::find(1)->topics()->store($php);

        $this->assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM article_topic WHERE article_id = 1 AND topic_id = 1'));
        $this->assertSame('php 8', $this->scalar('SELECT name FROM topics WHERE id = 1'), 'the model itself is saved');
    }

    public function testStoreWithPivotDataUpdatesTheStoredValuesOfAnExistingLink(): void
    {
        $article = Article::find(1);

        $article->topics()->store(Topic::find(1), ['note' => 'first']);
        $this->assertSame('first', $this->scalar('SELECT note FROM article_topic WHERE article_id = 1 AND topic_id = 1'));
        $this->assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM article_topic WHERE article_id = 1 AND topic_id = 1'));

        $article->topics()->store(Topic::find(1), ['note' => 'second']);
        $this->assertSame('second', $this->scalar('SELECT note FROM article_topic WHERE article_id = 1 AND topic_id = 1'));

        $article->topics()->store(Topic::find(1)); // no pivot data: left as it is
        $this->assertSame('second', $this->scalar('SELECT note FROM article_topic WHERE article_id = 1 AND topic_id = 1'));
    }

    public function testStoreManyLinksEachModelOnce(): void
    {
        $new = new Topic();
        $new->name = 'fresh';

        $stored = Article::find(1)->topics()->storeMany([$new, Topic::find(2), Topic::find(1)]);

        $this->assertCount(3, $stored);
        $this->assertSame($new, $stored[0]);
        $this->assertSame(['fresh', 'php', 'sql'], $this->names(Article::find(1)->topics()->get()));
        $this->assertSame(3, (int) $this->scalar('SELECT COUNT(*) FROM article_topic WHERE article_id = 1'));
        $this->assertNoTransactionLeft();
    }

    public function testStoreManyIsAllOrNothing(): void
    {
        $good = new Topic();
        $good->name = 'good';
        $bad = new Topic();
        $bad->name = 'bad';

        try {
            Article::find(1)->topics()->storeMany([$good, $bad]);
            $this->fail('Expected the hook exception');
        } catch (\RuntimeException) {
        }

        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM topics'));
        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM article_topic'));
        $this->assertNoTransactionLeft();
    }

    public function testStoreRejectsAModelOfTheWrongClassAndChangesNothing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        try {
            Article::find(1)->topics()->store(Article::find(2));
        } finally {
            $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM article_topic'));
        }
    }

    // ------------------------------------------------------------ guards

    public function testWritingThroughARelationOfAnUnsavedModelThrowsAndChangesNothing(): void
    {
        foreach ([
            fn () => (new Article())->topics()->create(['name' => 'x']),
            fn () => (new Article())->topics()->createMany([['name' => 'x']]),
            fn () => (new Article())->topics()->store(new Topic()),
            fn () => (new Article())->topics()->storeMany([new Topic()]),
        ] as $write) {
            try {
                $write();
                $this->fail('Expected a LogicException');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('unsaved', $e->getMessage());
            }
        }

        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM topics'));
        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM article_topic'));
    }

    // ------------------------------------------------------------ firstOrCreate / updateOrCreate

    public function testFirstOrCreateStaysInsideTheParentsRecords(): void
    {
        $article = Article::find(1);

        $found = $article->topics()->firstOrCreate(['name' => 'php']);
        $this->assertEquals(1, $found->id);
        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM topics'), 'nothing was created');

        // "sql" exists but is linked to another article, so it is created and linked here
        $created = $article->topics()->firstOrCreate(['name' => 'sql']);
        $this->assertNotEquals(2, $created->id);
        $this->assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM article_topic WHERE article_id = 1 AND topic_id = {$created->id}"));
        $this->assertSame(2, (int) $this->scalar("SELECT COUNT(*) FROM topics WHERE name = 'sql'"));
    }

    public function testUpdateOrCreateUpdatesAParentsRecordOrCreatesAndLinksOne(): void
    {
        $article = Article::find(1);

        $article->topics()->updateOrCreate(['name' => 'php'], ['name' => 'php 8']);
        $this->assertSame('php 8', $this->scalar('SELECT name FROM topics WHERE id = 1'));
        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM topics'));

        $new = $article->topics()->updateOrCreate(['name' => 'rust'], ['name' => 'rust']);
        $this->assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM article_topic WHERE article_id = 1 AND topic_id = {$new->id}"));
    }

    // ------------------------------------------------------------ hooks and transactions

    public function testHooksRunAndCanBeSkipped(): void
    {
        Topic::$created = 0;
        $article = Article::find(1);

        $article->topics()->create(['name' => 'loud']);
        $this->assertSame(1, Topic::$created);

        $article->topics()->withoutHook()->create(['name' => 'quiet']);
        $this->assertSame(1, Topic::$created);

        $stored = new Topic();
        $stored->name = 'quietly stored';
        $article->topics()->withoutHook()->store($stored);
        $this->assertSame(1, Topic::$created);

        $this->assertSame(['loud', 'php', 'quiet', 'quietly stored'], $this->names($article->topics()->get()));
    }

    public function testAnOuterTransactionCanRollEverythingBack(): void
    {
        $db = new Database('default');

        try {
            $db->transaction(function () {
                Article::find(1)->topics()->create(['name' => 'inside']);
                Article::find(1)->topics()->createMany([['name' => 'more']]);

                throw new \RuntimeException('abort');
            });
            $this->fail('Expected the exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('abort', $e->getMessage());
        }

        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM topics'));
        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM article_topic'));
        $this->assertNoTransactionLeft();
    }

    public function testAnOuterTransactionKeepsTheWritesWhenItCommits(): void
    {
        (new Database('default'))->transaction(function () {
            Article::find(1)->topics()->create(['name' => 'kept']);
        });

        $this->assertSame(['kept', 'php'], $this->names(Article::find(1)->topics()->get()));
        $this->assertNoTransactionLeft();
    }

    public function testRelationQueriesStillWork(): void
    {
        $query = Article::find(1)->topics();

        $this->assertInstanceOf(BindToMany::class, $query);
        $this->assertSame(['php'], $this->names($query->get()));
        $this->assertSame(1, $query->count());
    }
}

class Article extends Model
{
    protected $table = 'articles';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['title'];

    public function topics(): BindToMany
    {
        return $this->bindToMany(Topic::class);
    }

    public function explicitTopics(): BindToMany
    {
        return $this->bindToMany(Topic::class, 'article_id', 'topic_id', 'article_topic');
    }

    public function lockedTopics(): BindToMany
    {
        return $this->bindToMany(LockedTopic::class, 'article_id', 'topic_id', 'article_topic');
    }
}

class Topic extends Model
{
    public static int $created = 0;

    protected $table = 'topics';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['name'];

    protected $hooks = [
        'before_created' => [self::class, 'rejectBad'],
        'after_created' => [self::class, 'countCreated'],
    ];

    public static function rejectBad(Model $model): void
    {
        if ($model->name === 'bad') {
            throw new \RuntimeException('bad topic');
        }
    }

    public static function countCreated(Model $model): void
    {
        self::$created++;
    }

    public function articles(): BindToMany
    {
        return $this->bindToMany(Article::class);
    }
}

/**
 * Has no $creatable, so it cannot be saved.
 */
class LockedTopic extends Model
{
    protected $table = 'topics';
    protected $connection = 'default';
    protected $timeStamps = false;
}
