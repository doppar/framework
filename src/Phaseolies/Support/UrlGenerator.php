<?php

namespace Phaseolies\Support;

use Phaseolies\Http\Exceptions\RouteNameNotFoundException;

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
    public function __construct(?string $baseUrl = null, bool $secure = false)
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
    public function enqueue(string $path = '/', ?bool $secure = null)
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
        return request()->fullUrl();
    }

    /**
     * Get the current URL without query parameters.
     *
     * @return string
     */
    public function current()
    {
        return $this->to(request()->uri())->make();
    }

    /**
     * Generate a URL for a named route.
     *
     * @param string $name The route name
     * @param array|string $parameters Route parameters
     * @param bool|null $secure Whether to force HTTPS
     * @return string
     */
    public function route(string $name, array $parameters = [], ?bool $secure = null)
    {
        $path = app('route')->route($name, $parameters);

        if (empty($path)) {
            throw new RouteNameNotFoundException(404, "Route [ {$name} ] not found");
        }

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
    public function to(string $path = '/', array $parameters = [], ?bool $secure = null)
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
    public function withQuery(array|string $query = [])
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
    public function withSignature(int $expiration = 3600)
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
    public function withFragment(string $fragment = '')
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
            $baseUrl = $this->baseUrl;
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
    public function signed(string $path = '/', array $parameters = [], int $expiration = 3600, ?bool $secure = null)
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
     * This method is taken from PHP Laravel framework
     * Determine if the given path is a valid URL.
     *
     * @param  string  $path
     * @return bool
     */
    public function isValid($path)
    {
        if (! preg_match('~^(#|//|https?://|(mailto|tel|sms):)~', $path)) {
            return filter_var($path, FILTER_VALIDATE_URL) !== false;
        }

        return true;
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
