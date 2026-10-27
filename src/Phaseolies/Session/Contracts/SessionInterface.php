<?php

namespace Phaseolies\Session\Contracts;

interface SessionInterface
{
    /**
     * Get a session value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null): mixed;

    /**
     * Get a session value by key and remove it.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, $default = null): mixed;

    /**
     * Get a peek value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getPeek(string $key, $default = null): mixed;

    /**
     * Put a value into the session peek data.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function putPeek(string $key, $value): void;

    /**
     * Remove all session peek data.
     *
     * @return void
     */
    public function flushPeek(): void;

    /**
     * Put one or more values into the session.
     *
     * @param string|array $key
     * @param mixed $value
     * @return void
     */
    public function put(string|array $key, $value = null): void;

    /**
     * Determine whether a session key exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove a session key.
     *
     * @param string $key
     * @return void
     */
    public function forget(string $key): void;

    /**
     * Remove all session data.
     *
     * @return void
     */
    public function flush(): void;

    /**
     * Regenerate the session ID.
     *
     * @param bool $deleteOldSession
     * @return void
     */
    public function regenerate(bool $deleteOldSession = true): void;

    /**
     * Get all session data.
     *
     * @return array
     */
    public function all(): array;

    /**
     * Get the session ID.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Set the session ID.
     *
     * @param string $id
     * @return void
     */
    public function setId(string $id): void;

    /**
     * Destroy the session.
     *
     * @return void
     */
    public function destroy(): void;

    /**
     * Get the CSRF token.
     *
     * @return string|null
     */
    public function token(): ?string;

    /**
     * Set a flash message.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function flash(string $key, $value): void;

    /**
     * Keep flash data for the next request.
     *
     * @param string|array $keys
     * @return void
     */
    public function reflash($keys): void;

    /**
     * Invalidate the session.
     *
     * @return void
     */
    public function invalidate(): void;

    /**
     * Regenerate the CSRF token.
     *
     * @return void
     */
    public function regenerateToken(): void;
}
