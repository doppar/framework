<?php

namespace Phaseolies\Middleware;

use Closure;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Middleware\Contracts\Middleware;

class AuthenticateSession implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $actor
     * @return Response
     */
    public function __invoke(Request $request, Closure $next, ?string $actor = null): Response
    {
        $auth = app('auth')->actor($actor ?: null);

        // A stale session is signed out here; the request goes on as a guest,
        // so the "auth" middleware of a protected route sends it to sign in.
        $auth->isSessionCurrent();

        $response = $next($request);

        // Keep this device signed in when this request changed the password.
        $auth->refreshSessionFingerprint();

        return $response;
    }
}
