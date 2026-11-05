<?php

namespace Tests\Unit;

use ArrayAccess;
use Phaseolies\Support\Collection;
use Phaseolies\Support\HigherOrderCollectionProxy;
use PHPUnit\Framework\TestCase;

class CollectionFeaturesTest extends TestCase
{
    private function collect(array $items = []): Collection
    {
        return new Collection(\stdClass::class, $items);
    }

    private function users(): Collection
    {
        return $this->collect([
            ['id' => 1, 'name' => 'ada', 'age' => 30, 'date' => '2025-03-01', 'count' => 5, 'profile' => ['city' => 'Paris']],
            ['id' => 2, 'name' => 'bob', 'age' => 25, 'date' => '2025-01-01', 'count' => 9, 'profile' => ['city' => 'Oslo']],
            ['id' => 3, 'name' => 'cy', 'age' => 30, 'date' => '2025-02-01', 'count' => 7, 'profile' => ['city' => 'Rome']],
        ]);
    }

    private function objects(): Collection
    {
        $make = fn(string $name, int $age, bool $active) => new class($name, $age, $active) {
            public array $saved = [];

            public function __construct(public string $name, public int $age, public bool $active)
            {
            }

            public function isAdult(): bool
            {
                return $this->age >= 18;
            }

            public function save(string $note = ''): void
            {
                $this->saved[] = $note;
            }
        };

        return $this->collect([$make('ada', 30, true), $make('bob', 12, false), $make('cy', 40, true)]);
    }

    // ----- fixes ------------------------------------------------------------------------

    public function testAppendingWithBracketsAndAddActuallyAppends(): void
    {
        $c = $this->collect([1]);
        $c[] = 2;
        $c[] = 3;

        $this->assertSame([1, 2, 3], $c->all());

        $this->assertTrue($c->add(4));
        $this->assertTrue($c->add(5));
        $this->assertSame([1, 2, 3, 4, 5], $c->all());
    }

    public function testSettingAKeyStillWorks(): void
    {
        $c = $this->collect(['a' => 1]);
        $c['b'] = 2;

        $this->assertSame(['a' => 1, 'b' => 2], $c->all());
    }

    public function testFirstAcceptsATestAndADefault(): void
    {
        $c = $this->collect([1, 2, 3, 4]);

        $this->assertSame(1, $c->first());
        $this->assertSame(3, $c->first(fn($n) => $n > 2));
        $this->assertNull($c->first(fn($n) => $n > 9));
        $this->assertSame('none', $c->first(fn($n) => $n > 9, 'none'));
        $this->assertSame('lazy', $c->first(fn($n) => $n > 9, fn() => 'lazy'));
        $this->assertSame('empty', $this->collect()->first(null, 'empty'));
    }

    public function testFirstPassesTheKeyToTheTest(): void
    {
        $c = $this->collect(['a' => 1, 'b' => 2]);

        $this->assertSame(2, $c->first(fn($v, $k) => $k === 'b'));
    }

    public function testLastAcceptsATestAndADefault(): void
    {
        $c = $this->collect([1, 2, 3, 4]);

        $this->assertSame(4, $c->last());
        $this->assertSame(3, $c->last(fn($n) => $n < 4));
        $this->assertSame('none', $c->last(fn($n) => $n > 9, 'none'));
        $this->assertNull($this->collect()->last());
    }

    public function testFilterWithoutACallbackDropsFalsyItems(): void
    {
        $this->assertSame([1, 'a', true], $this->collect([0, 1, null, 'a', false, '', [], true])->filter()->all());
    }

    public function testFilterWithACallbackIsUnchanged(): void
    {
        $this->assertSame([2, 4], $this->collect([1, 2, 3, 4])->filter(fn($n) => $n % 2 === 0)->all());
    }

    public function testUniqueTellsFloatsApart(): void
    {
        $deprecations = [];
        set_error_handler(function ($no, $message) use (&$deprecations) {
            $deprecations[] = $message;

            return true;
        });

        try {
            $result = $this->collect([1.5, 1.2, 1.5, 1.2, 2.0])->unique()->all();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([1.5, 1.2, 2.0], $result);
        $this->assertSame([], $deprecations, 'no "implicit conversion from float" deprecations');
    }

    public function testUniqueStillTreatsLooselyEqualScalarsAsTheSame(): void
    {
        $this->assertSame(['1'], $this->collect(['1', 1, true])->unique()->all());
        $this->assertSame([1, '1'], $this->collect([1, '1'])->unique(null, true)->all());
    }

    public function testAStringIsAColumnNameNeverAFunction(): void
    {
        $users = $this->users();

        $this->assertSame([1, 2, 3], array_map(fn($g) => $g[0]['id'], array_values($users->groupBy('date'))));
        $this->assertSame(['2025-03-01', '2025-01-01', '2025-02-01'], array_keys($users->keyBy('date')));
        $this->assertSame(['bob', 'cy', 'ada'], $users->sortBy('date')->pluck('name')->all());
        $this->assertSame(['ada', 'cy', 'bob'], $users->sortBy('count')->pluck('name')->all());
        $this->assertSame(['bob', 'cy', 'ada'], $users->sortByDesc('count')->pluck('name')->all());
    }

    public function testACallableValueIsStillUsedAsACallback(): void
    {
        $this->assertSame([1, 2, 3], array_keys($this->users()->keyBy(fn($u) => $u['id'])));
    }

    public function testTheParentCollectionMethodsThatCrashedNowWork(): void
    {
        $c = $this->collect([1]);

        $this->assertSame(\stdClass::class, $c->getType());
        $this->assertSame([1, 2, 3], $c->merge($this->collect([2]), [3])->all());
    }

    public function testMergeFollowsArrayMergeRules(): void
    {
        $merged = $this->collect(['a' => 1, 5 => 'x'])->merge(['a' => 2, 9 => 'y'], new \ArrayIterator([7 => 'z']));

        $this->assertSame(['a' => 2, 0 => 'x', 1 => 'y', 2 => 'z'], $merged->all());
        $this->assertSame(\stdClass::class, $merged->getModel());
    }

    public function testConcatAppendsValues(): void
    {
        $this->assertSame([1, 2, 3, 4], $this->collect([1, 2])->concat([3, 4])->all());
        $this->assertSame([1, 2, 3], $this->collect([1, 2])->concat(new \ArrayIterator(['k' => 3]))->all());
    }

    // ----- dot notation -----------------------------------------------------------------

    public function testPluckUnderstandsDotNotation(): void
    {
        $this->assertSame(['Paris', 'Oslo', 'Rome'], $this->users()->pluck('profile.city')->all());
        $this->assertSame(['ada' => 'Paris', 'bob' => 'Oslo', 'cy' => 'Rome'], $this->users()->pluck('profile.city', 'name')->all());
    }

    public function testDotNotationReachesIntoObjectsAndArrayAccess(): void
    {
        $nested = new class implements ArrayAccess {
            private array $data = ['inner' => 'value'];

            public function offsetExists(mixed $o): bool
            {
                return isset($this->data[$o]);
            }

            public function offsetGet(mixed $o): mixed
            {
                return $this->data[$o];
            }

            public function offsetSet(mixed $o, mixed $v): void
            {
            }

            public function offsetUnset(mixed $o): void
            {
            }
        };
        $object = (object) ['user' => (object) ['address' => ['zip' => '75001']], 'box' => $nested];

        $c = $this->collect([$object]);

        $this->assertSame(['75001'], $c->pluck('user.address.zip')->all());
        $this->assertSame(['value'], $c->pluck('box.inner')->all());
        $this->assertSame([null], $c->pluck('user.missing.deeper')->all());
    }

    public function testAKeyThatReallyContainsADotIsTriedFirst(): void
    {
        $c = $this->collect([['a.b' => 'direct', 'a' => ['b' => 'nested']]]);

        $this->assertSame(['direct'], $c->pluck('a.b')->all());
    }

    public function testSortGroupUniqueAndDuplicatesUseDotNotation(): void
    {
        $users = $this->users();

        $this->assertSame(['bob', 'ada', 'cy'], $users->sortBy('profile.city')->pluck('name')->all());
        $this->assertSame(['Paris', 'Oslo', 'Rome'], array_keys($users->groupBy('profile.city')));
        $this->assertCount(3, $users->unique('profile.city'));
        $this->assertCount(1, $users->duplicates('age'));
    }

    // ----- sorting by several keys ------------------------------------------------------

    public function testSortByManyKeys(): void
    {
        $this->assertSame(['bob', 'ada', 'cy'], $this->users()->sortBy(['age', 'name'])->pluck('name')->all());
    }

    public function testSortByManyKeysWithDirections(): void
    {
        $this->assertSame(['cy', 'ada', 'bob'], $this->users()->sortBy([['age', 'desc'], ['name', 'desc']])->pluck('name')->all());
        $this->assertSame(['ada', 'cy', 'bob'], $this->users()->sortBy([['age', 'desc'], ['name', 'asc']])->pluck('name')->all());
        $this->assertSame(['cy', 'ada', 'bob'], $this->users()->sortBy([['age', true], ['name', true]])->pluck('name')->all());
    }

    public function testSortByManyAcceptsCallbacksAndADefaultDirection(): void
    {
        $users = $this->users();

        // no direction named, so both criteria use the default: descending
        $this->assertSame(['cy', 'ada', 'bob'], $users->sortBy([fn($u) => $u['age'], 'name'], SORT_REGULAR, true)->pluck('name')->all());
        $this->assertSame(['bob', 'ada', 'cy'], $users->sortBy([fn($u) => $u['age'], fn($u) => $u['name']])->pluck('name')->all());
    }

    public function testSortByManyIsStableAndRespectsSortFlags(): void
    {
        $c = $this->collect([['n' => 'b10', 'g' => 1], ['n' => 'B2', 'g' => 1], ['n' => 'a1', 'g' => 0]]);

        $this->assertSame(['a1', 'b10', 'B2'], $c->sortBy(['g', 'n'], SORT_STRING | SORT_FLAG_CASE)->pluck('n')->all());
        $this->assertSame(['a1', 'B2', 'b10'], $c->sortBy(['g', 'n'], SORT_NATURAL | SORT_FLAG_CASE)->pluck('n')->all());

        $ties = $this->collect([['k' => 1, 'v' => 'first'], ['k' => 1, 'v' => 'second']]);
        $this->assertSame(['first', 'second'], $ties->sortBy(['k'])->pluck('v')->all());
    }

    public function testSortByAnArrayCallableIsStillACallback(): void
    {
        $sorter = new class {
            public function key(array $u): int
            {
                return -$u['age'];
            }
        };

        $this->assertSame(['ada', 'cy', 'bob'], $this->users()->sortBy([$sorter, 'key'])->pluck('name')->all());
    }

    // ----- new methods ------------------------------------------------------------------

    public function testReject(): void
    {
        $this->assertSame([1, 3], $this->collect([1, 2, 3, 4])->reject(fn($n) => $n % 2 === 0)->all());
    }

    public function testEveryAndSome(): void
    {
        $c = $this->collect([2, 4, 6]);

        $this->assertTrue($c->every(fn($n) => $n % 2 === 0));
        $this->assertFalse($c->every(fn($n) => $n > 2));
        $this->assertTrue($c->some(fn($n) => $n > 5));
        $this->assertFalse($c->some(fn($n) => $n > 6));
        $this->assertTrue($this->collect()->every(fn() => false));
        $this->assertFalse($this->collect()->some(fn() => true));
    }

    public function testReverseKeysOnlyExcept(): void
    {
        $this->assertSame([3, 2, 1], $this->collect([1, 2, 3])->reverse()->all());
        $this->assertSame(['a', 'b'], $this->collect(['a' => 1, 'b' => 2])->keys()->all());
        $this->assertSame(['a' => 1, 'c' => 3], $this->collect(['a' => 1, 'b' => 2, 'c' => 3])->only(['a', 'c'])->all());
        $this->assertSame(['b' => 2], $this->collect(['a' => 1, 'b' => 2, 'c' => 3])->except(['a', 'c'])->all());
    }

    public function testFirstWhere(): void
    {
        $users = $this->users();

        $this->assertSame('ada', $users->firstWhere('age', 30)['name']);
        $this->assertSame('bob', $users->firstWhere('age', '<', 30)['name']);
        $this->assertSame('bob', $users->firstWhere('count', '>=', 7)['name']);
        $this->assertSame('ada', $users->firstWhere('name', '===', 'ada')['name']);
        $this->assertSame('bob', $users->firstWhere('name', '!=', 'ada')['name']);
        $this->assertSame('ada', $users->firstWhere('id')['name'], 'one argument means truthy');
        $this->assertNull($users->firstWhere('age', 99));
        $this->assertSame('Oslo', $users->firstWhere('profile.city', 'Oslo')['profile']['city']);
    }

    public function testFirstWhereRejectsAnUnknownOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown comparison operator [~]');

        $this->users()->firstWhere('age', '~', 30);
    }

    public function testWhereInWhereNotInAndNullChecks(): void
    {
        $users = $this->users();
        $rows = $this->collect([['v' => 1], ['v' => null], ['v' => '1'], []]);

        $this->assertSame(['ada', 'cy'], $users->whereIn('age', [30])->pluck('name')->all());
        $this->assertSame(['bob'], $users->whereNotIn('age', [30])->pluck('name')->all());
        $this->assertCount(2, $rows->whereIn('v', [1]), 'loose by default');
        $this->assertCount(1, $rows->whereIn('v', [1], true));
        $this->assertCount(2, $rows->whereNotNull('v'));
        $this->assertCount(2, $rows->whereNull('v'));
        $this->assertSame(['ada', 'bob'], $users->whereIn('profile.city', new \ArrayIterator(['Paris', 'Oslo']))->pluck('name')->all());
    }

    public function testCountBy(): void
    {
        $this->assertSame(['a' => 2, 'b' => 1], $this->collect(['a', 'b', 'a'])->countBy());
        $this->assertSame([30 => 2, 25 => 1], $this->users()->countBy('age'));
        $this->assertSame(['odd' => 2, 'even' => 1], $this->collect([1, 2, 3])->countBy(fn($n) => $n % 2 ? 'odd' : 'even'));
        $this->assertSame([1 => 2, 0 => 1], $this->collect([true, false, true])->countBy());
    }

    public function testImplode(): void
    {
        $this->assertSame('1, 2, 3', $this->collect([1, 2, 3])->implode(', '));
        $this->assertSame('ada|bob|cy', $this->users()->implode('|', 'name'));
        $this->assertSame('', $this->collect()->implode(','));
    }

    public function testFlatMapAndCollapse(): void
    {
        $this->assertSame([1, 2, 3, 4], $this->collect([[1, 2], [3], [4]])->collapse()->all());
        $this->assertSame([1, 2, 3, 4], $this->collect([$this->collect([1, 2]), [3], 4])->collapse()->all());
        $this->assertSame(['a', 'b', 'c'], $this->collect(['ab', 'c'])->flatMap(fn($s) => str_split($s))->all());
    }

    public function testSkipAndSlice(): void
    {
        $c = $this->collect([1, 2, 3, 4, 5]);

        $this->assertSame([3, 4, 5], $c->skip(2)->all());
        $this->assertSame([], $c->skip(9)->all());
        $this->assertSame([1, 2, 3, 4, 5], $c->skip(-3)->all());
        $this->assertSame([2, 3], $c->slice(1, 2)->all());
        $this->assertSame([4, 5], $c->slice(3)->all());
        $this->assertSame([4, 5], $c->slice(-2)->all());
    }

    public function testSearch(): void
    {
        $c = $this->collect(['a' => 1, 'b' => '2', 'c' => 3]);

        $this->assertSame('c', $c->search(3));
        $this->assertSame('b', $c->search(2));
        $this->assertFalse($c->search(2, true));
        $this->assertSame('c', $c->search(fn($v, $k) => $v > 2));
        $this->assertFalse($c->search(99));
        $this->assertSame('b', $this->collect(['a' => 'strtoupper', 'b' => 'x'])->search('x'), 'a string is a value, not a callback');
    }

    public function testMedian(): void
    {
        $this->assertSame(3, $this->collect([5, 1, 3])->median());
        $this->assertSame(2.5, $this->collect([1, 2, 3, 4])->median());
        $this->assertSame(2, $this->collect([1, null, 3, null, 2])->median());
        $this->assertNull($this->collect()->median());
        $this->assertSame(30, $this->users()->median('age'));
    }

    public function testWhenAndUnless(): void
    {
        $c = $this->collect([1, 2]);

        $this->assertSame([1, 2, 3], $c->when(true, fn($c) => $c->concat([3]))->all());
        $this->assertSame([1, 2], $c->when(false, fn($c) => $c->concat([3]))->all());
        $this->assertSame([9], $c->when(false, fn($c) => $c, fn($c) => $this->collect([9]))->all());
        $this->assertSame([1, 2, 7], $c->when(fn($c) => $c->count() === 2, fn($c) => $c->concat([7]))->all());
        $this->assertSame($c, $c->when(true, fn($c) => null), 'a callback that returns nothing leaves the collection');

        $this->assertSame([1, 2, 5], $c->unless(false, fn($c) => $c->concat([5]))->all());
        $this->assertSame([1, 2], $c->unless(true, fn($c) => $c->concat([5]))->all());
        $this->assertSame([8], $c->unless(true, fn($c) => $c, fn($c) => $this->collect([8]))->all());
    }

    // ----- higher-order access ----------------------------------------------------------

    public function testMapAndEachProxiesStillWork(): void
    {
        $people = $this->objects();

        $this->assertSame(['ada', 'bob', 'cy'], $people->map->name->all());
        $this->assertInstanceOf(HigherOrderCollectionProxy::class, $people->map);

        $people->each->save('hello');
        $this->assertSame(['hello'], $people->first()->saved);
        $this->assertSame(['hello'], $people->last()->saved);

        // each() skips items that do not have the method
        $mixed = $this->collect([$people->first(), 5, ['x']]);
        $mixed->each->save('again');
        $this->assertSame(['hello', 'again'], $people->first()->saved);
    }

    public function testMapProxyOnArraysAndNulls(): void
    {
        $this->assertSame(['ada', 'bob', 'cy'], $this->users()->map->name->all());
        $this->assertSame([1, null], $this->collect([['a' => 1], null])->map->a->all());
    }

    public function testNewProxiesReadPropertiesAndCallMethods(): void
    {
        $people = $this->objects();

        $this->assertSame(['ada', 'cy'], $people->filter->active->map->name->all());
        $this->assertSame(['ada', 'cy'], $people->filter->isAdult()->map->name->all());
        $this->assertSame(['bob'], $people->reject->active->map->name->all());
        $this->assertSame(82, $people->sum->age);
        $this->assertSame(40, $people->max->age);
        $this->assertSame(12, $people->min->age);
        $this->assertSame(['bob', 'ada', 'cy'], $people->sortBy->age->map->name->all());
        $this->assertSame(['cy', 'ada', 'bob'], $people->sortByDesc->age->map->name->all());
        $this->assertTrue($people->some->isAdult());
        $this->assertFalse($people->every->isAdult());
        $this->assertCount(2, $people->groupBy->active);
    }

    public function testADataKeyBeatsTheNewProxyNamesButNotMapAndEach(): void
    {
        $c = $this->collect(['filter' => 'a value', 'sum' => 10, 'map' => 'shadowed']);

        $this->assertSame('a value', $c->filter);
        $this->assertSame(10, $c->sum);
        $this->assertInstanceOf(HigherOrderCollectionProxy::class, $c->map, 'map has always been the proxy');
        $this->assertNull($this->collect([1])->name);
    }

    public function testNewMethodsKeepTheModel(): void
    {
        $c = new Collection('App\Models\User', [3, 1, 2]);

        foreach ([$c->reject(fn() => false), $c->reverse(), $c->keys(), $c->only([0]), $c->except([0]), $c->skip(1), $c->slice(1), $c->concat([4]), $c->collapse(), $c->flatMap(fn($n) => [$n]), $c->whereNotNull('x'), $c->merge([1]), $c->sortBy(['a'])] as $result) {
            $this->assertInstanceOf(Collection::class, $result);
            $this->assertSame('App\Models\User', $result->getModel());
        }
    }
}
