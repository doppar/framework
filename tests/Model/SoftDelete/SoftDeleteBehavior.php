<?php

namespace Tests\Unit\Model\SoftDelete;

use Carbon\Carbon;
use Phaseolies\Database\Entity\Hooks\HookHandler;
use Phaseolies\Database\Entity\Model;
use Phaseolies\DI\Container;
use Phaseolies\Support\UrlGenerator;
use Tests\Support\Database\ModelQueryDriverTestCase;
use Tests\Support\Model\MockSoftArticle;
use Tests\Support\Model\MockSoftAuthor;
use Tests\Support\Model\MockSoftLabel;
use Tests\Support\Model\MockSoftNote;

abstract class SoftDeleteTest extends ModelQueryDriverTestCase
{
    protected function tableDefinitions(): array
    {
        return [
            'authors' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'name', 'type' => 'string'],
            ],
            'articles' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'author_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'views', 'type' => 'integer', 'nullable' => true, 'default' => 0],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true],
                ['name' => 'updated_at', 'type' => 'datetime', 'nullable' => true],
                ['name' => 'deleted_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'notes' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'article_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'body', 'type' => 'string'],
                ['name' => 'removed_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'labels' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'deleted_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'article_label' => [
                ['name' => 'article_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'label_id', 'type' => 'integer', 'nullable' => true],
            ],
        ];
    }

    protected function seedData(): array
    {
        $stamp = '2024-01-01 00:00:00';

        return [
            'authors' => [
                ['name' => 'Ann'],
                ['name' => 'Ben'],
                ['name' => 'Cid'],
            ],
            'articles' => [
                ['author_id' => 1, 'title' => 'Alpha', 'views' => 10, 'created_at' => $stamp, 'updated_at' => $stamp],
                ['author_id' => 1, 'title' => 'Beta', 'views' => 20, 'created_at' => $stamp, 'updated_at' => $stamp],
                ['author_id' => 2, 'title' => 'Gamma', 'views' => 30, 'created_at' => $stamp, 'updated_at' => $stamp],
                ['author_id' => 2, 'title' => 'Delta', 'views' => 40, 'created_at' => $stamp, 'updated_at' => $stamp],
            ],
            'notes' => [
                ['article_id' => 1, 'body' => 'first'],
                ['article_id' => 1, 'body' => 'second'],
                ['article_id' => 3, 'body' => 'third'],
            ],
            'labels' => [
                ['name' => 'php'],
                ['name' => 'sql'],
                ['name' => 'legacy'],
            ],
            'article_label' => [
                ['article_id' => 1, 'label_id' => 1],
                ['article_id' => 1, 'label_id' => 2],
                ['article_id' => 2, 'label_id' => 3],
                ['article_id' => 3, 'label_id' => 3],
            ],
        ];
    }

    private function rawRow(string $table, int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function bindUrlGenerator(): void
    {
        Container::getInstance()->bind('url', fn() => new UrlGenerator('http://localhost'));
    }

    private function ids(iterable $models): array
    {
        $ids = [];
        foreach ($models as $model) {
            $ids[] = (int) $model->id;
        }
        sort($ids);

        return $ids;
    }

    // =========================================================================
    // Deleting and querying
    // =========================================================================

    public function testDeleteStampsTheRowInsteadOfRemovingIt(): void
    {
        $article = MockSoftArticle::find(1);

        $this->assertTrue($article->delete());
        $this->assertTrue($article->trashed());
        $this->assertNotNull($article->deleted_at);

        $row = $this->rawRow('articles', 1);
        $this->assertNotFalse($row);
        $this->assertNotNull($row['deleted_at']);
        $this->assertNotSame('2024-01-01 00:00:00', (string) $row['updated_at']);
    }

    public function testModelMirrorsTheExactValuesWritten(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-04 05:06:07'));

        try {
            $article = MockSoftArticle::find(1);
            $article->delete();

            $row = $this->rawRow('articles', 1);
            $this->assertSame('2026-03-04 05:06:07', $article->deleted_at);
            $this->assertSame('2026-03-04 05:06:07', $article->updated_at);
            $this->assertSame('2026-03-04 05:06:07', substr((string) $row['deleted_at'], 0, 19));
            $this->assertSame('2026-03-04 05:06:07', substr((string) $row['updated_at'], 0, 19));
            $this->assertSame([], $article->getDirtyAttributes());

            Carbon::setTestNow(Carbon::parse('2026-03-05 00:00:00'));
            $article->restore();

            $row = $this->rawRow('articles', 1);
            $this->assertNull($article->deleted_at);
            $this->assertNull($row['deleted_at']);
            $this->assertSame('2026-03-05 00:00:00', $article->updated_at);
            $this->assertSame('2026-03-05 00:00:00', substr((string) $row['updated_at'], 0, 19));
            $this->assertSame([], $article->getDirtyAttributes());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function testModelWithoutTimestampsDoesNotTouchUpdatedAt(): void
    {
        MockSoftNote::find(1)->delete();

        $this->assertArrayNotHasKey('updated_at', MockSoftNote::withTrashed()->find(1)->getAttributes());
        $this->assertNotNull($this->rawRow('notes', 1)['removed_at']);
    }

    public function testDeletingAModelWithoutAKeyIsANoOp(): void
    {
        $this->assertFalse((new MockSoftArticle())->delete());
        $this->assertSame(4, MockSoftArticle::count());
    }

    public function testTrashedRowsAreExcludedByDefault(): void
    {
        MockSoftArticle::find(1)->delete();

        $this->assertNull(MockSoftArticle::find(1));
        $this->assertSame([2, 3, 4], $this->ids(MockSoftArticle::all()));
        $this->assertSame(3, MockSoftArticle::count());
        $this->assertSame(3, MockSoftArticle::query()->count());
        $this->assertFalse(MockSoftArticle::where('id', 1)->exists());
        $this->assertSame(['Beta', 'Delta', 'Gamma'], MockSoftArticle::orderBy('title')->get()->pluck('title')->all());
    }

    public function testWithTrashedAndOnlyTrashed(): void
    {
        MockSoftArticle::find(1)->delete();
        MockSoftArticle::find(3)->delete();

        $this->assertSame([1, 2, 3, 4], $this->ids(MockSoftArticle::withTrashed()->get()));
        $this->assertSame([1, 3], $this->ids(MockSoftArticle::onlyTrashed()->get()));
        $this->assertSame([2, 4], $this->ids(MockSoftArticle::onlyTrashed()->withoutTrashed()->get()));
        $this->assertSame(1, (int) MockSoftArticle::withTrashed()->find(1)->id);
        $this->assertTrue(MockSoftArticle::withTrashed()->find(1)->trashed());
        $this->assertSame(2, MockSoftArticle::onlyTrashed()->count());
    }

    public function testOrConditionsCannotBypassTheConstraint(): void
    {
        MockSoftArticle::find(1)->delete();

        $articles = MockSoftArticle::where('id', 1)->orWhere('id', 2)->get();

        $this->assertSame([2], $this->ids($articles));
    }

    public function testNestedWhereAppliesTheConstraintOnce(): void
    {
        MockSoftArticle::find(1)->delete();

        $query = MockSoftArticle::where(function ($q) {
            $q->where('id', 1)->orWhere('id', 2);
        });

        $this->assertSame(1, substr_count($query->toSql(), 'deleted_at'));
        $this->assertSame([2], $this->ids($query->get()));
    }

    public function testLimitAndOffsetSkipTrashedRows(): void
    {
        MockSoftArticle::find(2)->delete();

        $page = MockSoftArticle::orderBy('id')->limit(2)->offset(1)->get();

        $this->assertSame([3, 4], $this->ids($page));
    }

    public function testOrWhereGroupAppliesTheConstraintOutsideTheGroup(): void
    {
        MockSoftArticle::find(1)->delete();

        $query = MockSoftArticle::where('id', 3)->orWhere(function ($q) {
            $q->where('id', 1)->where(function ($inner) {
                $inner->where('views', 10)->orWhere('views', 20);
            });
        });

        $this->assertSame(1, substr_count($query->toSql(), 'deleted_at'));
        $this->assertSame([3], $this->ids($query->get()));
    }

    public function testAggregatesAndGroupedCountsSkipTrashedRows(): void
    {
        MockSoftArticle::find(4)->delete();

        $this->assertEquals(60, MockSoftArticle::query()->sum('views'));
        $this->assertEquals(30, MockSoftArticle::query()->max('views'));
        $this->assertEquals(20, MockSoftArticle::query()->avg('views'));
        $this->assertSame(2, MockSoftArticle::query()->groupBy('author_id')->count());
        $this->assertSame(2, MockSoftArticle::where('author_id', 2)->withTrashed()->count());
    }

    public function testChunkingAndCursorPaginationSkipTrashedRows(): void
    {
        MockSoftArticle::find(2)->delete();

        $chunked = [];
        MockSoftArticle::query()->chunkById(2, function ($chunk) use (&$chunked) {
            foreach ($chunk as $article) {
                $chunked[] = (int) $article->id;
            }
        });
        $this->assertSame([1, 3, 4], $chunked);

        $this->bindUrlGenerator();

        $page = MockSoftArticle::query()->cursorPaginate(10);
        $this->assertSame([1, 3, 4], $this->ids($page['data']));
    }

    public function testPaginationCountsOnlyLiveRows(): void
    {
        MockSoftArticle::find(4)->delete();
        $this->bindUrlGenerator();

        $page = MockSoftArticle::query()->paginate(2);

        $this->assertSame(3, $page['total']);
        $this->assertSame([1, 2], $this->ids($page['data']));
    }

    public function testBulkUpdateAndIncrementSkipTrashedRows(): void
    {
        MockSoftArticle::find(1)->delete();

        MockSoftArticle::where('author_id', 1)->update(['title' => 'Bulk']);
        MockSoftArticle::where('author_id', 1)->increment('views', 1);

        $this->assertSame('Alpha', $this->rawRow('articles', 1)['title']);
        $this->assertSame(10, (int) $this->rawRow('articles', 1)['views']);
        $this->assertSame('Bulk', $this->rawRow('articles', 2)['title']);
        $this->assertSame(21, (int) $this->rawRow('articles', 2)['views']);
    }

    public function testTableAliasIsUsedToQualifyTheColumn(): void
    {
        $sql = MockSoftArticle::query()->from('articles as a')->where('a.id', 1)->toSql();

        $this->assertStringContainsString('a.deleted_at IS NULL', $sql);
        $this->assertStringNotContainsString('articles as a.deleted_at', $sql);

        MockSoftArticle::find(1)->delete();

        $this->assertNull(MockSoftArticle::query()->from('articles as a')->where('a.id', 1)->first());
        $this->assertNotNull(MockSoftArticle::query()->from('articles a')->where('a.id', 2)->first());
    }

    public function testSavingAndIncrementingATrashedModelStillPersists(): void
    {
        MockSoftArticle::find(1)->delete();

        $article = MockSoftArticle::withTrashed()->find(1);
        $article->title = 'Renamed';
        $this->assertTrue($article->save());
        $this->assertSame(1, $article->increment('views', 5));

        $row = $this->rawRow('articles', 1);
        $this->assertSame('Renamed', $row['title']);
        $this->assertSame(15, (int) $row['views']);
        $this->assertNotNull($row['deleted_at']);
    }

    public function testFreshKeepsTheTrashedMode(): void
    {
        MockSoftArticle::find(1)->delete();

        $this->assertNotNull(MockSoftArticle::withTrashed()->where('id', 1)->fresh());
        $this->assertNull(MockSoftArticle::where('id', 1)->fresh());
    }

    // =========================================================================
    // Restoring and force deleting
    // =========================================================================

    public function testRestoreBringsTheModelBack(): void
    {
        $article = MockSoftArticle::find(1);
        $article->delete();

        $this->assertTrue($article->restore());
        $this->assertFalse($article->trashed());
        $this->assertNull($this->rawRow('articles', 1)['deleted_at']);
        $this->assertNotNull(MockSoftArticle::find(1));
    }

    public function testBulkRestoreTargetsTrashedRows(): void
    {
        MockSoftArticle::where('author_id', 1)->delete();
        $this->assertSame(2, MockSoftArticle::count());

        $this->assertTrue(MockSoftArticle::where('id', 1)->restore());

        $this->assertSame([1, 3, 4], $this->ids(MockSoftArticle::all()));
        $this->assertSame('2024-01-01 00:00:00', (string) $this->rawRow('articles', 3)['updated_at']);
    }

    public function testRestoringALiveModelKeepsItLive(): void
    {
        $article = MockSoftArticle::find(1);

        $this->assertTrue($article->restore());
        $this->assertFalse($article->trashed());
        $this->assertNull($this->rawRow('articles', 1)['deleted_at']);
    }

    public function testDeletingATrashedModelRestampsIt(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));

        try {
            MockSoftArticle::find(1)->delete();

            Carbon::setTestNow(Carbon::parse('2026-02-01 00:00:00'));
            MockSoftArticle::withTrashed()->find(1)->delete();

            $this->assertSame('2026-02-01 00:00:00', substr((string) $this->rawRow('articles', 1)['deleted_at'], 0, 19));
            $this->assertSame(1, MockSoftArticle::onlyTrashed()->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function testBulkSoftDeleteDoesNotRestampTrashedRows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));

        try {
            MockSoftArticle::find(1)->delete();

            Carbon::setTestNow(Carbon::parse('2026-02-01 00:00:00'));
            MockSoftArticle::where('author_id', 1)->delete();

            $this->assertSame('2026-01-01 00:00:00', substr((string) $this->rawRow('articles', 1)['deleted_at'], 0, 19));
            $this->assertSame('2026-02-01 00:00:00', substr((string) $this->rawRow('articles', 2)['deleted_at'], 0, 19));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function testForceDeleteRemovesTheRow(): void
    {
        $article = MockSoftArticle::find(1);

        $this->assertTrue($article->forceDelete());
        $this->assertFalse($this->rawRow('articles', 1));
        $this->assertSame(3, MockSoftArticle::withTrashed()->count());
    }

    public function testForceDeleteRemovesAnAlreadyTrashedModel(): void
    {
        MockSoftArticle::find(1)->delete();

        $this->assertTrue(MockSoftArticle::withTrashed()->find(1)->forceDelete());
        $this->assertFalse($this->rawRow('articles', 1));
    }

    public function testBulkDeleteSoftDeletesAndBulkForceDeleteRemoves(): void
    {
        $this->assertTrue(MockSoftArticle::where('author_id', 2)->delete());
        $this->assertSame([3, 4], $this->ids(MockSoftArticle::onlyTrashed()->get()));
        $this->assertNotFalse($this->rawRow('articles', 3));

        $this->assertTrue(MockSoftArticle::onlyTrashed()->forceDelete());
        $this->assertFalse($this->rawRow('articles', 3));
        $this->assertFalse($this->rawRow('articles', 4));
        $this->assertSame([1, 2], $this->ids(MockSoftArticle::withTrashed()->get()));
    }

    public function testPurgeSoftDeletesLiveRowsOnly(): void
    {
        MockSoftArticle::find(1)->delete();
        $before = $this->rawRow('articles', 1)['deleted_at'];

        $this->assertSame(1, MockSoftArticle::query()->purge(1, 2));
        $this->assertSame((string) $before, (string) $this->rawRow('articles', 1)['deleted_at']);
        $this->assertNotNull($this->rawRow('articles', 2)['deleted_at']);
        $this->assertSame([3, 4], $this->ids(MockSoftArticle::all()));
    }

    public function testCustomColumnIsHonoured(): void
    {
        MockSoftNote::find(1)->delete();

        $this->assertNotNull($this->rawRow('notes', 1)['removed_at']);
        $this->assertSame([2, 3], $this->ids(MockSoftNote::all()));
        $this->assertSame([1], $this->ids(MockSoftNote::onlyTrashed()->get()));
    }

    // =========================================================================
    // Hooks
    // =========================================================================

    public function testDeleteAndRestoreHooksFire(): void
    {
        $events = [];
        $record = function (string $event) use (&$events) {
            return function (Model $model) use (&$events, $event) {
                $events[] = $event . ($model->isForceDeleting() ? ':force' : '');
            };
        };

        HookHandler::register(MockSoftArticle::class, [
            'before_deleted' => $record('before_deleted'),
            'after_deleted' => $record('after_deleted'),
            'before_restored' => $record('before_restored'),
            'after_restored' => $record('after_restored'),
        ]);

        try {
            $article = MockSoftArticle::find(1);
            $article->delete();
            $article->restore();
            $article->forceDelete();
        } finally {
            unset(HookHandler::$hooks[MockSoftArticle::class]);
        }

        $this->assertSame([
            'before_deleted',
            'after_deleted',
            'before_restored',
            'after_restored',
            'before_deleted:force',
            'after_deleted:force',
        ], $events);
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function testLazyAndEagerRelationsExcludeTrashedRelated(): void
    {
        MockSoftArticle::find(1)->delete();

        $author = MockSoftAuthor::find(1);
        $this->assertSame([2], $this->ids($author->articles));

        $authors = MockSoftAuthor::query()->embed('articles')->orderBy('id')->get();
        $this->assertSame([2], $this->ids($authors[0]->articles));
        $this->assertSame([3, 4], $this->ids($authors[1]->articles));

        $authors = MockSoftAuthor::query()->embedCount('articles')->orderBy('id')->get();
        $this->assertSame(1, (int) $authors[0]->articles_count);
        $this->assertSame(2, (int) $authors[1]->articles_count);
    }

    public function testManyToManyExcludesTrashedRelated(): void
    {
        MockSoftLabel::find(2)->delete();

        $article = MockSoftArticle::query()->embed('labels')->where('id', 1)->first();
        $this->assertSame([1], $this->ids($article->labels));

        $article = MockSoftArticle::query()->embedCount('labels')->where('id', 1)->first();
        $this->assertSame(1, (int) $article->labels_count);
    }

    public function testPresentAndAbsentIgnoreTrashedRelated(): void
    {
        MockSoftArticle::find(3)->delete();
        MockSoftArticle::find(4)->delete();

        $this->assertSame([1], $this->ids(MockSoftAuthor::query()->present('articles')->get()));
        $this->assertSame([2, 3], $this->ids(MockSoftAuthor::query()->absent('articles')->get()));

        $withTrashed = MockSoftAuthor::query()->present('articles', fn($q) => $q->withTrashed())->get();
        $this->assertSame([1, 2], $this->ids($withTrashed));

        $onlyTrashed = MockSoftAuthor::query()->present('articles', fn($q) => $q->onlyTrashed())->get();
        $this->assertSame([2], $this->ids($onlyTrashed));
    }

    public function testPresentManyToManyIgnoresTrashedRelated(): void
    {
        MockSoftLabel::find(3)->delete();

        $this->assertSame([1], $this->ids(MockSoftArticle::query()->present('labels')->get()));

        $named = MockSoftArticle::query()->present('labels', fn($q) => $q->where('name', 'legacy'))->get();
        $this->assertSame([], $this->ids($named));
    }

    public function testOrPresentIfExistsAndWhereLinkedIgnoreTrashedRelated(): void
    {
        MockSoftArticle::find(3)->delete();
        MockSoftArticle::find(4)->delete();

        $this->assertSame([1], $this->ids(MockSoftAuthor::query()->ifExists('articles')->get()));
        $this->assertSame([2, 3], $this->ids(MockSoftAuthor::query()->ifNotExists('articles')->get()));
        $this->assertSame([1, 3], $this->ids(MockSoftAuthor::where('id', 3)->orPresent('articles')->get()));
        $this->assertSame([], $this->ids(MockSoftAuthor::query()->whereLinked('articles', 'title', 'Gamma')->get()));
        $this->assertSame([1], $this->ids(MockSoftAuthor::query()->whereLinked('articles', 'title', 'Alpha')->get()));
    }

    public function testNestedRelationsThroughManyToManyIgnoreTrashedRows(): void
    {
        // Label 3 is attached to articles 2 (Ann) and 3 (Ben)
        $this->assertSame([1, 2], $this->ids(MockSoftAuthor::query()->present('articles.labels')->get()));

        MockSoftLabel::find(1)->delete();
        MockSoftLabel::find(2)->delete();
        MockSoftArticle::find(3)->delete();

        // Ann reaches label 3 through live article 2; Ben's article 3 is trashed
        $this->assertSame([1], $this->ids(MockSoftAuthor::query()->present('articles.labels')->get()));
        $this->assertSame([1], $this->ids(MockSoftAuthor::query()->whereLinked('articles.labels', 'name', 'legacy')->get()));
        $this->assertSame([], $this->ids(MockSoftAuthor::query()->whereLinked('articles.labels', 'name', 'php')->get()));
    }

    public function testNestedEagerLoadingSkipsTrashedRows(): void
    {
        MockSoftArticle::find(2)->delete();
        MockSoftNote::find(2)->delete();

        $author = MockSoftAuthor::query()->embed('articles.notes')->where('id', 1)->first();

        $this->assertSame([1], $this->ids($author->articles));
        $this->assertSame([1], $this->ids($author->articles[0]->notes));
    }

    public function testBelongsToTrashedParentResolvesToNull(): void
    {
        $note = MockSoftNote::find(1);
        MockSoftArticle::find(1)->delete();

        $article = MockSoftArticle::query()->embed('author')->withTrashed()->where('id', 1)->first();

        $this->assertSame('Ann', $article->author->name);
        $this->assertNull(MockSoftArticle::find((int) $note->article_id));
    }

    public function testNestedPresentAndWhereLinkedIgnoreTrashedRows(): void
    {
        // Article 3 holds Ben's only note; trashing the article hides it
        MockSoftArticle::find(3)->delete();

        $this->assertSame([1], $this->ids(MockSoftAuthor::query()->present('articles.notes')->get()));
        $this->assertSame([], $this->ids(MockSoftAuthor::query()->whereLinked('articles.notes', 'body', 'third')->get()));

        // Trashing Ann's notes hides them at the end of the chain
        MockSoftNote::query()->where('article_id', 1)->delete();

        $this->assertSame([], $this->ids(MockSoftAuthor::query()->present('articles.notes')->get()));
        $this->assertSame([], $this->ids(MockSoftAuthor::query()->whereLinked('articles.notes', 'body', 'first')->get()));
    }

    // =========================================================================
    // Models without #[SoftDeletes]
    // =========================================================================

    public function testModelsWithoutTheAttributeAreUnaffected(): void
    {
        $this->assertFalse((new MockSoftAuthor())->usesSoftDeletes());
        $this->assertStringNotContainsString('deleted_at', MockSoftAuthor::where('id', 1)->toSql());

        $this->assertTrue(MockSoftAuthor::find(3)->delete());
        $this->assertFalse($this->rawRow('authors', 3));
    }

    public function testSoftDeleteMethodsThrowOnModelsWithoutTheAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('not marked #[SoftDeletes]');

        MockSoftAuthor::withTrashed();
    }

    public function testModelRestoreThrowsOnModelsWithoutTheAttribute(): void
    {
        $this->expectException(\LogicException::class);

        MockSoftAuthor::find(1)->restore();
    }

    public function testModelForceDeleteThrowsOnModelsWithoutTheAttribute(): void
    {
        try {
            MockSoftAuthor::find(1)->forceDelete();
            $this->fail('Expected a LogicException.');
        } catch (\LogicException) {
            $this->assertNotFalse($this->rawRow('authors', 1));
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function builderOnlyMethods(): array
    {
        return [
            'onlyTrashed' => ['onlyTrashed'],
            'withoutTrashed' => ['withoutTrashed'],
            'restore' => ['restore'],
            'forceDelete' => ['forceDelete'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('builderOnlyMethods')]
    public function testBuilderMethodsThrowOnModelsWithoutTheAttribute(string $method): void
    {
        try {
            MockSoftAuthor::query()->$method();
            $this->fail('Expected a LogicException.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString("{$method}()", $e->getMessage());
            $this->assertSame(3, MockSoftAuthor::count());
        }
    }

    public function testWithoutHookStillSoftDeletes(): void
    {
        $fired = false;
        HookHandler::register(MockSoftArticle::class, [
            'before_deleted' => function () use (&$fired) {
                $fired = true;
            },
        ]);

        try {
            MockSoftArticle::withoutHook()->find(1)->delete();
        } finally {
            unset(HookHandler::$hooks[MockSoftArticle::class]);
        }

        $this->assertFalse($fired);
        $this->assertNotNull($this->rawRow('articles', 1)['deleted_at']);
    }
}
