<?php

namespace Tests\Unit;

use Phaseolies\Support\Collection;
use Phaseolies\Support\StreamCollection;
use PHPUnit\Framework\TestCase;

class StreamCollectionFeaturesTest extends TestCase
{
    /**
     * A stream over 1..$count that records every item it hands out.
     */
    private function counting(int $count, array &$pulled): StreamCollection
    {
        return StreamCollection::make(function () use ($count, &$pulled) {
            for ($i = 1; $i <= $count; $i++) {
                $pulled[] = $i;

                yield $i;
            }
        });
    }

    // ----- data loss --------------------------------------------------------------------

    public function testFlattenKeepsEveryItemWhenConvertedToAnArray(): void
    {
        $stream = StreamCollection::make(fn() => yield from [[1, 2], [3, 4]])->flatten();

        $this->assertSame([1, 2, 3, 4], $stream->all());
        $this->assertSame([1, 2, 3, 4], $stream->collect()->all());
        $this->assertSame([1, 2, 3, 4], $stream->toArray());
        $this->assertSame('[1,2,3,4]', $stream->toJson());
    }

    public function testFlattenOfNestedStreamsAndCollectionsKeepsEveryItem(): void
    {
        $inner = new StreamCollection([3, [4]]);
        $stream = new StreamCollection([1, new Collection('', [2]), $inner, [[5]]]);

        $this->assertSame([1, 2, 3, 4, 5], $stream->flatten()->all());
        $this->assertSame([1, [2], 3], (new StreamCollection([[1, [2]], 3]))->flatten(1)->all());
    }

    public function testFlattenWithADepthLimit(): void
    {
        $this->assertSame([1, [2, [3]], 4], (new StreamCollection([[1, [2, [3]]], [4]]))->flatten(1)->all());
    }

    public function testChunkDoesNotLoseItemsWhenKeysRepeat(): void
    {
        $stream = StreamCollection::make(function () {
            yield from [1, 2];
            yield from [3, 4];
        });

        $chunks = array_map(fn($chunk) => array_values($chunk->all()), iterator_to_array($stream->chunk(4), false));

        $this->assertSame([[1, 2, 3, 4]], $chunks);
    }

    public function testChunkStillKeepsKeysWhenTheyAreUnique(): void
    {
        $chunks = iterator_to_array((new StreamCollection(['a' => 1, 'b' => 2, 'c' => 3]))->chunk(2), false);

        $this->assertSame(['a' => 1, 'b' => 2], $chunks[0]->all());
        $this->assertSame(['c' => 3], $chunks[1]->all());
    }

    // ----- laziness ---------------------------------------------------------------------

    public function testTakeReadsNoMoreThanItReturns(): void
    {
        $pulled = [];

        $this->assertSame([1, 2], $this->counting(5, $pulled)->take(2)->all());
        $this->assertSame([1, 2], $pulled, 'the third item must not be requested');
    }

    public function testTakeZeroReadsNothing(): void
    {
        $pulled = [];

        $this->assertSame([], $this->counting(5, $pulled)->take(0)->all());
        $this->assertSame([], $pulled);
        $this->assertSame([], $this->counting(5, $pulled)->take(-3)->all());
        $this->assertSame([], $pulled);
    }

    public function testUniqueIsLazyAndWorksOnAnEndlessStream(): void
    {
        $endless = StreamCollection::make(function () {
            $i = 0;

            while (true) {
                yield $i++ % 5;
            }
        });

        $this->assertSame([0, 1, 2], $endless->unique()->take(3)->all());
    }

    public function testUniqueStopsReadingOnceTheConsumerHasEnough(): void
    {
        $pulled = [];

        $this->counting(5, $pulled)->unique()->take(2)->all();

        $this->assertSame([1, 2], $pulled);
    }

    public function testUniqueKeepsTheFirstOfEach(): void
    {
        $stream = new StreamCollection([
            ['id' => 1, 'k' => 'a'],
            ['id' => 2, 'k' => 'a'],
            ['id' => 3, 'k' => 'b'],
        ]);

        $this->assertSame([1, 3], array_column($stream->unique('k')->all(), 'id'));
    }

    public function testUniqueTellsFloatsApartWithoutDeprecations(): void
    {
        $deprecations = [];
        set_error_handler(function ($no, $message) use (&$deprecations) {
            $deprecations[] = $message;

            return true;
        });

        try {
            $result = (new StreamCollection([1.5, 1.2, 1.5, 1.2]))->unique()->all();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([1.5, 1.2], $result);
        $this->assertSame([], $deprecations);
    }

    public function testUniqueByANestedKey(): void
    {
        $stream = new StreamCollection([['a' => ['b' => 1]], ['a' => ['b' => 1]], ['a' => ['b' => 2]]]);

        $this->assertCount(2, $stream->unique('a.b')->all());
    }

    public function testFirstStopsAtTheFirstMatch(): void
    {
        $pulled = [];

        $this->assertSame(3, $this->counting(10, $pulled)->first(fn($n) => $n >= 3));
        $this->assertSame([1, 2, 3], $pulled);
    }

    public function testEverySomeAndTakeWhileStopEarly(): void
    {
        $pulled = [];
        $this->assertFalse($this->counting(10, $pulled)->every(fn($n) => $n < 3));
        $this->assertSame([1, 2, 3], $pulled);

        $pulled = [];
        $this->assertTrue($this->counting(10, $pulled)->some(fn($n) => $n === 2));
        $this->assertSame([1, 2], $pulled);

        $pulled = [];
        $this->assertSame([1, 2], $this->counting(10, $pulled)->takeWhile(fn($n) => $n < 3)->all());
        $this->assertSame([1, 2, 3], $pulled, 'it has to read the first item that fails');
    }

    // ----- API --------------------------------------------------------------------------

    public function testMakeAcceptsEverySourceTheConstructorDoes(): void
    {
        $this->assertSame([1, 2], StreamCollection::make([1, 2])->all());
        $this->assertSame([1], StreamCollection::make((function () {
            yield 1;
        })())->all());
        $this->assertSame([1], StreamCollection::make(new \ArrayIterator([1]))->all());
        $this->assertSame([1], StreamCollection::make(fn() => yield 1)->all());
    }

    public function testItCanBeCounted(): void
    {
        $this->assertInstanceOf(\Countable::class, new StreamCollection([]));
        $this->assertSame(3, count(new StreamCollection([1, 2, 3])));
    }

    public function testEachIsChainableAndStopsWhenTheCallbackReturnsFalse(): void
    {
        $seen = [];
        $stream = new StreamCollection([1, 2, 3, 4]);

        $returned = $stream->each(function ($value) use (&$seen) {
            $seen[] = $value;

            return $value < 2;
        });

        $this->assertSame($stream, $returned);
        $this->assertSame([1, 2], $seen);
    }

    public function testFirstAndLastTakeATestAndADefault(): void
    {
        $stream = new StreamCollection([1, 2, 3, 4]);

        $this->assertSame(2, $stream->first(fn($n) => $n > 1));
        $this->assertSame('none', $stream->first(fn($n) => $n > 9, 'none'));
        $this->assertSame('lazy', $stream->first(fn($n) => $n > 9, fn() => 'lazy'));
        $this->assertSame(4, $stream->last());
        $this->assertSame(3, $stream->last(fn($n) => $n < 4));
        $this->assertSame('none', $stream->last(fn($n) => $n > 9, 'none'));
        $this->assertNull((new StreamCollection([]))->last());
    }

    public function testFilterWithoutACallbackDropsFalsyItems(): void
    {
        $this->assertSame([1, 'a'], array_values((new StreamCollection([0, 1, null, 'a', false, '', []]))->filter()->all()));
    }

    public function testRejectIsTheOppositeOfFilter(): void
    {
        $this->assertSame([1, 3], array_values((new StreamCollection([1, 2, 3, 4]))->reject(fn($n) => $n % 2 === 0)->all()));
    }

    public function testPluckUnderstandsDotNotation(): void
    {
        $stream = new StreamCollection([['user' => ['name' => 'ada']], ['user' => ['name' => 'bob']], ['user' => []]]);

        $this->assertSame(['ada', 'bob', null], $stream->pluck('user.name')->all());
    }

    // ----- new methods ------------------------------------------------------------------

    public function testSkipWhile(): void
    {
        $this->assertSame([3, 4, 1], array_values((new StreamCollection([1, 2, 3, 4, 1]))->skipWhile(fn($n) => $n < 3)->all()));
    }

    public function testReduceSumAvgMinMax(): void
    {
        $stream = new StreamCollection([4, 1, 7]);

        $this->assertSame(12, $stream->reduce(fn($carry, $n) => $carry + $n, 0));
        $this->assertSame(12, $stream->sum());
        $this->assertSame(4.0, (float) $stream->avg());
        $this->assertSame(1, $stream->min());
        $this->assertSame(7, $stream->max());
    }

    public function testAggregatesOfAnEmptyStream(): void
    {
        $empty = new StreamCollection([]);

        $this->assertSame(0, $empty->sum());
        $this->assertNull($empty->avg());
        $this->assertNull($empty->min());
        $this->assertNull($empty->max());
        $this->assertNull($empty->reduce(fn($c, $n) => $c + $n));
        $this->assertTrue($empty->every(fn() => false));
        $this->assertFalse($empty->some(fn() => true));
    }

    public function testAggregatesByKeyAndCallback(): void
    {
        $stream = new StreamCollection([['p' => ['n' => 3]], ['p' => ['n' => 5]]]);

        $this->assertSame(8, $stream->sum('p.n'));
        $this->assertSame(4.0, (float) $stream->avg('p.n'));
        $this->assertSame(3, $stream->min('p.n'));
        $this->assertSame(10, $stream->max(fn($row) => $row['p']['n'] * 2));
        $this->assertSame(16, $stream->sum(fn($row) => $row['p']['n'] * 2));
    }

    public function testAMinOrMaxOfZeroOrNegativeValuesIsNotMistakenForNothing(): void
    {
        $this->assertSame(0, (new StreamCollection([0, 3]))->min());
        $this->assertSame(0, (new StreamCollection([-5, 0]))->max());
        $this->assertSame(-5, (new StreamCollection([-5, 0]))->min());
    }

    public function testConcatNumbersTheItems(): void
    {
        $stream = (new StreamCollection(['a' => 1]))->concat(new StreamCollection([2, 3]))->concat([4]);

        $this->assertSame([1, 2, 3, 4], $stream->all());
    }

    public function testFlatMap(): void
    {
        $this->assertSame(['a', 'b', 'c'], (new StreamCollection(['ab', 'c']))->flatMap(fn($s) => str_split($s))->all());
        $this->assertSame([1, 2, 9], (new StreamCollection([[1, 2], 9]))->flatMap(fn($x) => $x)->all());
        $this->assertSame([1, 2], (new StreamCollection([1]))->flatMap(fn($x) => new Collection('', [1, 2]))->all());
    }

    public function testTapAndPipe(): void
    {
        $stream = new StreamCollection([1, 2]);
        $seen = null;

        $this->assertSame($stream, $stream->tap(function ($s) use (&$seen) {
            $seen = $s;
        }));
        $this->assertSame($stream, $seen);
        $this->assertSame(2, $stream->pipe(fn($s) => $s->count()));
    }

    public function testRange(): void
    {
        $this->assertSame([1, 2, 3], StreamCollection::range(1, 3)->all());
        $this->assertSame([0, 5, 10], StreamCollection::range(0, 10, 5)->all());
        $this->assertSame([3, 2, 1], StreamCollection::range(3, 1)->all());
        $this->assertSame([0, 0.5, 1.0], StreamCollection::range(0, 1, 0.5)->all());
        $this->assertSame([5], StreamCollection::range(5, 5)->all());
    }

    public function testRangeIsLazyAndRejectsAZeroStep(): void
    {
        $this->assertSame([1, 2, 3], StreamCollection::range(1, PHP_INT_MAX - 1)->take(3)->all());

        $this->expectException(\InvalidArgumentException::class);
        StreamCollection::range(1, 5, 0);
    }

    public function testTimes(): void
    {
        $this->assertSame([1, 2, 3], StreamCollection::times(3)->all());
        $this->assertSame([2, 4, 6], StreamCollection::times(3, fn($i) => $i * 2)->all());
        $this->assertSame([], StreamCollection::times(0)->all());
    }

    // ----- files ------------------------------------------------------------------------

    public function testLinesReadsAFileOneLineAtATime(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stream');
        file_put_contents($path, "one\r\ntwo\nthree");

        try {
            $this->assertSame(['one', 'two', 'three'], StreamCollection::lines($path)->all());
            $this->assertSame(['one', 'two'], StreamCollection::lines($path)->take(2)->all());
            $this->assertSame(3, StreamCollection::lines($path)->count());
            $this->assertSame(['TWO'], StreamCollection::lines($path)->filter(fn($l) => $l === 'two')->map(fn($line) => strtoupper($line))->values()->all());
        } finally {
            unlink($path);
        }
    }

    public function testLinesClosesTheFileWhenTheConsumerStopsEarly(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stream');
        file_put_contents($path, str_repeat("line\n", 50));

        try {
            $this->assertSame('line', StreamCollection::lines($path)->first());
            gc_collect_cycles();

            // On Linux, a deleted file whose handle is still open is still listed under /proc.
            if (is_dir('/proc/self/fd')) {
                $open = array_filter(array_map(fn($fd) => @readlink($fd), glob('/proc/self/fd/*') ?: []), fn($target) => $target === $path);
                $this->assertSame([], array_values($open));
            }
        } finally {
            unlink($path);
        }
    }

    public function testLinesFailsClearlyForAMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        StreamCollection::lines('/nonexistent/file.log');
    }

    // ----- remember ---------------------------------------------------------------------

    public function testAGeneratorSourceCanOnlyBeReadOnce(): void
    {
        $stream = new StreamCollection((function () {
            yield 1;
            yield 2;
        })());

        $stream->all();

        $this->expectException(\Exception::class);
        $stream->all();
    }

    public function testRememberLetsAGeneratorSourceBeReadAgain(): void
    {
        $stream = (new StreamCollection((function () {
            yield 'a' => 1;
            yield 'b' => 2;
        })()))->remember();

        $this->assertSame(['a' => 1, 'b' => 2], $stream->all());
        $this->assertSame(['a' => 1, 'b' => 2], $stream->all());
        $this->assertSame(2, $stream->count());
    }

    public function testRememberRunsTheSourceOnlyOnceAndReadsLazily(): void
    {
        $pulled = [];
        $stream = $this->counting(5, $pulled)->remember();

        $this->assertSame([1, 2], $stream->take(2)->all());
        $this->assertSame([1, 2], $pulled);

        $this->assertSame([1, 2, 3, 4, 5], $stream->all());
        $this->assertSame([1, 2, 3, 4, 5], $pulled, 'items 1 and 2 were not produced again');

        $stream->all();
        $this->assertSame([1, 2, 3, 4, 5], $pulled);
    }

    public function testRememberedStreamsCanBeReadInterleaved(): void
    {
        $pulled = [];
        $stream = $this->counting(3, $pulled)->remember();

        $a = $stream->getIterator();
        $b = $stream->getIterator();
        $a->rewind();
        $b->rewind();

        $this->assertSame(1, $a->current());
        $a->next();
        $this->assertSame(2, $a->current());
        $this->assertSame(1, $b->current(), 'a second reader starts from the beginning');
        $b->next();
        $this->assertSame(2, $b->current());
        $this->assertSame([1, 2], $pulled);
    }

    public function testMethodsReturnStreamsOfTheSameClass(): void
    {
        $stream = new StreamCollection([1, 2, 3]);

        foreach ([$stream->map(fn($x) => $x), $stream->filter(), $stream->reject(fn() => false), $stream->take(1), $stream->skip(1), $stream->takeWhile(fn() => true), $stream->skipWhile(fn() => false), $stream->unique(), $stream->flatten(), $stream->values(), $stream->concat([1]), $stream->flatMap(fn($x) => [$x]), $stream->remember(), $stream->pluck('x')] as $result) {
            $this->assertInstanceOf(StreamCollection::class, $result);
        }
    }
}
