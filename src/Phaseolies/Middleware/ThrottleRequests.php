<?php

namespace Phaseolies\Middleware;

use Phaseolies\Middleware\Contracts\Middleware;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Phaseolies\Http\Exceptions\TooManyRequestsHttpException;
use Phaseolies\Cache\RateLimiter;
use Closure;

class ThrottleRequests implements Middleware
{
    /**
     * The rate limiter instance.
     *
     * @var \Phaseolies\Cache\RateLimiter
     */
    protected $limiter;

    /**
     * Create a new middleware instance.
     *
     * @return void
     */
    public function __construct(RateLimiter $rateLimiter)
    {
        $this->limiter = $rateLimiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Phaseolies\Http\Request $request
     * @param \Closure $next
     * @param int|string $maxAttempts
     * @param float|int $decayMinutes
     * @return \Phaseolies\Http\Response
     * @throws \Phaseolies\Http\Exceptions\HttpResponseException
     */
    public function __invoke(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request, $maxAttempts, $decayMinutes);

        $maxAttempts = $this->resolveMaxAttempts($request, $maxAttempts);
        $decaySeconds = (int) ($decayMinutes * 60);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $this->rejectIfStillLimited($key);
        }

        $this->limiter->hit($key, $decaySeconds);

        // The handler only runs for requests that are allowed through.
        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts),
            $this->limiter->availableAt($this->limiter->availableIn($key) ?: $decaySeconds)
        );
    }

    /**
     * Resolve the number of attempts if the user is authenticated.
     *
     * @param \Phaseolies\Http\Request $request
     * @param int|string $maxAttempts
     * @return int
     */
    protected function resolveMaxAttempts(Request $request, $maxAttempts): int
    {
        if (str_contains($maxAttempts, '|')) {
            $maxAttempts = explode('|', $maxAttempts, 2)[$request->user() ? 1 : 0];
        }

        if (! is_numeric($maxAttempts)) {
            $maxAttempts = (int) $maxAttempts;
        }

        return $maxAttempts;
    }

    /**
     * Resolve request signature.
     *
     * @param \Phaseolies\Http\Request $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request, $maxAttempts = 60, $decayMinutes = 1): string
    {
        $client = $request->user() ? 'user:' . auth()->id() : 'ip:' . $request->ip();

        // The limit is part of the key, so a strict limit on one route (login: 5 a minute)
        // is not used up by traffic on a generous one (API: 60 a minute).
        return sha1($client . '|' . $maxAttempts . '|' . $decayMinutes);
    }

    /**
     * Calculate the number of remaining attempts.
     *
     * @param string $key
     * @param int $maxAttempts
     * @return int
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->limiter->attempts($key));
    }

    /**
     * Add the limit header information to the given response.
     *
     * @param \Phaseolies\Http\Response $response
     * @param int $maxAttempts
     * @param int $remainingAttempts
     * @param int $resetAt
     * @return \Phaseolies\Http\Response
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, int $resetAt): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
            'X-RateLimit-Reset' => $resetAt,
        ]);

        return $response;
    }

    /**
     * Stop a client that has used up its attempts, unless its window has ended.
     *
     * @param string $key
     * @return void
     * @throws TooManyRequestsHttpException
     */
    protected function rejectIfStillLimited(string $key): void
    {
        $retryAfter = $this->limiter->availableIn($key);

        // The window is over, so the client starts again with a fresh count.
        if ($retryAfter <= 0) {
            $this->limiter->clear($key);

            return;
        }

        $message = trans('validation.rate_limit.error', ['attribute' => $retryAfter]);

        throw new TooManyRequestsHttpException(
            $retryAfter,
            $message,
            null,
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }
}
