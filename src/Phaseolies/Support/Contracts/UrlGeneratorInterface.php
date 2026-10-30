<?php

namespace Phaseolies\Support\Contracts;

interface UrlGeneratorInterface
{
    /**
     * Generate a full URL for the given path.
     *
     * @param string $path
     * @param bool|null $secure
     * @return string
     */
    public function enqueue(string $path = '/', ?bool $secure = null): string;

    /**
     * Generate the current full URL, including query parameters.
     *
     * @return string
     */
    public function full(): string;

    /**
     * Get the current URL without query parameters.
     *
     * @return string
     */
    public function current(): string;

    /**
     * Generate a URL for a named route.
     *
     * @param string $name
     * @param array|string|int $parameters
     * @param bool|null $secure
     * @return string
     */
    public function route(string $name, array|string|int $parameters = [], ?bool $secure = null): string;

    /**
     * Set the path and optional parameters.
     *
     * @param string $path
     * @param array|string|int $parameters
     * @param bool|null $secure
     * @return self
     */
    public function to(string $path = '/', array|string|int $parameters = [], ?bool $secure = null): self;

    /**
     * Add query parameters.
     *
     * @param array|string $query
     * @return self
     */
    public function withQuery(array|string $query = []): self;

    /**
     * Add a signature to the generated URL.
     *
     * @param int $expiration
     * @return self
     */
    public function withSignature(int $expiration = 3600): self;

    /**
     * Add a URL fragment.
     *
     * @param string $fragment
     * @return self
     */
    public function withFragment(string $fragment = ''): self;

    /**
     * Generate the final URL.
     *
     * @return string
     */
    public function make(): string;

    /**
     * Generate a signed URL.
     *
     * @param string $path
     * @param array|string|int $parameters
     * @param int $expiration
     * @param bool|null $secure
     * @return string
     */
    public function signed(string $path = '/', array|string|int $parameters = [], int $expiration = 3600, ?bool $secure = null): string;

    /**
     * Determine whether a value is a valid URL.
     *
     * @param mixed $path
     * @return bool
     */
    public function isValid(mixed $path): bool;

    /**
     * Get the configured base URL.
     *
     * @return string
     */
    public function base(): string;

    /**
     * Set the HTTPS preference.
     *
     * @param bool $secure
     * @return self
     */
    public function setSecure(bool $secure): self;
}
