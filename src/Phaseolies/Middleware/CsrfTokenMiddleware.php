<?php

namespace Phaseolies\Middleware;

use Closure;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Http\Exceptions\TokenMismatchException;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Http\Response\Cookie;
use Phaseolies\Middleware\Contracts\Middleware;
use Phaseolies\Session\Contracts\CommittableSession;
use Phaseolies\Support\Facades\Str;

class CsrfTokenMiddleware implements Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * Handles an incoming request and verifies the CSRF token.
     *
     * @param Request $request
     * @param \Closure(\Phaseolies\Http\Request) $next
     * @return \Phaseolies\Http\Response
     * @throws HttpException
     */
    public function __invoke(Request $request, Closure $next): Response
    {
        if (
            $this->isReading($request) ||
            $this->runningUnitTests() ||
            $this->isTokenMatch($request)
        ) {
            $response = $next($request);
            if ($this->shouldAddXsrfTokenCookie($request)) {
                return $this->addCookieToResponse($request, $response);
            }
            return $response;
        }

        return $this->handleError("CSRF Token mismatched");
    }

    /**
     * Checks if the request is a modifying request (POST, PUT, PATCH, DELETE).
     *
     * @param Request $request
     * @return bool
     */
    protected function isReading(Request $request): bool
    {
        return in_array(Str::toUpper($request->method()), ['HEAD', 'GET', 'OPTIONS']);
    }

    /**
     * Determines if the application is running UNIT TEST
     *
     * @return bool
     */
    protected function runningUnitTests(): bool
    {
        return app()->runningInConsole() && app()->isRunningUnitTests();
    }

    /**
     * Check the request token is matched or not
     *
     * @param Request $request
     * @return bool
     */
    public function isTokenMatch($request): bool
    {
        foreach (app()->getRelaxablePaths() as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        // Read the stored token instead of token(), which would create one:
        // a request that arrives without a session must fail, not start one.
        $stored = $request->session()->get('_token');

        return is_string($stored) &&
            is_string($token) &&
            hash_equals($stored, $token);
    }

    /**
     * Add the CSRF token to the response cookies.
     *
     * @param \Phaseolies\Http\Request $request
     * @param \Phaseolies\Http\Response $response
     * @return \Phaseolies\Http\Response
     */
    protected function addCookieToResponse($request, $response): Response
    {
        if (PHP_SAPI === 'cli' || defined('STDIN')) {
            return $response;
        }

        if (!$this->shouldIssueCookie($request)) {
            return $response;
        }

        $response->headers->setCookie($this->addCookie($request, config('session')));

        return $response;
    }

    /**
     * Decide whether this response should carry the XSRF-TOKEN cookie
     *
     * @param \Phaseolies\Http\Request $request
     * @return bool
     */
    protected function shouldIssueCookie($request): bool
    {
        $session = $request->session();

        // Stateless requests have no stored session to tie the token to.
        if ($session instanceof CommittableSession && $session->isStateless()) {
            return false;
        }

        if ($session->has('_token')) {
            return true;
        }

        return $this->alwaysSendsCookie();
    }

    /**
     * Whether the cookie is sent even before a token exists.
     *
     * @return bool
     */
    protected function alwaysSendsCookie(): bool
    {
        return (bool) (((array) config('session'))['always_send_xsrf_cookie'] ?? false);
    }

    /**
     * Create a new "XSRF-TOKEN" cookie that contains the CSRF token.
     *
     * @param \Phaseolies\Http\Request $request
     * @param array $config
     * @return \Phaseolies\Http\Response\Cookie
     */
    protected function addCookie($request, $config): Cookie
    {
        return new Cookie(
            'XSRF-TOKEN',
            $request->session()->token(),
            time() + 60 * $config['lifetime'],
            $config['path'],
            $config['domain'],
            $config['secure'],
            false,
            false,
            $config['same_site'] ?? 'lax'
        );
    }

    /**
     * Get the CSRF token from the request.
     *
     * @param \Phaseolies\Http\Request $request
     * @return string|null
     */
    protected function getTokenFromRequest($request): ?string
    {
        $token = $request->input('_token') ?: $request->headers->get('X-CSRF-TOKEN');

        if (! $token && $header = $request->headers->get('X-CSRF-TOKEN')) {
            try {
                $token = $header;
            } catch (\Exception) {
                $token = '';
            }
        }

        return $token;
    }

    /**
     * Determine if the cookie should be added to the response.
     *
     * @return bool
     */
    public function shouldAddXsrfTokenCookie(): bool
    {
        return $this->addHttpCookie;
    }

    /**
     * Handles CSRF validation errors.
     *
     * @param string $message
     * @return Response
     * @throws TokenMismatchException
     */
    protected function handleError(string $message): Response
    {
        throw new TokenMismatchException(419, $message);
    }
}
