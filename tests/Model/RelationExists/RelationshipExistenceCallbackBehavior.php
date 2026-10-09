<?php

namespace Tests\Unit\Model\RelationExists;

use Tests\Support\Database\ModelQueryDriverTestCase;
use Tests\Support\Model\MockSoftArticle;
use Tests\Support\Model\MockSoftAuthor;

/**
 * Conditions added inside present() / orPresent() / ifExists() callbacks must
 * keep their meaning: OR, nested groups, raw conditions and NULL checks.
 */
abstract class RelationshipExistenceCallbackTest extends ModelQueryDriverTestCase
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

    private function ids(iterable $models): array
    {
        $ids = [];
        foreach ($models as $model) {
            $ids[] = (int) $model->id;
        }
        sort($ids);

        return $ids;
    }

    // Notes: article 1 => first, second; article 3 => third

    public function testPlainConditionStillFilters(): void
    {
        $this->assertSame([1], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->where('body', 'first'))->get()));
    }

    public function testOrWhereInsideCallbackIsHonoured(): void
    {
        $query = MockSoftArticle::query()->present('notes', fn($q) => $q->where('body', 'first')->orWhere('body', 'third'));

        $this->assertSame([1, 3], $this->ids($query->get()));
    }

    public function testNestedGroupInsideCallbackIsHonoured(): void
    {
        $this->assertSame([1], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->where(fn($g) => $g->where('body', 'first')))->get()));
        $this->assertSame([3], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->where(fn($g) => $g->where('body', 'first')->where('id', 3)->orWhere('body', 'third')))->get()));
    }

    public function testRawConditionInsideCallbackIsHonoured(): void
    {
        $this->assertSame([1], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->whereRaw('body = ?', ['first']))->get()));
    }

    public function testNullChecksInsideCallbackAreHonoured(): void
    {
        $this->assertSame([], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->whereNull('body'))->get()));
        $this->assertSame([1, 3], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->whereNotNull('body'))->get()));
        $this->assertSame([1, 3], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->where('body', '!=', null))->get()));
    }

    public function testOtherOperatorsStillWork(): void
    {
        $this->assertSame([1], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->whereIn('body', ['first']))->get()));
        $this->assertSame([3], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->where('body', '>', 'second'))->get()));
        $this->assertSame([3], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->whereNotIn('body', ['first', 'second']))->get()));
        $this->assertSame([1, 3], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->whereNotIn('body', []))->get()));
        $this->assertSame([1], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->where('body', 'like', 'fir%'))->get()));
        $this->assertSame([3], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->whereBetween('id', [3, 3]))->get()));
        $this->assertSame([], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->whereIn('body', []))->get()));
    }

    public function testValuesAreBoundNotInlined(): void
    {
        $this->assertSame([], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->where('body', "x' OR '1'='1"))->get()));
        $this->assertSame([1, 3], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->where('body', "it's")->orWhere('body', 'third')->orWhere('body', 'first'))->get()));
    }

    public function testBindingsKeepTheirOrderAroundTheCallback(): void
    {
        $query = MockSoftArticle::query()
            ->where('title', 'Gamma')
            ->present('notes', fn($q) => $q->where('body', 'third'))
            ->where('views', 30);

        $this->assertSame([3], $this->ids($query->get()));

        $query = MockSoftArticle::query()
            ->where('title', 'Alpha')
            ->orPresent('notes', fn($q) => $q->where('body', 'third'))
            ->where('views', '>', 0);

        $this->assertSame([1, 3], $this->ids($query->get()));
    }

    public function testCountAndPaginationKeepTheBindings(): void
    {
        $query = MockSoftArticle::query()->present('notes', fn($q) => $q->where('body', 'first')->orWhere('body', 'third'));

        $this->assertSame(2, $query->count());
    }

    public function testManyToManyCallbackIsHonoured(): void
    {
        // Labels: article 1 => php, sql; article 2 => legacy; article 3 => legacy
        $this->assertSame([1, 2, 3], $this->ids(MockSoftArticle::query()->present('labels', fn($q) => $q->where('name', 'php')->orWhere('name', 'legacy'))->get()));
        $this->assertSame([2, 3], $this->ids(MockSoftArticle::query()->present('labels', fn($q) => $q->where(fn($g) => $g->where('name', 'legacy')))->get()));
        $this->assertSame([], $this->ids(MockSoftArticle::query()->present('labels', fn($q) => $q->whereNull('name'))->get()));
        $this->assertSame([1], $this->ids(MockSoftArticle::query()->present('labels', fn($q) => $q->whereRaw('name = ?', ['sql']))->get()));
    }

    public function testNestedRelationCallbackIsHonoured(): void
    {
        // Ann owns articles 1 and 2, Ben owns 3 and 4
        $this->assertSame([1, 2], $this->ids(MockSoftAuthor::query()->present('articles.notes', fn($q) => $q->where('body', 'first')->orWhere('body', 'third'))->get()));
        $this->assertSame([1], $this->ids(MockSoftAuthor::query()->present('articles.notes', fn($q) => $q->where(fn($g) => $g->where('body', 'first')))->get()));
        $this->assertSame([2], $this->ids(MockSoftAuthor::query()->present('articles.notes', fn($q) => $q->whereRaw('body = ?', ['third']))->get()));
        $this->assertSame([], $this->ids(MockSoftAuthor::query()->present('articles.notes', fn($q) => $q->whereNull('body'))->get()));
    }

    public function testIfExistsUsesTheSameCallbackRules(): void
    {
        $this->assertSame([1, 3], $this->ids(MockSoftArticle::query()->ifExists('notes', fn($q) => $q->where('body', 'first')->orWhere('body', 'third'))->get()));
    }

    public function testSoftDeletedRelatedRowsStayExcludedAlongsideTheCallback(): void
    {
        \Tests\Support\Model\MockSoftNote::query()->where('body', 'third')->delete();

        $this->assertSame([1], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->where('body', 'first')->orWhere('body', 'third'))->get()));
        $this->assertSame([1, 3], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->withTrashed()->where('body', 'first')->orWhere('body', 'third'))->get()));
        $this->assertSame([3], $this->ids(MockSoftArticle::query()->present('notes', fn($q) => $q->onlyTrashed()->where('body', 'first')->orWhere('body', 'third'))->get()));
    }
}
