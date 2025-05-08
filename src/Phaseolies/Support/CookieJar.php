<?php

namespace Phaseolies\Support;

use Phaseolies\Http\Response\Cookie;

class CookieJar
{
    /**
     * Default cookie settings
     *
     * @var array
     */
    protected $defaults = [
        'expires' => 0,
        'path' => '/',
        'domain' => null,
        'secure' => null,
        'httponly' => true,
        'raw' => false,
        'samesite' => Cookie::SAMESITE_LAX,
        'partitioned' => false,
    ];

    /**
     * Create a new cookie instance
     *
     * @param string $name
     * @param string|null $value
     * @param array $options
     * @return Cookie
     */
    public function make(string $name, ?string $value = null, array $options = []): Cookie
    {
        $options = array_merge($this->defaults, $options);

        return Cookie::create(
            $name,
            $value,
            $options['expires'],
            $options['path'],
            $options['domain'],
            $options['secure'],
            $options['httponly'],
            $options['raw'],
            $options['samesite'],
            $options['partitioned']
        );
    }

    /**
     * Retrieve a cookie value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $_COOKIE[$key] ?? $default;
    }

    /**
     * Check if a cookie exists
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($_COOKIE[$key]);
    }

    /**
     * Store a cookie
     *
     * @param Cookie|string $name
     * @param string|null $value
     * @param array $options
     * @return void
     */
    public function store($name, $value = null, array $options = []): bool
    {
        if (!$name instanceof Cookie) {
            $name = $this->make($name, $value, $options);
        }

        return $this->setCookie($name);
    }

    /**
     * Remove a cookie
     *
     * @param string $name
     * @param array $options
     * @return void
     */
    public function remove(string $name, array $options = []): void
    {
        $options['expires'] = time() - 3600;
        $options['value'] = '';
        $this->store($name, null, $options);
    }

    /**
     * Store a cookie that lasts "forever" (5 years)
     *
     * @param string $name
     * @param string $value
     * @param array $options
     * @return void
     */
    public function forever(string $name, string $value, array $options = []): void
    {
        $options['expires'] = time() + 60 * 60 * 24 * 365 * 5;
        $this->store($name, $value, $options);
    }

    /**
     * Set a cookie using the Cookie object
     *
     * @param Cookie $cookie
     * @return bool
     */
    protected function setCookie(Cookie $cookie): bool
    {
        if ($cookie->isRaw()) {
            $name = $cookie->getName();
            $value = $cookie->getValue() ?? '';
        } else {
            $name = str_replace(Cookie::RESERVED_CHARS_FROM, Cookie::RESERVED_CHARS_TO, $cookie->getName());
            $value = rawurlencode($cookie->getValue() ?? '');
        }

        $options = [
            'expires' => $cookie->getExpiresTime(),
            'path' => $cookie->getPath(),
            'domain' => $cookie->getDomain(),
            'secure' => $cookie->isSecure(),
            'httponly' => $cookie->isHttpOnly(),
        ];

        if (null !== $cookie->getSameSite()) {
            $options['samesite'] = $cookie->getSameSite();
        }

        if ($cookie->isPartitioned()) {
            $options['partitioned'] = true;
        }

        if (PHP_VERSION_ID < 70300) {
            // For PHP < 7.3
            $path = $options['path'];
            if (isset($options['samesite'])) {
                $path .= '; samesite=' . $options['samesite'];
            }
            if (isset($options['partitioned'])) {
                $path .= '; partitioned';
            }
            return setcookie(
                $name,
                $value,
                $options['expires'],
                $path,
                $options['domain'],
                $options['secure'],
                $options['httponly']
            );
        }

        // For PHP >= 7.3
        return setcookie($name, $value, $options);
    }

    /**
     * Get all cookies that were sent with the current request
     *
     * @param bool $decodeValues Whether to URL-decode cookie values (default: true)
     * @return array
     */
    public function all(bool $decodeValues = true): array
    {
        if (!$decodeValues) {
            return $_COOKIE;
        }

        $cookies = [];
        foreach ($_COOKIE as $name => $value) {
            $cookies[$name] = is_string($value) ? rawurldecode($value) : $value;
        }

        return $cookies ?? [];
    }
}
