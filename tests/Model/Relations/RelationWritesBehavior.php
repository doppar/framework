<?php

namespace Tests\Unit\Model\RelationWrites;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Relations\BindTo;
use Phaseolies\Database\Entity\Relations\LinkMany;
use Phaseolies\Database\Entity\Relations\LinkOne;
use Phaseolies\Support\Collection;
use Tests\Support\Database\ModelQueryDriverTestCase;

/**
 * Records are created and saved through a relation, with the foreign key set
 * from the parent, and a child is pointed at its parent with associate().
 */
abstract class RelationWritesTest extends ModelQueryDriverTestCase
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
                ['name' => 'line', 'type' => 'string'],
            ],
            'articles' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'author_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'title', 'type' => 'string'],
            ],
            'notes' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'author_id', 'type' => 'integer', 'nullable' => true],
                ['name' => 'body', 'type' => 'string'],
            ],
        ];
    }

    protected function seedData(): array
    {
        return [
            'authors' => [['name' => 'Ann'], ['name' => 'Ben']],
            'bios' => [],
            'articles' => [
                ['author_id' => 1, 'title' => 'Alpha'],
                ['author_id' => 2, 'title' => 'Gamma'],
            ],
            'notes' => [],
        ];
    }

    private function rowCount(string $table, string $where = '1=1'): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    }

    public function testCreateThroughLinkManySetsTheForeignKey(): void
    {
        $author = Author::find(1);

        $article = $author->articles()->create(['title' => 'Beta']);

        $this->assertInstanceOf(Article::class, $article);
        $this->assertNotNull($article->id);
        $this->assertEquals(1, $article->author_id);
        $this->assertSame(1, $this->rowCount('articles', "author_id = 1 AND title = 'Beta'"));
        $this->assertSame(['Alpha', 'Beta'], $author->articles()->orderBy('id')->get()->pluck('title')->all());
    }

    public function testTheParentKeyWinsOverAForeignKeyInTheAttributes(): void
    {
        $article = Author::find(1)->articles()->create(['title' => 'Beta', 'author_id' => 2]);

        $this->assertEquals(1, $article->author_id);
        $this->assertSame(1, $this->rowCount('articles', "title = 'Beta' AND author_id = 1"));
    }

    public function testCreateThroughLinkOne(): void
    {
        $bio = Author::find(1)->bio()->create(['line' => 'Ann writes']);

        $this->assertInstanceOf(Bio::class, $bio);
        $this->assertSame(1, $this->rowCount('bios', "author_id = 1 AND line = 'Ann writes'"));
        $this->assertSame('Ann writes', Author::find(1)->bio->line);
    }

    public function testCreateManyReturnsACollectionOfSavedModels(): void
    {
        $articles = Author::find(2)->articles()->createMany([
            ['title' => 'Delta'],
            ['title' => 'Epsilon'],
        ]);

        $this->assertInstanceOf(Collection::class, $articles);
        $this->assertCount(2, $articles);
        $this->assertSame(['Delta', 'Epsilon'], $articles->pluck('title')->all());
        $this->assertSame(3, $this->rowCount('articles', 'author_id = 2'));
    }

    public function testStoreSavesANewModelAndMovesAnExistingOne(): void
    {
        $author = Author::find(1);

        $new = new Article();
        $new->title = 'Beta';

        $this->assertSame($new, $author->articles()->store($new));
        $this->assertSame(1, $this->rowCount('articles', "title = 'Beta' AND author_id = 1"));

        $gamma = Article::find(2); // belongs to author 2
        $author->articles()->store($gamma);

        $this->assertSame(1, $this->rowCount('articles', "title = 'Gamma' AND author_id = 1"));
    }

    public function testStoreManySavesEachModel(): void
    {
        $first = new Article();
        $first->title = 'One';
        $second = new Article();
        $second->title = 'Two';

        $saved = Author::find(1)->articles()->storeMany([$first, $second]);

        $this->assertSame([$first, $second], $saved);
        $this->assertSame(3, $this->rowCount('articles', 'author_id = 1'));
    }

    public function testStoreRejectsAModelOfTheWrongClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Author::find(1)->articles()->store(new Bio());
    }

    public function testWritingThroughARelationOfAnUnsavedModelThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('unsaved');

        (new Author())->articles()->create(['title' => 'Orphan']);
    }

    public function testTheForeignKeyMustBeCreatable(): void
    {
        try {
            Author::find(1)->notes()->create(['body' => 'secret']);
            $this->fail('Expected a LogicException');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('author_id', $e->getMessage());
            $this->assertStringContainsString('$creatable', $e->getMessage());
        }

        $this->assertSame(0, $this->rowCount('notes'));
    }

    public function testFirstOrCreateAndUpdateOrCreateStayInsideTheRelation(): void
    {
        $author = Author::find(1);

        $existing = $author->articles()->firstOrCreate(['title' => 'Alpha']);
        $this->assertEquals(1, $existing->id);
        $this->assertSame(1, $this->rowCount('articles', 'author_id = 1'));

        $created = $author->articles()->firstOrCreate(['title' => 'Beta']);
        $this->assertEquals(1, $created->author_id);

        // Gamma belongs to another author, so it is not found here and is created for author 1
        $author->articles()->firstOrCreate(['title' => 'Gamma']);
        $this->assertSame(1, $this->rowCount('articles', "title = 'Gamma' AND author_id = 1"));
        $this->assertSame(1, $this->rowCount('articles', "title = 'Gamma' AND author_id = 2"));

        $author->articles()->updateOrCreate(['title' => 'Alpha'], ['title' => 'Alpha 2']);
        $this->assertSame(1, $this->rowCount('articles', "title = 'Alpha 2' AND author_id = 1"));
    }

    public function testHooksRunForRelationWritesAndCanBeSkipped(): void
    {
        Article::$created = 0;
        $author = Author::find(1);

        $author->articles()->create(['title' => 'Beta']);
        $this->assertSame(1, Article::$created);

        $author->articles()->withoutHook()->create(['title' => 'Quiet']);
        $this->assertSame(1, Article::$created);

        $quiet = new Article();
        $quiet->title = 'Stored quietly';
        $author->articles()->withoutHook()->store($quiet);
        $this->assertSame(1, Article::$created);
    }

    public function testAssociateAndDissociateChangeTheChildUntilItIsSaved(): void
    {
        $article = Article::find(1); // author 1
        $ben = Author::find(2);

        $same = $article->author()->associate($ben);

        $this->assertSame($article, $same);
        $this->assertEquals(2, $article->author_id);
        $this->assertSame(1, $this->rowCount('articles', 'id = 1 AND author_id = 1'), 'nothing is saved yet');

        $article->save();
        $this->assertSame(1, $this->rowCount('articles', 'id = 1 AND author_id = 2'));
        $this->assertSame('Ben', Article::find(1)->author->name);

        $article->author()->associate(1); // a bare id works too
        $article->save();
        $this->assertSame(1, $this->rowCount('articles', 'id = 1 AND author_id = 1'));

        $article->author()->dissociate();
        $article->save();
        $this->assertSame(1, $this->rowCount('articles', 'id = 1 AND author_id IS NULL'));
        $this->assertNull(Article::find(1)->author);
    }

    public function testAssociateRejectsAnUnsavedParent(): void
    {
        $this->expectException(\LogicException::class);

        Article::find(1)->author()->associate(new Author());
    }

    public function testAssociateNeedsTheForeignKeyToBeCreatable(): void
    {
        $note = new Note();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('$creatable');

        $note->author()->associate(Author::find(1));
    }

    public function testRelationQueriesStillWorkNormally(): void
    {
        $author = Author::find(1);

        $this->assertInstanceOf(LinkMany::class, $author->articles());
        $this->assertInstanceOf(LinkOne::class, $author->bio());
        $this->assertInstanceOf(BindTo::class, Article::find(1)->author());
        $this->assertSame(['Alpha'], $author->articles()->get()->pluck('title')->all());
    }
}

class Author extends Model
{
    protected $table = 'authors';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['name'];

    public function articles(): LinkMany
    {
        return $this->linkMany(Article::class);
    }

    public function bio(): LinkOne
    {
        return $this->linkOne(Bio::class);
    }

    public function notes(): LinkMany
    {
        return $this->linkMany(Note::class);
    }
}

class Bio extends Model
{
    protected $table = 'bios';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['author_id', 'line'];
}

class Article extends Model
{
    public static int $created = 0;

    protected $table = 'articles';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['author_id', 'title'];

    protected $hooks = [
        'after_created' => [self::class, 'countCreated'],
    ];

    public static function countCreated(Model $model): void
    {
        self::$created++;
    }

    public function author(): BindTo
    {
        return $this->bindTo(Author::class);
    }
}

/**
 * The foreign key is left out of $creatable on purpose.
 */
class Note extends Model
{
    protected $table = 'notes';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['body'];

    public function author(): BindTo
    {
        return $this->bindTo(Author::class);
    }
}
