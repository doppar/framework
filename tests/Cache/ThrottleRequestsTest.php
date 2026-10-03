<?php

namespace Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use Phaseolies\Cache\CacheStore;
use Phaseolies\Cache\RateLimiter;
use Phaseolies\DI\Container;
use Phaseolies\Http\Exceptions\TooManyRequestsHttpException;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Middleware\ThrottleRequests;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tests\Support\MockContainer;

class ThrottleClientRequest extends Request
{
    public function __construct(private string $stubIp = '203.0.113.7')
    {
    }

    public function ip(): ?string
    {
        return $this->stubIp;
    }

    public function user(): ?\Phaseolies\Database\Entity\Model
    {
        return null;
    }
}

class ThrottleRequestsTest extends TestCase
{
    private CacheStore $cache;
    private ThrottleRequests $middleware;
    private int $handled = 0;

    protected function setUp(): void
    {
        $container = new MockContainer();
        $container->instance('translator', new class {
            public function trans($key, array $replace = [], $locale = null): string
            {
                return 'Too many attempts, retry in ' . ($replace['attribute'] ?? '?') . ' seconds.';
            }
        });
        Container::setInstance($container);

        $this->cache = new CacheStore(new ArrayAdapter(), 'rl_');
        $this->middleware = new ThrottleRequests(new RateLimiter($this->cache));
        $this->handled = 0;
    }

    protected function tearDown(): void
    {
        Container::forgetInstance();
    }

    /**
     * Send one request through the middleware
     */
    private function send(int|string $max, float|int $decayMinutes = 1, string $ip = '203.0.113.7'): Response
    {
        return ($this->middleware)(new ThrottleClientRequest($ip), function () {
            $this->handled++;

            return new Response('ok');
        }, $max, $decayMinutes);
    }

    public function testTheHandlerOnlyRunsForRequestsThatAreAllowedThrough(): void
    {
        $blocked = 0;

        for ($i = 0; $i < 6; $i++) {
            try {
                $this->send(2);
            } catch (TooManyRequestsHttpException) {
                $blocked++;
            }
        }

        $this->assertSame(2, $this->handled, 'a blocked request must never reach the controller');
        $this->assertSame(4, $blocked);
    }

    public function testTheLimitIsExactlyTheNumberOfAllowedRequests(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->send(3);
            $this->assertSame((string) 3, (string) $response->headers->get('X-RateLimit-Limit'));
            $this->assertSame((string) (3 - $i), (string) $response->headers->get('X-RateLimit-Remaining'));
        }

        $this->expectException(TooManyRequestsHttpException::class);
        $this->send(3);
    }

    public function testEachClientHasItsOwnCounter(): void
    {
        $this->send(1, 1, '198.51.100.1');

        $this->send(1, 1, '198.51.100.2');
        $this->assertSame(2, $this->handled);

        $this->expectException(TooManyRequestsHttpException::class);
        $this->send(1, 1, '198.51.100.1');
    }

    public function testRoutesWithDifferentLimitsDoNotShareACounter(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->send(100);   // a busy API route
        }

        $this->send(5);         // the first request to a strict login route

        $this->assertSame(11, $this->handled);
    }

    public function testRoutesWithTheSameLimitShareACounterPerClient(): void
    {
        $this->send(2);
        $this->send(2);

        $this->expectException(TooManyRequestsHttpException::class);
        $this->send(2);
    }

    public function testTheRetryTimeReportedDoesNotOutlastTheWindow(): void
    {
        $this->send(1, 1);

        try {
            $this->send(1, 1);
            $this->fail('Expected the second request to be blocked');
        } catch (TooManyRequestsHttpException $e) {
            $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 0);
        }

        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    public function testTheResetTimeFollowsTheWindowAndDoesNotMoveBetweenRequests(): void
    {
        $first = (int) $this->send(5)->headers->get('X-RateLimit-Reset');
        sleep(1);
        $second = (int) $this->send(5)->headers->get('X-RateLimit-Reset');

        $this->assertEqualsWithDelta($first, $second, 1, 'later hits must not push the reset time back');
        $this->assertEqualsWithDelta(time() + 59, $second, 3);
    }

    public function testAClientStartsAgainOnceTheWindowHasEnded(): void
    {
        $signature = (new \ReflectionMethod($this->middleware, 'resolveRequestSignature'))
            ->invoke($this->middleware, new ThrottleClientRequest(), 2, 1);

        // Used up, but the reset time is already in the past.
        $this->cache->set($signature, 2, 60);
        $this->cache->set($signature . '_timer', time() - 1, 60);

        $response = $this->send(2);

        $this->assertSame(1, $this->handled, 'the request goes through');
        $this->assertSame('1', (string) $response->headers->get('X-RateLimit-Remaining'), 'and starts a new count');
    }

    public function testALimitWithAnAuthenticatedVariantUsesTheGuestValueWithoutAUser(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->send('2|10');
        }

        $this->expectException(TooManyRequestsHttpException::class);
        $this->send('2|10');
    }
}
