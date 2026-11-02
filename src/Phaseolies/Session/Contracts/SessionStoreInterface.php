<?php

namespace Phaseolies\Session\Contracts;

interface SessionStoreInterface
{
    /**
     * Load a session from the value of the session cookie
     *
     * @param string $cookieValue
     * @return array{id: string, data: array}|null
     */
    public function read(string $cookieValue): ?array;

    /**
     * Persist a session and return the value the session cookie must carry
     *
     * @param string $id
     * @param array $data
     * @return string
     */
    public function write(string $id, array $data): string;

    /**
     * Refresh the lifetime of a session without rewriting its data
     *
     * @param string $id
     * @return void
     */
    public function touch(string $id): void;

    /**
     * Remove a session
     *
     * @param string $id
     * @return void
     */
    public function destroy(string $id): void;

    /**
     * Remove every expired session
     *
     * @param int $maxLifetime Seconds
     * @return int Number of removed sessions
     */
    public function gc(int $maxLifetime): int;

    /**
     * Remove expired sessions, doing only a bounded slice of the work
     *
     * @param int $maxLifetime Seconds
     * @return int
     */
    public function sweep(int $maxLifetime): int;

    /**
     * Remove every stored session
     *
     * @return int
     */
    public function flush(): int;

    /**
     * Whether the session data lives in the cookie instead of on the server
     *
     * @return bool
     */
    public function isClientSide(): bool;
}
