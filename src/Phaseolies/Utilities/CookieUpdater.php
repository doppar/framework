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
     * @param string|null $value
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
     * @param int|string|\DateTimeInterface $expire
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
     * @param string $path
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
     * @param string|null $domain
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
     * @param bool $secure
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
     * @param bool $httpOnly
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
     * @param bool $raw
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
     * @param string|null $sameSite
     * @return self
     * @throws \InvalidArgumentException
     */
    public function withSameSite(?string $sameSite): self
    {
        $this->cookie = $this->cookie->withSameSite($sameSite);

        return $this;
    }

    /**
     * Set whether the cookie is partitioned (CHIPS)
     *
     * @param bool $partitioned
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
     * @return bool
     */
    public function update(): bool
    {
        return app(CookieJar::class)->store($this->cookie);
    }
}
