<?php

namespace Phaseolies\Middleware;

use Phaseolies\Support\Facades\Str;
use Phaseolies\Middleware\Contracts\Middleware;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Phaseolies\Http\Exceptions\TokenMismatchException;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Closure;

class CsrfTokenMiddleware implements Middleware
{
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
     * @return Phaseolies\Http\Response
     * @throws HttpException
     */
    public function __invoke(Request $request, Closure $next): Response
    {
        $token = $request->headers->get('X-CSRF-TOKEN') ?? $request->_token;

        if ($this->isReading($request) && !$request->has('_token')) {
            throw new TokenMismatchException(422, "CSRF Token not found");
        }

        if (
            $this->isReading($request) &&
            (!hash_equals($request->session()->token(), $token))
        ) {
            throw new TokenMismatchException(422, "Unauthorized, CSRF Token mismatched");
        }

        return $next($request);
    }

    /**
     * Checks if the request is a modifying request (POST, PUT, PATCH, DELETE).
     *
     * @param Request $request The incoming request instance.
     * @return bool
     */
    protected function isReading(Request $request): bool
    {
        return !in_array(Str::toUpper($request->method()), ['HEAD', 'GET', 'OPTIONS']);
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
        if ($request->isAjax()) {
            throw new HttpResponseException(
                $message,
                Response::HTTP_UNAUTHORIZED
            );
        }

        throw new TokenMismatchException(Response::HTTP_UNAUTHORIZED, $message);
    }
}
