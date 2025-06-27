<?php

namespace Phaseolies\Middleware;

use Phaseolies\Support\Facades\Str;
use Phaseolies\Http\Response\Cookie;
use Phaseolies\Middleware\Contracts\Middleware;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Phaseolies\Http\Exceptions\TokenMismatchException;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Closure;
use Phaseolies\Support\Facades\Crypt;

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
     * This middleware checks if the request is a POST, PUT, PATCH, or DELETE request
     * and if the CSRF token is present and valid. If the CSRF token is missing or invalid,
     * a JSON response with a 422 status code is returned for AJAX requests, and an exception
     * is thrown for non-AJAX requests.
     *
     * @param Request $request The incoming request instance.
     * @param Closure $next The next middleware or request handler.
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

        return $this->handleError($request, "CSRF Token mismatched");
    }

    /**
     * Checks if the request is a modifying request (POST, PUT, PATCH, DELETE).
     *
     * @param Request $request The incoming request instance.
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
     * @param Request $request
     *
     * @return bool
     */
    public function isTokenMatch($request): bool
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        return is_string($request->session()->token()) &&
            is_string($token) &&
            hash_equals($request->session()->token(), $token);
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

        $config = config('session');

        $response->headers->setCookie($this->addCookie($request, $config));

        return $response;
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
        $token = Crypt::encrypt($request->session()->token());

        return new Cookie(
            'XSRF-TOKEN',
            $token,
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
     * @param Request $request The incoming request instance.
     * @param string $message The error message.
     * @return Response
     * @throws TokenMismatchException
     */
    protected function handleError(Request $request, string $message): Response
    {
        if ($request->isAjax() || $request->is('/api/*') || $request->wantsJson()) {
            throw new HttpResponseException(
                $message,
                Response::HTTP_UNAUTHORIZED
            );
        }

        throw new TokenMismatchException(Response::HTTP_UNAUTHORIZED, $message);
    }
}
