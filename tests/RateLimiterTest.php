<?php

namespace Tests\Unit;

use Phaseolies\Cache\RateLimiter;
use Phaseolies\Cache\RateLimit;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    protected $cache;
    protected $limiter;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
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

        // Mock has() to return false for both key checks
        $this->cache->expects($this->exactly(1))
            ->method('has')
            ->willReturnCallback(function ($arg) use ($key) {
                TestCase::assertTrue(in_array($arg, [$key, $key . '_timer']));
            })
            ->willReturn(false);

        // Mock set() calls
        $this->cache->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function ($keyArg, $valueArg, $ttlArg) use ($key, $decaySeconds, $now) {
                static $call = 0;
                $call++;

                if ($call === 1) {
                    // First expected call: [$key, 1, $decaySeconds]
                    TestCase::assertEquals($key, $keyArg);
                    TestCase::assertEquals(1, $valueArg);
                    TestCase::assertEquals($decaySeconds, $ttlArg);
                } elseif ($call === 2) {
                    // Second expected call: [$key . '_timer', $now + $decaySeconds, $decaySeconds]
                    TestCase::assertEquals($key . '_timer', $keyArg);
                    TestCase::assertEquals($now + $decaySeconds, $valueArg);
                    TestCase::assertEquals($decaySeconds, $ttlArg);
                }

                return true;
            })
            ->willReturn(true);

        $result = $this->limiter->attempt($key, $maxAttempts, $decaySeconds);

        $this->assertInstanceOf(RateLimit::class, $result);
        $this->assertEquals($maxAttempts, $result->limit);
        $this->assertEquals($maxAttempts - 1, $result->remaining);
        $this->assertEquals($now + $decaySeconds, $result->resetAt);
    }

    public function testTooManyAttempts()
    {
        $key = 'test_key';
        $maxAttempts = 5;

        $this->cache->method('get')
            ->with($key)
            ->willReturn(5);

        $this->assertTrue($this->limiter->tooManyAttempts($key, $maxAttempts));
    }

    public function testAvailableIn()
    {
        $key = 'test_key';
        $remainingTime = 30;
        $now = time();

        $this->cache->method('get')
            ->with($key . '_timer')
            ->willReturn($now + $remainingTime);

        $result = $this->limiter->availableIn($key);
        $this->assertEquals($remainingTime, $result);
    }

    public function testAvailableInWhenTimerMissing()
    {
        $key = 'test_key';

        $this->cache->method('get')
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

        $this->cache->method('has')
            ->with($key)
            ->willReturn(false);

        $this->cache->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function ($keyArg, $valueArg, $ttlArg) use ($key, $decaySeconds, $now) {
                static $call = 0;
                $call++;

                if ($call === 1) {
                    TestCase::assertEquals($key, $keyArg);
                    TestCase::assertEquals(1, $valueArg);
                    TestCase::assertEquals($decaySeconds, $ttlArg);
                } elseif ($call === 2) {
                    TestCase::assertEquals($key . '_timer', $keyArg);
                    TestCase::assertEquals($now + $decaySeconds, $valueArg);
                    TestCase::assertEquals($decaySeconds, $ttlArg);
                }
            })
            ->willReturn(true);

        $result = $this->limiter->hit($key, $decaySeconds);
        $this->assertEquals(1, $result);
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
            })
            ->willReturn(true);

        $this->limiter->clear($key);
    }

    public function testAttempts()
    {
        $key = 'test_key';
        $attempts = 3;

        $this->cache->method('get')
            ->with($key)
            ->willReturn($attempts);

        $result = $this->limiter->attempts($key);
        $this->assertEquals($attempts, $result);
    }

    public function testAttemptsWhenKeyMissing()
    {
        $key = 'test_key';

        $this->cache->method('get')
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

        $this->cache->method('has')
            ->with($key)
            ->willThrowException($exception);

        $this->expectException(InvalidArgumentException::class);
        $this->limiter->attempt($key, 5, 60);
    }
}
