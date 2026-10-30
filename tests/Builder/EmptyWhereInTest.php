<?php

namespace Tests\Unit\Builder;

use PDO;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder as ModelBuilder;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Query\Builder as QueryBuilder;
use Phaseolies\DI\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\MockContainer;

class EmptyWhereInItem extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['name', 'cat', 'note', 'qty'];
}

/**
 * An empty IN list matches nothing and an empty NOT IN list excludes nothing.
 *
 * The empty list used to become `column = 'NULL'`, which matched rows holding
 * the text "NULL" (and, on MySQL, integer columns equal to 0).
 */
class EmptyWhereInTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            cat INTEGER NULL,
            note TEXT NULL,
            qty INTEGER DEFAULT 0
        )');

        $this->pdo->exec('CREATE TABLE tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER,
            note TEXT NULL
        )');

        $this->pdo->exec("INSERT INTO tags (item_id, note) VALUES (1, 't1'), (2, 't2'), (3, 't3'), (5, 't5')");

        $this->pdo->exec("INSERT INTO items (name, cat, note, qty) VALUES
            ('alpha', 1, 'x', 5),
            ('beta', 2, NULL, 10),
            ('gamma', NULL, 'NULL', 15),
            ('delta', NULL, NULL, 0),
            ('epsilon', 0, 'null', 25)");

        foreach (['connections' => ['default' => $this->pdo, 'sqlite' => $this->pdo], 'transactions' => []] as $property => $value) {
            (new \ReflectionProperty(Database::class, $property))->setValue(null, $value);
        }

        Container::setInstance(new MockContainer());
    }

    protected function tearDown(): void
    {
        foreach (['connections', 'transactions'] as $property) {
            (new \ReflectionProperty(Database::class, $property))->setValue(null, []);
        }

        Container::forgetInstance();
    }

    /**
     * Both builders implement whereIn(), so every case runs against each.
     *
     * @return array<string, array{0: string}>
     */
    public static function builders(): array
    {
        return [
            'query builder' => ['query'],
            'model builder' => ['model'],
        ];
    }

    private function builder(string $kind): QueryBuilder|ModelBuilder
    {
        return $kind === 'query'
            ? new QueryBuilder($this->pdo, 'items')
            : EmptyWhereInItem::query();
    }

    /**
     * The "name" of every row the builder returns, sorted.
     *
     * @return array<int, string>
     */
    private function names(QueryBuilder|ModelBuilder $builder): array
    {
        $names = [];

        foreach ($builder->get()->all() as $row) {
            $names[] = (string) ($row instanceof Model ? $row->getAttributes()['name'] : $row['name']);
        }

        sort($names);

        return $names;
    }

    private function rowCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
    }

    // ---- whereIn() with an empty list ------------------------------------

    #[DataProvider('builders')]
    public function testEmptyWhereInMatchesNothingOnATextColumn(string $kind): void
    {
        $this->assertSame([], $this->names($this->builder($kind)->whereIn('note', [])));
    }

    #[DataProvider('builders')]
    public function testEmptyWhereInDoesNotMatchTheTextNullRow(string $kind): void
    {
        $this->assertSame([], $this->names($this->builder($kind)->whereIn('note', [])), 'row "gamma" holds the text NULL');
        $this->assertSame([], $this->names($this->builder($kind)->whereIn('name', [])));
    }

    #[DataProvider('builders')]
    public function testEmptyWhereInDoesNotMatchRealNullRows(string $kind): void
    {
        $this->assertSame([], $this->names($this->builder($kind)->whereIn('cat', [])));
        $this->assertSame([], $this->names($this->builder($kind)->whereIn('note', [])));
    }

    #[DataProvider('builders')]
    public function testEmptyWhereInDoesNotMatchZeroOnAnIntegerColumn(string $kind): void
    {
        $this->assertSame([], $this->names($this->builder($kind)->whereIn('qty', [])));
        $this->assertSame([], $this->names($this->builder($kind)->whereIn('cat', [])));
    }

    #[DataProvider('builders')]
    public function testEmptyWhereInCountsZero(string $kind): void
    {
        $this->assertSame(0, $this->builder($kind)->whereIn('note', [])->count());
    }

    #[DataProvider('builders')]
    public function testEmptyWhereInDoesNotExist(string $kind): void
    {
        $this->assertFalse($this->builder($kind)->whereIn('note', [])->exists());
    }

    #[DataProvider('builders')]
    public function testEmptyWhereInFirstIsNull(string $kind): void
    {
        $this->assertNull($this->builder($kind)->whereIn('note', [])->first());
    }

    #[DataProvider('builders')]
    public function testEmptyWhereInCompilesToAnAlwaysFalseCondition(string $kind): void
    {
        $sql = $this->builder($kind)->whereIn('note', [])->toSql();

        $this->assertMatchesRegularExpression('/WHERE 1\s*=\s*0$/', $sql);
        $this->assertStringNotContainsString("'NULL'", $sql);
        $this->assertStringNotContainsString('note =', $sql);
    }

    #[DataProvider('builders')]
    public function testEmptyWhereInWorksOnAQualifiedColumn(string $kind): void
    {
        $this->assertSame([], $this->names($this->builder($kind)->whereIn('items.note', [])));
    }

    #[DataProvider('builders')]
    public function testEmptyWhereInStaysEmptyWhenCombinedWithOtherConditions(string $kind): void
    {
        $this->assertSame([], $this->names($this->builder($kind)->where('qty', '>', 0)->whereIn('note', [])));
        $this->assertSame([], $this->names($this->builder($kind)->whereIn('note', [])->where('id', 1)));
    }

    // ---- writes must not touch rows --------------------------------------

    #[DataProvider('builders')]
    public function testDeleteWithAnEmptyWhereInDeletesNothing(string $kind): void
    {
        $this->builder($kind)->whereIn('note', [])->delete();

        $this->assertSame(5, $this->rowCount());
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM items WHERE note = 'NULL'")->fetchColumn());
    }

    #[DataProvider('builders')]
    public function testUpdateWithAnEmptyWhereInUpdatesNothing(string $kind): void
    {
        $this->builder($kind)->whereIn('note', [])->update(['qty' => 999]);

        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM items WHERE qty = 999')->fetchColumn());
    }

    // ---- orWhereIn() with an empty list ----------------------------------

    #[DataProvider('builders')]
    public function testEmptyOrWhereInAddsNothingToTheOtherConditions(string $kind): void
    {
        $this->assertSame(['alpha'], $this->names($this->builder($kind)->where('id', 1)->orWhereIn('note', [])));
    }

    #[DataProvider('builders')]
    public function testEmptyOrWhereInDoesNotAddTheTextNullRow(string $kind): void
    {
        $names = $this->names($this->builder($kind)->where('id', 1)->orWhereIn('note', []));

        $this->assertNotContains('gamma', $names);
    }

    #[DataProvider('builders')]
    public function testEmptyOrWhereInAloneMatchesNothing(string $kind): void
    {
        $this->assertSame([], $this->names($this->builder($kind)->orWhereIn('note', [])));
    }

    #[DataProvider('builders')]
    public function testEmptyOrWhereInKeepsTheOtherConditionsRows(string $kind): void
    {
        $this->assertSame(
            ['alpha', 'beta', 'epsilon'],
            $this->names($this->builder($kind)->where('qty', '>', 0)->where('qty', '!=', 15)->orWhereIn('note', []))
        );
    }

    #[DataProvider('builders')]
    public function testEmptyOrWhereInCompilesToAnAlwaysFalseCondition(string $kind): void
    {
        $sql = $this->builder($kind)->where('id', 1)->orWhereIn('note', [])->toSql();

        $this->assertMatchesRegularExpression('/OR 1\s*=\s*0$/', $sql);
        $this->assertStringNotContainsString("'NULL'", $sql);
    }

    // ---- nested groups ----------------------------------------------------

    #[DataProvider('builders')]
    public function testEmptyWhereInInsideANestedGroup(string $kind): void
    {
        $query = $this->builder($kind)->where(function ($q) {
            $q->whereIn('note', [])->orWhere('id', 1);
        });

        $this->assertSame(['alpha'], $this->names($query));
    }

    // ---- unchanged behaviour ---------------------------------------------

    public function testEmptyWhereNotInExcludesNothing(): void
    {
        // Only the model builder has whereNotIn(); the plain query builder does not.
        $this->assertCount(5, $this->names($this->builder('model')->whereNotIn('note', [])));
    }

    #[DataProvider('builders')]
    public function testWhereInWithValuesStillFilters(string $kind): void
    {
        $this->assertSame(['alpha'], $this->names($this->builder($kind)->whereIn('note', ['x'])));
        $this->assertSame(['alpha', 'beta'], $this->names($this->builder($kind)->whereIn('cat', [1, 2])));
    }

    #[DataProvider('builders')]
    public function testWhereInStillMatchesTheLiteralTextNullWhenAskedFor(string $kind): void
    {
        $this->assertSame(['gamma'], $this->names($this->builder($kind)->whereIn('note', ['NULL'])));
    }

    #[DataProvider('builders')]
    public function testOrWhereInWithValuesStillWorks(string $kind): void
    {
        $this->assertSame(['alpha', 'beta'], $this->names($this->builder($kind)->where('id', 1)->orWhereIn('cat', [2])));
    }

    #[DataProvider('builders')]
    public function testWhereNullStillFindsRealNullRows(string $kind): void
    {
        $this->assertSame(['beta', 'delta'], $this->names($this->builder($kind)->whereNull('note')));
    }

    #[DataProvider('builders')]
    public function testChainedEmptyAndNonEmptyWhereIn(string $kind): void
    {
        $this->assertSame([], $this->names($this->builder($kind)->whereIn('cat', [1, 2])->whereIn('note', [])));
    }

    #[DataProvider('builders')]
    public function testTheBuilderCanBeUsedAgainAfterAnEmptyWhereIn(string $kind): void
    {
        $empty = $this->builder($kind)->whereIn('note', []);
        $this->assertSame([], $this->names($empty));

        $this->assertCount(5, $this->names($this->builder($kind)));
    }

    // ---- columns are qualified, so joins and aliases work ----------------

    private function joined(string $kind): QueryBuilder|ModelBuilder
    {
        // items and tags both have "id" and "note" columns.
        return $this->builder($kind)->join('tags', 'items.id', '=', 'tags.item_id');
    }

    #[DataProvider('builders')]
    public function testWhereInOnAJoinIsNotAmbiguous(string $kind): void
    {
        $this->assertSame(['beta'], $this->names($this->joined($kind)->whereIn('id', [2])));
    }

    #[DataProvider('builders')]
    public function testOrWhereInOnAJoinIsNotAmbiguous(string $kind): void
    {
        $this->assertSame(
            ['alpha', 'beta'],
            $this->names($this->joined($kind)->where('items.id', 1)->orWhereIn('id', [2]))
        );
    }

    #[DataProvider('builders')]
    public function testOrWhereInOnAJoinWithAnotherSharedColumn(string $kind): void
    {
        $this->assertSame(
            ['alpha', 'gamma'],
            $this->names($this->joined($kind)->where('items.id', 1)->orWhereIn('note', ['NULL']))
        );
    }

    #[DataProvider('builders')]
    public function testWhereNotInOnAJoinIsNotAmbiguous(string $kind): void
    {
        $this->assertSame(['beta', 'epsilon'], $this->names($this->joined($kind)->whereNotIn('id', [1, 3])));
    }

    #[DataProvider('builders')]
    public function testOrWhereNotInOnAJoinIsNotAmbiguous(string $kind): void
    {
        // items.id = 1 OR items.id NOT IN (2), over the joined ids 1, 2, 3 and 5.
        $this->assertSame(
            ['alpha', 'epsilon', 'gamma'],
            $this->names($this->joined($kind)->where('items.id', 1)->orWhereNotIn('id', [2]))
        );
    }

    #[DataProvider('builders')]
    public function testOrWhereInQualifiesABareColumn(string $kind): void
    {
        $this->assertStringContainsString('items.id IN (?)', $this->builder($kind)->orWhereIn('id', [1])->toSql());
    }

    #[DataProvider('builders')]
    public function testAnAlreadyQualifiedColumnIsLeftAlone(string $kind): void
    {
        $sql = $this->builder($kind)->whereIn('tags.id', [1])->orWhereIn('items.id', [2])->toSql();

        $this->assertStringContainsString('tags.id IN (?)', $sql);
        $this->assertStringContainsString('items.id IN (?)', $sql);
        $this->assertStringNotContainsString('items.tags.id', $sql);
        $this->assertStringNotContainsString('items.items.id', $sql);
    }

    public function testWhereInUsesTheAliasOfAnAliasedTable(): void
    {
        $query = (new QueryBuilder($this->pdo, 'items as i'))->whereIn('id', [1, 2]);

        $this->assertStringContainsString('i.id IN (?,?)', $query->toSql());
        $this->assertSame(['alpha', 'beta'], $this->names($query));
    }

    public function testAllInConditionsUseTheAliasOfAnAliasedTable(): void
    {
        $query = (new QueryBuilder($this->pdo, 'items as i'))
            ->whereIn('id', [1])
            ->orWhereIn('id', [2])
            ->whereNotIn('id', [3])
            ->orWhereNotIn('id', [4]);

        $this->assertStringNotContainsString('items as i.', $query->toSql());
        $this->assertStringContainsString('i.id IN (?)', $query->toSql());
        $this->assertStringContainsString('i.id NOT IN (?)', $query->toSql());
        $this->assertNotEmpty($this->names($query));
    }

    public function testTheAliasWithoutTheAsKeywordIsUsedToo(): void
    {
        $query = (new QueryBuilder($this->pdo, 'items i'))->whereIn('id', [3]);

        $this->assertStringContainsString('i.id IN (?)', $query->toSql());
        $this->assertSame(['gamma'], $this->names($query));
    }

    public function testEmptyListsWorkOnAnAliasedTable(): void
    {
        $this->assertSame([], $this->names((new QueryBuilder($this->pdo, 'items as i'))->whereIn('note', [])));
        $this->assertCount(5, $this->names((new QueryBuilder($this->pdo, 'items as i'))->whereNotIn('note', [])));
    }

    public function testTheModelBuilderUsesTheAliasToo(): void
    {
        $query = EmptyWhereInItem::query()->from('items as i')->whereIn('id', [1, 2])->orWhereIn('id', [3]);

        $this->assertStringContainsString('i.id IN (?,?)', $query->toSql());
        $this->assertSame(['alpha', 'beta', 'gamma'], $this->names($query));
    }

    // ---- the plain query builder has whereNotIn / orWhereNotIn ----------

    public function testQueryBuilderWhereNotInExcludesTheListedValues(): void
    {
        $query = (new QueryBuilder($this->pdo, 'items'))->whereNotIn('id', [1, 2, 3]);

        $this->assertSame(['delta', 'epsilon'], $this->names($query));
        $this->assertStringContainsString('items.id NOT IN (?,?,?)', $query->toSql());
    }

    public function testQueryBuilderWhereNotInWithAnEmptyListExcludesNothing(): void
    {
        $query = (new QueryBuilder($this->pdo, 'items'))->whereNotIn('id', []);

        $this->assertCount(5, $this->names($query));
        $this->assertMatchesRegularExpression('/WHERE 1\s*=\s*1$/', $query->toSql());
    }

    public function testQueryBuilderOrWhereNotInAddsItsRows(): void
    {
        $query = (new QueryBuilder($this->pdo, 'items'))->where('id', 1)->orWhereNotIn('id', [2, 3, 4, 5]);

        $this->assertSame(['alpha'], $this->names($query));

        $query = (new QueryBuilder($this->pdo, 'items'))->where('id', 1)->orWhereNotIn('id', [1, 2, 3]);

        $this->assertSame(['alpha', 'delta', 'epsilon'], $this->names($query));
    }

    public function testQueryBuilderOrWhereNotInWithAnEmptyListMatchesEverything(): void
    {
        $query = (new QueryBuilder($this->pdo, 'items'))->where('id', 1)->orWhereNotIn('id', []);

        $this->assertCount(5, $this->names($query));
    }

    public function testQueryBuilderNotInConditionsCanBeChained(): void
    {
        $query = (new QueryBuilder($this->pdo, 'items'))->whereNotIn('id', [1])->whereNotIn('cat', [2]);

        $this->assertSame(['epsilon'], $this->names($query));
    }

    public function testQueryBuilderNotInRejectsAnUnsafeColumnName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new QueryBuilder($this->pdo, 'items'))->whereNotIn('id); DROP TABLE items; --', [1]);
    }

    public function testQueryBuilderNotInDoesNotTouchOtherRows(): void
    {
        (new QueryBuilder($this->pdo, 'items'))->whereNotIn('id', [1])->delete();

        $this->assertSame(1, $this->rowCount());
    }

    // ---- value lists ------------------------------------------------------

    #[DataProvider('builders')]
    public function testWhereInAcceptsAnAssociativeList(string $kind): void
    {
        $this->assertSame(['alpha', 'beta'], $this->names($this->builder($kind)->whereIn('id', ['a' => 1, 'b' => 2])));
    }

    #[DataProvider('builders')]
    public function testOrWhereInAcceptsAnAssociativeList(string $kind): void
    {
        $this->assertSame(
            ['alpha', 'beta'],
            $this->names($this->builder($kind)->where('id', 1)->orWhereIn('id', ['x' => 2]))
        );
    }

    #[DataProvider('builders')]
    public function testWhereNotInAcceptsAnAssociativeList(string $kind): void
    {
        $this->assertSame(['delta', 'epsilon', 'gamma'], $this->names($this->builder($kind)->whereNotIn('id', ['a' => 1, 'b' => 2])));
    }

    #[DataProvider('builders')]
    public function testWhereInKeepsTheOrderIndependentOfListOrder(string $kind): void
    {
        $this->assertSame(
            $this->names($this->builder($kind)->whereIn('id', [3, 1, 2])),
            $this->names($this->builder($kind)->whereIn('id', [1, 2, 3]))
        );
    }

    #[DataProvider('builders')]
    public function testWhereInWithASingleValue(string $kind): void
    {
        $this->assertSame(['gamma'], $this->names($this->builder($kind)->whereIn('id', [3])));
    }

    #[DataProvider('builders')]
    public function testWhereInWithValuesThatDoNotExist(string $kind): void
    {
        $this->assertSame([], $this->names($this->builder($kind)->whereIn('id', [100, 200])));
    }
}
