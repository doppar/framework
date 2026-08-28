<?php

namespace Tests\Unit\Requests;

use Phaseolies\Cache\IncrementableCacheInterface;
use Phaseolies\Cache\RateLimit;
use Phaseolies\Cache\RateLimiter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

#[AllowMockObjectsWithoutExpectations]
class RateLimiterTest extends TestCase
{
    protected IncrementableCacheInterface $cache;
    protected RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(IncrementableCacheInterface::class);
        $this->limiter = new RateLimiter($this->cache);
    }

    public function testInitialization()
    {
        $this->assertInstanceOf(RateLimiter::class, $this->limiter);
    }

    public function testAttemptWithNewKey()
    {
        $key = 'test_key';
        $maxAttempts = 5;
        $decaySeconds = 60;
        $now = time();

        $this->cache->expects($this->exactly(2))
            ->method('add')
            ->willReturnCallback(function ($keyArg, $valueArg, $ttlArg) use ($key, $decaySeconds, $now) {
                static $call = 0;
                $call++;

                if ($call === 1) {
                    TestCase::assertSame($key, $keyArg);
                    TestCase::assertSame(1, $valueArg);
                    TestCase::assertSame($decaySeconds, $ttlArg);

                    return true;
                }

                TestCase::assertSame($key . '_timer', $keyArg);
                TestCase::assertSame($now + $decaySeconds, $valueArg);
                TestCase::assertSame($decaySeconds, $ttlArg);

                return true;
            });

        $this->cache->expects($this->once())
            ->method('get')
            ->with($key . '_timer')
            ->willReturn($now + $decaySeconds);

        $result = $this->limiter->attempt($key, $maxAttempts, $decaySeconds);

        $this->assertInstanceOf(RateLimit::class, $result);
        $this->assertEquals($maxAttempts, $result->limit);
        $this->assertEquals($maxAttempts - 1, $result->remaining);
        $this->assertEquals($now + $decaySeconds, $result->resetAt);
    }

    public function testAttemptWithExistingKeyUsesIncrementPath()
    {
        $key = 'test_key';
        $maxAttempts = 5;
        $decaySeconds = 60;
        $now = time();

        $this->cache->expects($this->once())
            ->method('add')
            ->with($key, 1, $decaySeconds)
            ->willReturn(false);

        $this->cache->expects($this->once())
            ->method('increment')
            ->with($key)
            ->willReturn(3);

        $this->cache->expects($this->once())
            ->method('set')
            ->with($key . '_timer', $now + $decaySeconds, $decaySeconds)
            ->willReturn(true);

        $this->cache->expects($this->once())
            ->method('get')
            ->with($key . '_timer')
            ->willReturn($now + $decaySeconds);

        $result = $this->limiter->attempt($key, $maxAttempts, $decaySeconds);

        $this->assertSame(2, $result->remaining);
    }

    public function testAttemptRecoversWhenIncrementReturnsFalse()
    {
        $key = 'test_key';
        $maxAttempts = 5;
        $decaySeconds = 60;
        $now = time();

        $this->cache->expects($this->once())
            ->method('add')
            ->with($key, 1, $decaySeconds)
            ->willReturn(false);

        $this->cache->expects($this->once())
            ->method('increment')
            ->with($key)
            ->willReturn(false);

        $this->cache->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function ($keyArg, $valueArg, $ttlArg) use ($key, $decaySeconds, $now) {
                static $call = 0;
                $call++;

                if ($call === 1) {
                    TestCase::assertSame($key, $keyArg);
                    TestCase::assertSame(1, $valueArg);
                } else {
                    TestCase::assertSame($key . '_timer', $keyArg);
                    TestCase::assertSame($now + $decaySeconds, $valueArg);
                }

                TestCase::assertSame($decaySeconds, $ttlArg);

                return true;
            });

        $this->cache->expects($this->once())
            ->method('get')
            ->with($key . '_timer')
            ->willReturn($now + $decaySeconds);

        $result = $this->limiter->attempt($key, $maxAttempts, $decaySeconds);

        $this->assertSame($maxAttempts - 1, $result->remaining);
    }

    public function testTooManyAttempts()
    {
        $key = 'test_key';
        $maxAttempts = 5;

        $this->cache->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn(5);

        $this->assertTrue($this->limiter->tooManyAttempts($key, $maxAttempts));
    }

    public function testAvailableIn()
    {
        $key = 'test_key';
        $remainingTime = 30;
        $now = time();

        $this->cache->expects($this->once())
            ->method('get')
            ->with($key . '_timer')
            ->willReturn($now + $remainingTime);

        $result = $this->limiter->availableIn($key);
        $this->assertEquals($remainingTime, $result);
    }

    public function testAvailableInWhenTimerMissing()
    {
        $key = 'test_key';

        $this->cache->expects($this->once())
            ->method('get')
            ->with($key . '_timer')
            ->willReturn(null);

        $result = $this->limiter->availableIn($key);
        $this->assertEquals(0, $result);
    }

    public function testAvailableAt()
    {
        $seconds = 60;
        $now = time();
        $result = $this->limiter->availableAt($seconds);

        $this->assertEquals($now + $seconds, $result);
    }

    public function testHitWithNewKey()
    {
        $key = 'test_key';
        $decaySeconds = 60;
        $now = time();

        $this->cache->expects($this->exactly(2))
            ->method('add')
            ->willReturnCallback(function ($keyArg, $valueArg, $ttlArg) use ($key, $decaySeconds, $now) {
                static $call = 0;
                $call++;

                if ($call === 1) {
                    TestCase::assertSame($key, $keyArg);
                    TestCase::assertSame(1, $valueArg);
                    TestCase::assertSame($decaySeconds, $ttlArg);

                    return true;
                }

                TestCase::assertSame($key . '_timer', $keyArg);
                TestCase::assertSame($now + $decaySeconds, $valueArg);
                TestCase::assertSame($decaySeconds, $ttlArg);

                return true;
            });

        $result = $this->limiter->hit($key, $decaySeconds);
        $this->assertEquals(1, $result);
    }

    public function testHitWithExistingKeyUsesIncrement()
    {
        $key = 'test_key';
        $decaySeconds = 60;
        $now = time();

        $this->cache->expects($this->once())
            ->method('add')
            ->with($key, 1, $decaySeconds)
            ->willReturn(false);

        $this->cache->expects($this->once())
            ->method('increment')
            ->with($key)
            ->willReturn(4);

        $this->cache->expects($this->once())
            ->method('set')
            ->with($key . '_timer', $now + $decaySeconds, $decaySeconds)
            ->willReturn(true);

        $this->assertSame(4, $this->limiter->hit($key, $decaySeconds));
    }

    public function testClear()
    {
        $key = 'test_key';

        $this->cache->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function ($keyArg) use ($key) {
                static $call = 0;
                $call++;

                if ($call === 1) {
                    TestCase::assertEquals($key, $keyArg);
                } elseif ($call === 2) {
                    TestCase::assertEquals($key . '_timer', $keyArg);
                }

                return true;
            });

        $this->limiter->clear($key);
    }

    public function testAttempts()
    {
        $key = 'test_key';
        $attempts = 3;

        $this->cache->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn($attempts);

        $result = $this->limiter->attempts($key);
        $this->assertEquals($attempts, $result);
    }

    public function testAttemptsWhenKeyMissing()
    {
        $key = 'test_key';

        $this->cache->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn(null);

        $result = $this->limiter->attempts($key);
        $this->assertEquals(0, $result);
    }

    public function testResetAttempts()
    {
        $key = 'test_key';

        $this->cache->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willReturn(true);

        $this->limiter->resetAttempts($key);
    }

    public function testCacheExceptionHandling()
    {
        $key = 'test_key';
        $exception = new class extends \Exception implements InvalidArgumentException {};

        $this->cache->expects($this->once())
            ->method('add')
            ->with($key, 1, 60)
            ->willThrowException($exception);

        $this->expectException(InvalidArgumentException::class);
        $this->limiter->attempt($key, 5, 60);
    }
}
