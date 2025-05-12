<?php

namespace Phaseolies\Middleware;

use Phaseolies\Middleware\Contracts\Middleware;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Closure;

class CacheHeaders implements Middleware
{
    /**
     * Set the cache headers on the incoming HTTP Response.
     *
     * @param Request $request
     * @param \Closure(\Phaseolies\Http\Request) $next
     * @param mixed $directives
     * @return Phaseolies\Http\Response
     */
    public function __invoke(Request $request, Closure $next, ...$directives): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            return $response;
        }

        $this->setHeaders($response, $this->parseOptions($directives));

        return $response;
    }

    /**
     * Set the cache headers on the response.
     *
     * @param \Phaseolies\Http\Response $response
     * @param array $options
     * @return void
     */
    protected function setHeaders($response, array $options): void
    {
        if (! empty($options['etag']) && $response->getBody() !== false) {
            $response->setEtag(md5($response->getBody()));
        }

        if (! empty($options['cache_control'])) {
            $response->headers->set('Cache-Control', $options['cache_control'], true);
        }

        if (! empty($options['pragma'])) {
            $response->headers->set('Pragma', $options['pragma']);
        }

        if (! empty($options['expires'])) {
            $response->headers->set('Expires', $options['expires']);
        }
    }

    /**
     * Parse the middleware options.
     *
     * @param  array  $options
     * @return array
     */
    protected function parseOptions(array $options)
    {
        $parsed = [
            'etag' => false,
            'cache_control' => null,
            'pragma' => null,
            'expires' => null,
        ];

        $directives = explode(';', implode(';', $options));

        $cacheControl = [];

        foreach ($directives as $directive) {
            $directive = str_replace('_', '-', trim($directive));
            if ($directive === 'etag') {
                $parsed['etag'] = true;
            } elseif (str_starts_with($directive, 'expires=')) {
                $parsed['expires'] = substr($directive, 8);
            } elseif (str_starts_with($directive, 'pragma=')) {
                $parsed['pragma'] = substr($directive, 7);
            } else {
                $cacheControl[] = $directive;
            }
        }

        if (! empty($cacheControl)) {
            $parsed['cache_control'] = implode(', ', $cacheControl);
        }

        return $parsed;
    }
}
