<?php

namespace Phaseolies\Http\Support;

use Phaseolies\Utilities\PathMatcher;

trait RequestParser
{
    /**
     * Retrieves the client's IP address.
     *
     * Forwarded headers are only honoured when the request comes from a trusted proxy.
     *
     * @return string|null
     */
    public function ip(): ?string
    {
        return $this->getClientIp();
    }

    /**
     * Get all client IP addresses, with trusted proxies stripped.
     * The original client IP is the first element of the array.
     *
     * @return array<int, string>
     */
    public function ips(): array
    {
        return array_values(array_filter($this->getClientIps(), fn($ip) => $ip !== null && $ip !== ''));
    }

    /**
     * Retrieves the full request URI.
     *
     * @return string
     */
    public function uri(): string
    {
        return $this->getRequestUri();
    }

    /**
     * Retrieves the server data.
     *
     * @return array
     */
    public function server(): array
    {
        return $this->server->all();
    }

    /**
     * Retrieves the request headers.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers->all();
    }

    /**
     * Retrieves a specific request header.
     *
     * @param string $name
     * @return string|null.
     */
    public function header(string $name): ?string
    {
        return $this->headers->get($name);
    }

    /**
     * Retrieves the request scheme (http or https).
     *
     * @return string
     */
    public function scheme(): string
    {
        return $this->getScheme();
    }

    /**
     * Retrieves the request URL (scheme + host + URI).
     *
     * @return string
     */
    public function url(): string
    {
        return $this->scheme() . '://' . $this->host() . $this->uri();
    }

    /**
     * Retrieves the request query parameter value by key.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function query(?string $key = null, $default = null): mixed
    {
        $queryString = $this->getQueryString();

        $queryParams = [];
        if ($queryString !== null) {
            parse_str($queryString, $queryParams);
        }

        if ($key !== null) {
            return $queryParams[$key] ?? $default;
        }

        return $queryParams;
    }

    /**
     * Retrieves the full request URL including query string.
     *
     * @return string
     */
    public function fullUrl(): string
    {
        $queryString = $this->getQueryString();

        return $this->url() . ($queryString ? '?' . $queryString : '');
    }

    /**
     * Retrieves the raw body content of the request.
     *
     * @return string|resource|false|null
     */
    public function content()
    {
        return $this->getContent();
    }

    /**
     * Retrieves the HTTP method used for the request.
     *
     * @return string
     */
    public function method(): string
    {
        return strtolower($this->getMethod());
    }

    /**
     * Retrieves the request cookies.
     *
     * @return array|null
     */
    public function cookie(): ?array
    {
        return $this->cookies->all();
    }

    /**
     * Retrieves the request user agent.
     *
     * @return string|null
     */
    public function userAgent(): ?string
    {
        return $this->headers->get('USER_AGENT');
    }

    /**
     * Retrieves the request referer.
     *
     * @return string|null
     */
    public function referer(): ?string
    {
        return $this->headers->get('REFERER');
    }

    /**
     * Checks whether the request is secure or not.
     *
     * This method can read the client protocol from the "X-Forwarded-Proto" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Proto" header must contain the protocol: "https" or "http".
     */
    public function isSecure(): bool
    {
        if ($this->isFromTrustedProxy() && $proto = $this->getTrustedValues(self::HEADER_X_FORWARDED_PROTO)) {
            return \in_array(strtolower($proto[0]), ['https', 'on', 'ssl', '1'], true);
        }

        $https = $this->server->get('HTTPS');

        return $https && 'off' !== strtolower($https);
    }

    /**
     * Returns true if the request is an XMLHttpRequest.
     *
     * It works if your JavaScript library sets an X-Requested-With HTTP header.
     * It is known to work with common JavaScript frameworks:
     *
     * @see https://wikipedia.org/wiki/List_of_Ajax_frameworks#JavaScript
     */
    public function isAjax(): bool
    {
        return 'XMLHttpRequest' == $this->headers->get('X-Requested-With');
    }

    /**
     * Determine if the request is the result of a PJAX call.
     *
     * @return bool
     */
    public function isPjax(): bool
    {
        return $this->headers->get('X-PJAX') == true;
    }

    /**
     * Retrieves the request content type.
     *
     * @return string|null
     */
    public function contentType(): ?string
    {
        return $this->headers->get('CONTENT_TYPE');
    }

    /**
     * Retrieves the request content length.
     *
     * @return int|null
     */
    public function contentLength(): ?int
    {
        $length = $this->headers->get('CONTENT_LENGTH');

        return $length !== null ? (int)$length : null;
    }

    /**
     * Checks if the current request path matches a given pattern.
     *
     * @param string $pattern
     * @return bool
     */
    public static function is(string $pattern): bool
    {
        return PathMatcher::matches($pattern, request()->uri());
    }

    /**
     * Checking a Request is API Request or not
     *
     * @return bool
     */
    public function isApiRequest(): bool
    {
        return $this->is('/api/*');
    }

    /**
     * Get the current request route name
     *
     * @return string|bool|null
     */
    public static function route(?string $name = null): string|bool|null
    {
        $routeName = array_search(request()->uri(), app('route')->getRouteNames(), true) ?: null;

        if (\is_null($name)) {
            return $routeName;
        }

        return $routeName === $name;
    }
}
