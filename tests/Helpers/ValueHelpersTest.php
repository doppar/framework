<?php

namespace Tests\Unit\Helpers;

use ArrayObject;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ValueHelpersTest extends TestCase
{
    public function testBlankAndFilled(): void
    {
        foreach ([null, '', '   ', "\n\t", [], new ArrayObject()] as $value) {
            $this->assertTrue(blank($value));
            $this->assertFalse(filled($value));
        }

        foreach ([0, 0.0, '0', false, true, 'a', [0], new ArrayObject([1]), new \stdClass()] as $value) {
            $this->assertFalse(blank($value));
            $this->assertTrue(filled($value));
        }
    }

    public function testWith(): void
    {
        $this->assertSame(10, with(5, fn($v) => $v * 2));
        $this->assertSame(5, with(5));
    }

    public function testRescueReturnsResultWhenNothingThrows(): void
    {
        $this->assertSame('ok', rescue(fn() => 'ok', 'fallback'));
    }

    public function testRescueReturnsDefaultOnException(): void
    {
        $this->assertSame('fallback', rescue(fn() => throw new RuntimeException('x'), 'fallback', false));
        $this->assertNull(rescue(fn() => throw new RuntimeException('x'), report: false));
    }

    public function testRescueDefaultClosureReceivesException(): void
    {
        $result = rescue(
            fn() => throw new RuntimeException('boom'),
            fn($e) => strtoupper($e->getMessage()),
            false
        );

        $this->assertSame('BOOM', $result);
    }

    public function testRetrySucceedsAfterFailures(): void
    {
        $attempts = [];

        $result = retry(3, function ($attempt) use (&$attempts) {
            $attempts[] = $attempt;

            if ($attempt < 3) {
                throw new RuntimeException('fail');
            }

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame([1, 2, 3], $attempts);
    }

    public function testRetryRethrowsLastExceptionWhenExhausted(): void
    {
        $calls = 0;

        try {
            retry(2, function () use (&$calls) {
                throw new RuntimeException('attempt ' . ++$calls);
            });
            $this->fail('Expected exception');
        } catch (RuntimeException $e) {
            $this->assertSame('attempt 2', $e->getMessage());
            $this->assertSame(2, $calls);
        }
    }

    public function testRetryStopsImmediatelyWhenWhenReturnsFalse(): void
    {
        $calls = 0;

        $this->expectException(RuntimeException::class);

        try {
            retry(5, function () use (&$calls) {
                $calls++;
                throw new RuntimeException('fatal');
            }, 0, fn($e) => false);
        } finally {
            $this->assertSame(1, $calls);
        }
    }

    public function testRetrySleepsBetweenAttempts(): void
    {
        $delays = [];

        retry(3, function ($attempt) {
            if ($attempt < 3) {
                throw new RuntimeException('x');
            }
        }, function ($attempt) use (&$delays) {
            $delays[] = $attempt;

            return 1;
        });

        $this->assertSame([1, 2], $delays);

        $start = hrtime(true);
        try {
            retry(2, fn() => throw new RuntimeException('x'), 30);
        } catch (RuntimeException) {
        }
        $this->assertGreaterThanOrEqual(25, (hrtime(true) - $start) / 1e6);
    }

    public function testRetryRequiresAtLeastOneAttempt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        retry(0, fn() => 1);
    }

    public function testBenchmarkReturnsResultTimeAndMemory(): void
    {
        $report = benchmark(function () {
            usleep(20000);

            return str_repeat('a', 1024 * 1024);
        });

        $this->assertSame(1024 * 1024, strlen($report['result']));
        $this->assertGreaterThanOrEqual(15.0, $report['time_ms']);
        $this->assertGreaterThanOrEqual(1024 * 1024, $report['peak_memory_bytes']);
        $this->assertIsInt($report['memory_bytes']);
    }
}
