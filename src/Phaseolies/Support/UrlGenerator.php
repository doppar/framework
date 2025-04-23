<?php

namespace Phaseolies\Support;

class UrlGenerator
{
    /**
     * The base URL for generating URLs.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Indicates whether the generated URLs should use HTTPS.
     *
     * @var bool
     */
    protected $secure;

    /**
     * The path for the URL.
     *
     * @var string
     */
    protected $path = '/';

    /**
     * The query parameters for the URL.
     *
     * @var array
     */
    protected $query = [];

    /**
     * The fragment for the URL.
     *
     * @var string
     */
    protected $fragment = '';

    /**
     * The expiration time for signed URLs.
     *
     * @var int
     */
    protected $expiration = 0;

    /**
     * Create a new UrlGenerator instance.
     *
     * @param string|null $baseUrl The base URL for generating URLs
     * @param bool $secure Whether to use HTTPS by default
     */
    public function __construct($baseUrl = null, $secure = false)
    {
        $this->baseUrl = $baseUrl ? rtrim($baseUrl, '/') : $this->determineBaseUrl();
        $this->secure = $secure;
    }

    /**
     * Determine the base URL to use.
     *
     * @return string
     */
    protected function determineBaseUrl()
    {
        return \base_url();
    }

    /**
     * Generate a full URL for the given path.
     *
     * @param string $path The path to append
     * @param bool|null $secure Whether to force HTTPS
     * @return string
     */
    public function enqueue($path = '/', $secure = null)
    {
        return $this->to($path, [], $secure)->make();
    }

    /**
     * Generate a full URL without query parameters.
     *
     * @return string
     */
    public function full()
    {
        return $this->to(request()->uri())->make();
    }

    /**
     * Get the current URL without query parameters.
     *
     * @return string
     */
    public function current()
    {
        $path = parse_url(request()->uri(), PHP_URL_PATH) ?: '/';

        return $this->to($path)->make();
    }

    /**
     * Generate a URL for a named route.
     *
     * @param string $name The route name
     * @param array|string $parameters Route parameters
     * @param bool|null $secure Whether to force HTTPS
     * @return string
     */
    public function route($name, $parameters = [], $secure = null)
    {
        $path = app('route')->route($name, $parameters);
        return $this->enqueue($path, $secure);
    }

    /**
     * Set the path and optional parameters.
     *
     * @param string $path
     * @param array|string $parameters
     * @param bool|null $secure
     * @return $this
     */
    public function to($path = '/', $parameters = [], $secure = null)
    {
        $this->path = ltrim($path, '/');

        if (!is_null($secure)) {
            $this->secure = $secure;
        }

        if (!empty($parameters)) {
            $this->withQuery($parameters);
        }

        return $this;
    }

    /**
     * Add query parameters.
     *
     * @param string|array $query
     * @return $this
     */
    public function withQuery($query = [])
    {
        if (is_string($query)) {
            parse_str($query, $parsedQuery);
            $this->query = array_merge($this->query, $parsedQuery);
        } elseif (is_array($query)) {
            $this->query = array_merge($this->query, $query);
        }

        return $this;
    }

    /**
     * Add a signature.
     *
     * @param int $expiration
     * @return $this
     */
    public function withSignature($expiration = 3600)
    {
        $this->expiration = $expiration;
        return $this;
    }

    /**
     * Add a fragment.
     *
     * @param string $fragment
     * @return $this
     */
    public function withFragment($fragment = '')
    {
        $this->fragment = ltrim($fragment, '#');
        return $this;
    }

    /**
     * Generate the final URL.
     *
     * @return string
     */
    public function make()
    {
        $scheme = $this->secure ? 'https://' : 'http://';
        $baseUrl = preg_replace('#^https?://#', '', $this->baseUrl);

        // Ensure we have a base URL
        if (empty($baseUrl)) {
            $baseUrl = function_exists('request') ? request()->host('host') : 'localhost';
        }

        $url = $scheme . $baseUrl . '/' . ltrim($this->path, '/');

        // Add query parameters
        $queryParameters = $this->query;
        if ($this->expiration > 0) {
            $queryParameters['expires'] = time() + $this->expiration;
            $queryParameters['signature'] = $this->createSignature($queryParameters);
        }

        if (!empty($queryParameters)) {
            $url .= '?' . http_build_query($queryParameters);
        }

        // Add fragment
        if (!empty($this->fragment)) {
            $url .= '#' . $this->fragment;
        }

        return $url;
    }

    /**
     * Generate a signed URL.
     *
     * @param string $path
     * @param array $parameters
     * @param int $expiration
     * @param bool|null $secure
     * @return string
     */
    public function signed($path = '/', array $parameters = [], $expiration = 3600, $secure = null)
    {
        return $this->to($path, $parameters, $secure)
            ->withSignature($expiration)
            ->make();
    }

    /**
     * Create a signature.
     *
     * @param array $parameters
     * @return string
     */
    protected function createSignature(array $parameters)
    {
        $secret = config('app.key');
        return hash_hmac('sha256', http_build_query($parameters), $secret);
    }

    /**
     * Validate a URL.
     *
     * @param string $url
     * @return bool
     */
    public function isValid(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Get the base URL.
     *
     * @return string
     */
    public function base()
    {
        return $this->baseUrl;
    }

    /**
     * Set HTTPS preference.
     *
     * @param bool $secure
     * @return $this
     */
    public function setSecure(bool $secure)
    {
        $this->secure = $secure;
        return $this;
    }
}
