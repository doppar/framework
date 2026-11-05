<?php

namespace Tests\Unit\Model\RelationScope;

use Tests\Support\Database\ModelQueryDriverTestCase;
use Tests\Support\Model\MockSoftArticle;
use Tests\Support\Model\MockSoftAuthor;

/**
 * A relationship method is limited to the records of its parent: for every
 * read and write through it, and whatever the caller adds (including orWhere).
 */
abstract class RelationScopeTest extends ModelQueryDriverTestCase
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
            'authors' => [['name' => 'Ann'], ['name' => 'Ben'], ['name' => 'Cid']],
            'articles' => [
                ['author_id' => 1, 'title' => 'Alpha', 'views' => 10, 'created_at' => $stamp, 'updated_at' => $stamp],
                ['author_id' => 1, 'title' => 'Beta', 'views' => 20, 'created_at' => $stamp, 'updated_at' => $stamp],
                ['author_id' => 2, 'title' => 'Gamma', 'views' => 30, 'created_at' => $stamp, 'updated_at' => $stamp],
                ['author_id' => 2, 'title' => 'Delta', 'views' => 40, 'created_at' => $stamp, 'updated_at' => $stamp],
            ],
            'labels' => [['name' => 'php'], ['name' => 'sql'], ['name' => 'legacy']],
            // article 1: php, sql | article 2: legacy | article 3: legacy | article 4: none
            'article_label' => [
                ['article_id' => 1, 'label_id' => 1],
                ['article_id' => 1, 'label_id' => 2],
                ['article_id' => 2, 'label_id' => 3],
                ['article_id' => 3, 'label_id' => 3],
            ],
        ];
    }

    private function names(iterable $models, string $column = 'name'): array
    {
        $names = [];
        foreach ($models as $model) {
            $names[] = $model->{$column};
        }
        sort($names);

        return $names;
    }

    private function scalar(string $sql)
    {
        return $this->pdo->query($sql)->fetchColumn();
    }

    // ------------------------------------------------------------ many to many reads

    public function testManyToManyRelationMethodOnlyReturnsTheParentsRecords(): void
    {
        $this->assertSame(['php', 'sql'], $this->names(MockSoftArticle::find(1)->labels()->get()));
        $this->assertSame(['legacy'], $this->names(MockSoftArticle::find(2)->labels()->get()));
        $this->assertSame([], $this->names(MockSoftArticle::find(4)->labels()->get()));
    }

    public function testManyToManyRelationMethodCountsAndChecksOnlyTheParentsRecords(): void
    {
        $this->assertSame(2, MockSoftArticle::find(1)->labels()->count());
        $this->assertSame(1, MockSoftArticle::find(3)->labels()->count());
        $this->assertSame(0, MockSoftArticle::find(4)->labels()->count());
        $this->assertTrue(MockSoftArticle::find(1)->labels()->exists());
        $this->assertFalse(MockSoftArticle::find(4)->labels()->exists());
    }

    public function testManyToManyConditionsStayInsideTheParent(): void
    {
        $article = MockSoftArticle::find(1);

        $this->assertSame(['php'], $this->names($article->labels()->where('name', 'php')->get()));
        $this->assertSame([], $this->names($article->labels()->where('name', 'legacy')->get()));
        $this->assertSame(['sql'], $this->names($article->labels()->where('name', '!=', 'php')->orderBy('id')->get()));
        $this->assertSame('php', $article->labels()->orderBy('id')->first()->name);
        $this->assertNull($article->labels()->where('name', 'legacy')->first());
    }

    public function testOrWhereCannotReachRecordsOfOtherParents(): void
    {
        $article = MockSoftArticle::find(1);

        $this->assertSame(
            ['php'],
            $this->names($article->labels()->where('name', 'php')->orWhere('name', 'legacy')->get())
        );
        $this->assertSame(
            ['php'],
            $this->names($article->labels()->orWhere('name', 'php')->orWhere('name', 'legacy')->get())
        );
        $this->assertSame(
            ['php'],
            $this->names($article->labels()->where(fn($group) => $group->where('name', 'php')->orWhere('name', 'legacy'))->get())
        );
        $this->assertSame(
            ['php', 'sql'],
            $this->names($article->labels()->whereIn('name', ['php', 'sql', 'legacy'])->get())
        );
    }

    public function testOrWhereCannotReachRecordsOfOtherParentsOnOneToMany(): void
    {
        $author = MockSoftAuthor::find(1);

        $this->assertSame(
            ['Alpha'],
            $this->names($author->articles()->where('title', 'Alpha')->orWhere('title', 'Gamma')->get(), 'title')
        );
        $this->assertSame(
            ['Alpha', 'Beta'],
            $this->names($author->articles()->orWhere('title', 'Gamma')->orWhereIn('title', ['Alpha', 'Beta', 'Delta'])->get(), 'title')
        );
    }

    public function testOrWhereCannotReachRecordsOfOtherParentsOnTheInverse(): void
    {
        $article = MockSoftArticle::find(3); // belongs to Ben

        $this->assertNull($article->author()->where('name', 'Ann')->first());
        $this->assertSame('Ben', $article->author()->where('name', 'Ann')->orWhere('name', 'Ben')->first()->name);
        $this->assertNull($article->author()->where('name', 'Ann')->orWhere('name', 'Cid')->first());
    }

    public function testDuplicatePivotRowsDoNotDuplicateRecords(): void
    {
        $this->pdo->exec('INSERT INTO article_label (article_id, label_id) VALUES (1, 1)');

        $this->assertSame(['php', 'sql'], $this->names(MockSoftArticle::find(1)->labels()->get()));
        $this->assertSame(2, MockSoftArticle::find(1)->labels()->count());
    }

    // ------------------------------------------------------------ soft deletes and the scope together

    public function testSoftDeletedRelatedRecordsAreStillLeftOut(): void
    {
        $this->pdo->exec("UPDATE labels SET deleted_at = '2024-02-01 00:00:00' WHERE name = 'sql'");
        $this->pdo->exec("UPDATE articles SET deleted_at = '2024-02-01 00:00:00' WHERE title = 'Beta'");

        $this->assertSame(['php'], $this->names(MockSoftArticle::find(1)->labels()->get()));
        $this->assertSame(['php', 'sql'], $this->names(MockSoftArticle::find(1)->labels()->withTrashed()->get()));
        $this->assertSame(['Alpha'], $this->names(MockSoftAuthor::find(1)->articles()->get(), 'title'));
        $this->assertSame(['Alpha', 'Beta'], $this->names(MockSoftAuthor::find(1)->articles()->withTrashed()->get(), 'title'));
        $this->assertSame(['Beta'], $this->names(MockSoftAuthor::find(1)->articles()->onlyTrashed()->get(), 'title'));
    }

    // ------------------------------------------------------------ writes through the relation method

    public function testBuilderWritesThroughARelationOnlyTouchTheParentsRecords(): void
    {
        MockSoftAuthor::find(1)->articles()->update(['views' => 99]);

        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM articles WHERE views = 99 AND author_id = 1'));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM articles WHERE views = 99 AND author_id = 2'));

        MockSoftAuthor::find(2)->articles()->delete(); // soft delete

        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM articles WHERE deleted_at IS NOT NULL AND author_id = 2'));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM articles WHERE deleted_at IS NOT NULL AND author_id = 1'));
    }

    public function testManyToManyBuilderUpdateOnlyTouchesTheParentsRecords(): void
    {
        MockSoftArticle::find(2)->labels()->update(['name' => 'old']); // article 2 has only "legacy"

        $this->assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM labels WHERE name = 'old'"));
        $this->assertSame('old', $this->scalar('SELECT name FROM labels WHERE id = 3'));
        $this->assertSame('php', $this->scalar('SELECT name FROM labels WHERE id = 1'));
    }

    public function testLinkUnlinkAndRelateAreReflectedByTheRelationMethod(): void
    {
        $article = MockSoftArticle::find(4);

        $this->assertSame([], $this->names($article->labels()->get()));

        $article->labels()->link([3]);
        $this->assertSame(['legacy'], $this->names($article->labels()->get()));

        $article->labels()->relate([1, 2]);
        $this->assertSame(['php', 'sql'], $this->names($article->labels()->get()));

        $article->labels()->unlink();
        $this->assertSame(0, $article->labels()->count());
        $this->assertSame(['php', 'sql'], $this->names(MockSoftArticle::find(1)->labels()->get()), 'other articles are untouched');
    }

    // ------------------------------------------------------------ the rest still works

    public function testPropertyAccessAndEagerLoadingStillLoadPivotData(): void
    {
        $labels = MockSoftArticle::find(1)->labels;

        $this->assertSame(['php', 'sql'], $this->names($labels));
        $this->assertSame(1, (int) $labels[0]->pivot->article_id);

        $articles = MockSoftArticle::embed('labels')->orderBy('id')->get();
        $this->assertSame(['php', 'sql'], $this->names($articles[0]->labels));
        $this->assertSame(['legacy'], $this->names($articles[1]->labels));
    }

    public function testRelationsOfAnUnsavedModelMatchNothing(): void
    {
        $this->assertSame([], $this->names((new MockSoftArticle())->labels()->get()));
        $this->assertSame(0, (new MockSoftArticle())->labels()->count());
        $this->assertSame([], $this->names((new MockSoftAuthor())->articles()->get(), 'title'));
    }

    public function testRelationQueriesCanBeCloned(): void
    {
        $query = MockSoftArticle::find(1)->labels()->where('name', 'php');
        $copy = clone $query;

        $this->assertSame(['php'], $this->names($copy->get()));
        $this->assertSame(['php', 'sql'], $this->names((clone $query)->orWhere('name', 'sql')->get()));
        $this->assertSame(['php'], $this->names($query->get()), 'the original is not changed by its clones');
    }
}
