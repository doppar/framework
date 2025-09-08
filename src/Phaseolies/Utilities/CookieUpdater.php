<?php

namespace Phaseolies\Utilities;

use Phaseolies\Support\CookieJar;
use Phaseolies\Http\Response\Cookie;

class CookieUpdater
{
    /**
     * The cookie instance being modified
     *
     * @var Cookie
     */
    protected Cookie $cookie;

    /**
     * Initialize with a Cookie instance
     */
    public function __construct(Cookie $cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * Update the cookie's value
     *
     * @param string|null $value The new cookie value
     * @return self
     */
    public function withValue(?string $value): self
    {
        $this->cookie = $this->cookie->withValue($value);

        return $this;
    }

    /**
     * Update the expiration time
     *
     * @param int|string|\DateTimeInterface $expire Timestamp, DateTime or strtotime string
     * @return self
     */
    public function withExpires($expire): self
    {
        $this->cookie = $this->cookie->withExpires($expire);

        return $this;
    }

    /**
     * Update the cookie path
     *
     * @param string $path The path on the server where the cookie is available
     * @return self
     */
    public function withPath(string $path): self
    {
        $this->cookie = $this->cookie->withPath($path);

        return $this;
    }

    /**
     * Update the cookie domain
     *
     * @param string|null $domain The domain that the cookie is available to
     * @return self
     */
    public function withDomain(?string $domain): self
    {
        $this->cookie = $this->cookie->withDomain($domain);

        return $this;
    }

    /**
     * Set whether the cookie is HTTPS-only
     *
     * @param bool $secure Whether the cookie should only be sent over HTTPS
     * @return self
     */
    public function withSecure(bool $secure = true): self
    {
        $this->cookie = $this->cookie->withSecure($secure);

        return $this;
    }

    /**
     * Set whether the cookie is HTTP-only
     *
     * @param bool $httpOnly Whether the cookie is accessible only through HTTP
     * @return self
     */
    public function withHttpOnly(bool $httpOnly = true): self
    {
        $this->cookie = $this->cookie->withHttpOnly($httpOnly);

        return $this;
    }

    /**
     * Set whether the cookie value should be raw
     *
     * @param bool $raw Whether to disable URL encoding
     * @return self
     * @throws \InvalidArgumentException If cookie name contains invalid characters
     */
    public function withRaw(bool $raw = true): self
    {
        $this->cookie = $this->cookie->withRaw($raw);

        return $this;
    }

    /**
     * Set the SameSite attribute
     *
     * @param string|null $sameSite One of: 'lax', 'strict', 'none', or null
     * @return self
     * @throws \InvalidArgumentException For invalid SameSite values
     */
    public function withSameSite(?string $sameSite): self
    {
        $this->cookie = $this->cookie->withSameSite($sameSite);

        return $this;
    }

    /**
     * Set whether the cookie is partitioned (CHIPS)
     *
     * @param bool $partitioned Whether the cookie should be partitioned
     * @return self
     */
    public function withPartitioned(bool $partitioned = true): self
    {
        $this->cookie = $this->cookie->withPartitioned($partitioned);

        return $this;
    }

    /**
     * Persist the modified cookie to the response
     *
     * @return bool True on successful header set, false otherwise
     */
    public function update(): bool
    {
        return app(CookieJar::class)->store($this->cookie);
    }
}
