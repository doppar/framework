<?php

namespace Tests\Unit\Model\Relations;

use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Relations\BindTo;
use Phaseolies\Database\Entity\Relations\BindToMany;
use Phaseolies\Database\Entity\Relations\LinkMany;
use Phaseolies\Database\Entity\Relations\LinkOne;
use Tests\Support\Database\ModelQueryDriverTestCase;

/**
 * Relationship methods return relation classes, and the keys and the pivot
 * table are optional: they default to the usual naming convention.
 */
abstract class RelationDefaultsTest extends ModelQueryDriverTestCase
{
    protected function tableDefinitions(): array
    {
        return [
            'authors' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'name', 'type' => 'string'],
            ],
            'bios' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'author_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'writer_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'line', 'type' => 'string'],
            ],
            'articles' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'author_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'title', 'type' => 'string'],
            ],
            'topics' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'name', 'type' => 'string'],
            ],
            'article_topic' => [
                ['name' => 'article_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'topic_id', 'type' => 'integer', 'nullable' => true],
            ],
        ];
    }

    protected function seedData(): array
    {
        return [
            'authors' => [['name' => 'Ann'], ['name' => 'Ben']],
            'bios' => [['author_id' => 1, 'writer_id' => 2, 'line' => 'Ann writes']],
            'articles' => [
                ['author_id' => 1, 'title' => 'Alpha'],
                ['author_id' => 1, 'title' => 'Beta'],
                ['author_id' => 2, 'title' => 'Gamma'],
            ],
            'topics' => [['name' => 'php'], ['name' => 'sql']],
            'article_topic' => [
                ['article_id' => 1, 'topic_id' => 1],
                ['article_id' => 1, 'topic_id' => 2],
                ['article_id' => 2, 'topic_id' => 2],
            ],
        ];
    }

    public function testRelationMethodsCanBeTypeHintedWithRelationClasses(): void
    {
        foreach ([
            [Author::class, 'bio', LinkOne::class],
            [Author::class, 'articles', LinkMany::class],
            [Bio::class, 'author', BindTo::class],
            [Article::class, 'topics', BindToMany::class],
        ] as [$model, $method, $type]) {
            $returnType = (new \ReflectionMethod($model, $method))->getReturnType();
            $this->assertSame($type, $returnType->getName());
        }

        $author = Author::find(1);

        $this->assertInstanceOf(LinkOne::class, $author->bio());
        $this->assertInstanceOf(LinkMany::class, $author->articles());
        $this->assertInstanceOf(Builder::class, $author->bio(), 'relation classes are query builders');
        $this->assertInstanceOf(BindTo::class, Bio::find(1)->author());
        $this->assertInstanceOf(BindToMany::class, Article::find(1)->topics());
    }

    public function testLinkOneAndLinkManyDefaultTheirKeys(): void
    {
        $author = Author::find(1);

        $this->assertSame('Ann writes', $author->bio->line);
        $this->assertSame(['Alpha', 'Beta'], $author->articles->pluck('title')->all());
        $this->assertSame(['Gamma'], Author::find(2)->articles->pluck('title')->all());
        $this->assertNull(Author::find(2)->bio);
    }

    public function testBindToDefaultsItsKeys(): void
    {
        $this->assertSame('Ann', Bio::find(1)->author->name);
        $this->assertSame('Ben', Article::find(3)->author->name);
    }

    public function testBindToManyDefaultsKeysAndPivotTableInBothDirections(): void
    {
        $this->assertSame(['php', 'sql'], Article::find(1)->topics->pluck('name')->all());
        $this->assertSame(['sql'], Article::find(2)->topics->pluck('name')->all());
        $this->assertSame(['Alpha'], Topic::find(1)->articles->pluck('title')->all());
        $this->assertSame(['Alpha', 'Beta'], Topic::find(2)->articles->pluck('title')->all());
    }

    public function testExplicitKeysStillWork(): void
    {
        $author = Author::find(1);

        $this->assertSame('Ann writes', $author->explicitBio->line);
        $this->assertSame(['Alpha', 'Beta'], $author->explicitArticles->pluck('title')->all());
        $this->assertSame('Ann', Bio::find(1)->explicitAuthor->name);
        $this->assertSame(['php', 'sql'], Article::find(1)->explicitTopics->pluck('name')->all());
    }

    public function testOnlyTheForeignKeyCanBeGiven(): void
    {
        $this->assertSame('Ann writes', Author::find(2)->bioByWriter->line);
    }

    public function testEagerLoadingWorksWithDefaultedRelations(): void
    {
        $authors = Author::embed('bio')->embed('articles')->get();

        $this->assertSame('Ann writes', $authors[0]->bio->line);
        $this->assertSame(['Alpha', 'Beta'], $authors[0]->articles->pluck('title')->all());
        $this->assertSame(['Gamma'], $authors[1]->articles->pluck('title')->all());

        $articles = Article::embed('topics')->get();
        $this->assertSame(['php', 'sql'], $articles[0]->topics->pluck('name')->all());
    }

    public function testRelationsOfAnUnsavedModelMatchNothing(): void
    {
        // A row whose foreign key is NULL must not be picked up by a model without a key
        $this->pdo->exec("INSERT INTO articles (author_id, title) VALUES (NULL, 'Orphan')");
        $this->pdo->exec("INSERT INTO bios (author_id, line) VALUES (NULL, 'Orphan bio')");

        $author = new Author();

        $this->assertCount(0, $author->articles);
        $this->assertNull($author->bio);
        $this->assertSame(0, $author->articles()->count());
        $this->assertNull((new Article())->author);
    }

    public function testRelationsApplyAConditionAddedByTheRelatedModelsQueryMethod(): void
    {
        $this->pdo->exec("INSERT INTO articles (author_id, title) VALUES (1, 'Hidden')");

        $author = Author::find(1);

        // The plain relation sees all three articles of author 1
        $this->assertSame(['Alpha', 'Beta', 'Hidden'], $author->articles->pluck('title')->all());

        // VisibleArticle::query() adds title != 'Hidden', and the relation keeps it
        $this->assertSame(['Alpha', 'Beta'], $author->visibleArticles->pluck('title')->all());
        $this->assertSame(2, $author->visibleArticles()->count());
        $this->assertInstanceOf(LinkMany::class, $author->visibleArticles());
        $this->assertSame(['Alpha'], $author->visibleArticles()->where('title', 'Alpha')->get()->pluck('title')->all());
    }

    public function testRelationQueriesCanStillBeConstrained(): void
    {
        $this->assertSame(['Beta'], Author::find(1)->articles()->where('title', 'Beta')->get()->pluck('title')->all());
        $this->assertSame(1, Author::find(1)->articles()->where('title', 'Alpha')->count());
    }
}

class Author extends Model
{
    protected $table = 'authors';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['name'];

    public function bio(): LinkOne
    {
        return $this->linkOne(Bio::class);
    }

    public function articles(): LinkMany
    {
        return $this->linkMany(Article::class);
    }

    public function visibleArticles(): LinkMany
    {
        return $this->linkMany(VisibleArticle::class, 'author_id');
    }

    public function explicitBio(): LinkOne
    {
        return $this->linkOne(Bio::class, 'author_id', 'id');
    }

    public function explicitArticles(): LinkMany
    {
        return $this->linkMany(Article::class, 'author_id', 'id');
    }

    public function bioByWriter(): LinkOne
    {
        return $this->linkOne(Bio::class, 'writer_id');
    }
}

class Bio extends Model
{
    protected $table = 'bios';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['author_id', 'line'];

    public function author(): BindTo
    {
        return $this->bindTo(Author::class);
    }

    public function explicitAuthor(): BindTo
    {
        return $this->bindTo(Author::class, 'id', 'author_id');
    }
}

class Article extends Model
{
    protected $table = 'articles';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['author_id', 'title'];

    public function author(): BindTo
    {
        return $this->bindTo(Author::class);
    }

    public function topics(): BindToMany
    {
        return $this->bindToMany(Topic::class);
    }

    public function explicitTopics(): BindToMany
    {
        return $this->bindToMany(Topic::class, 'article_id', 'topic_id', 'article_topic');
    }
}

class Topic extends Model
{
    protected $table = 'topics';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['name'];

    public function articles(): BindToMany
    {
        return $this->bindToMany(Article::class);
    }
}

/**
 * Overrides query() to hide some rows from every query on the model.
 */
class VisibleArticle extends Model
{
    protected $table = 'articles';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['author_id', 'title'];

    public static function query(?string $connection = null): Builder
    {
        return parent::query($connection)->where('title', '!=', 'Hidden');
    }
}
