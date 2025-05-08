<?php

namespace Phaseolies\Http\Support;

trait RequestParser
{
    /**
     * Retrieves the client's IP address.
     *
     * @return string|null The IP address or null if not available.
     */
    public function ip(): ?string
    {
        if ($this->trustedHeaderSet & self::HEADER_FORWARDED) {
            $forwarded = $this->headers->get('FORWARDED');
            if ($forwarded) {
                $parts = explode(';', $forwarded);
                foreach ($parts as $part) {
                    $keyValue = explode('=', trim($part), 2);
                    if (count($keyValue) === 2 && $keyValue[0] === 'for') {
                        return $keyValue[1];
                    }
                }
            }
        }

        if ($this->trustedHeaderSet & self::HEADER_X_FORWARDED_FOR) {
            $xForwardedFor = $this->headers->get('X_FORWARDED_FOR');
            if ($xForwardedFor) {
                $ips = explode(',', $xForwardedFor);
                return trim($ips[0]);
            }
        }

        return $this->server->get('REMOTE_ADDR');
    }

    /**
     * Retrieves the full request URI.
     *
     * @return string The full URI.
     */
    public function uri(): string
    {
        return $this->getRequestUri();
    }

    /**
     * Retrieves the server data.
     *
     * @return array The server data.
     */
    public function server(): array
    {
        return $this->server->all();
    }

    /**
     * Retrieves the request headers.
     *
     * @return array<string, string> The request headers.
     */
    public function headers(): array
    {
        return $this->headers->all();
    }

    /**
     * Retrieves a specific request header.
     *
     * @param string $name The header name.
     * @return string|null The header value or null if not found.
     */
    public function header(string $name): ?string
    {
        return $this->headers->get($name);
    }

    /**
     * Retrieves the request scheme (http or https).
     *
     * @return string The request scheme.
     */
    public function scheme(): string
    {
        return $this->server->get('HTTPS') === 'on' ? 'https' : 'http';
    }

    /**
     * Retrieves the request URL (scheme + host + URI).
     *
     * @return string The full request URL.
     */
    public function url(): string
    {
        return $this->scheme() . '://' . $this->host() . $this->uri();
    }

    /**
     * Retrieves the request query parameter value by key.
     *
     * @param string|null $key The key to retrieve the query parameter value.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The value of the query parameter, or the default value if not found.
     */
    public function query(?string $key = null, $default = null): mixed
    {
        $queryString = $this->getQueryString();
        parse_str($queryString, $queryParams);

        if ($key !== null) {
            return $queryParams[$key] ?? $default;
        }

        return $queryParams;
    }

    /**
     * Retrieves the full request URL including query string.
     *
     * @return string The full URL with query string
     */
    public function fullUrl(): string
    {
        $queryString = $this->getQueryString();

        return $this->url() . ($queryString ? '?' . $queryString : '');
    }

    /**
     * Retrieves the raw body content of the request.
     *
     * @return string|resource|false|null The raw body content.
     */
    public function content()
    {
        return $this->getContent();
    }

    /**
     * Retrieves the HTTP method used for the request.
     *
     * @return string The HTTP method in lowercase.
     */
    public function method(): string
    {
        return strtolower($this->getMethod());
    }

    /**
     * Retrieves the request cookies.
     *
     * @return array|null The cookies.
     */
    public function cookie(): ?array
    {
        return $this->cookies->all();
    }

    /**
     * Retrieves the request user agent.
     *
     * @return string|null The user agent or null if not available.
     */
    public function userAgent(): ?string
    {
        return $this->headers->get('USER_AGENT');
    }

    /**
     * Retrieves the request referer.
     *
     * @return string|null The referer or null if not available.
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
    public function isPjax()
    {
        return $this->headers->get('X-PJAX') == true;
    }

    /**
     * Retrieves the request content type.
     *
     * @return string|null The content type or null if not available.
     */
    public function contentType(): ?string
    {
        return $this->headers->get('CONTENT_TYPE');
    }

    /**
     * Retrieves the request content length.
     *
     * @return int|null The content length or null if not available.
     */
    public function contentLength(): ?int
    {
        $length = $this->headers->get('CONTENT_LENGTH');

        return $length !== null ? (int)$length : null;
    }

    /**
     * Checks if the current request path matches a given pattern.
     *
     * @param string $pattern The pattern to match against (e.g., 'api/*', 'admin/*')
     * @return bool True if the path matches the pattern, false otherwise
     */
    public function is(string $pattern): bool
    {
        $path = $this->uri();

        $pattern = str_replace('\*', '.*', preg_quote($pattern, '#'));

        return (bool) preg_match('#^' . $pattern . '$#i', $path);
    }

    /**
     * Checking a Request is API Request or not
     * @return bool
     */
    public function isApiRequest(): bool
    {
        if ($this->is('/api/*')) {
            return true;
        }

        return false;
    }
}
