<?php

namespace Phaseolies\Middleware;

use Phaseolies\Session\Contracts\CommittableSession;
use Phaseolies\Middleware\Contracts\Middleware;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Closure;

class StatelessSession implements Middleware
{
    /**
     * Keep the session of this request in memory only
     *
     * @param Request $request
     * @param \Closure(\Phaseolies\Http\Request): \Phaseolies\Http\Response $next
     * @return \Phaseolies\Http\Response
     */
    public function __invoke(Request $request, Closure $next): Response
    {
        $session = $request->session();

        if ($session instanceof CommittableSession) {
            $session->disable();
        }

        return $next($request);
    }
}
