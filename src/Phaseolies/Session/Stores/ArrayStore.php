<?php

namespace Phaseolies\Session\Stores;

use Phaseolies\Session\Contracts\SessionStoreInterface;

class ArrayStore implements SessionStoreInterface
{
    /**
     * Stored sessions keyed by id.
     *
     * @var array<string, array{data: array, expires: int}>
     */
    protected array $sessions = [];

    /**
     * @param int $lifetime Seconds
     */
    public function __construct(protected int $lifetime = 7200)
    {
        //
    }

    /**
     * Load a session from the value of the session cookie
     *
     * @param string $cookieValue
     * @return array{id: string, data: array}|null
     */
    public function read(string $cookieValue): ?array
    {
        $session = $this->sessions[$cookieValue] ?? null;

        if ($session === null) {
            return null;
        }

        if ($session['expires'] < time()) {
            unset($this->sessions[$cookieValue]);

            return null;
        }

        return ['id' => $cookieValue, 'data' => $session['data']];
    }

    /**
     * Persist a session and return the value the session cookie must carry
     *
     * @param string $id
     * @param array $data
     * @return string
     */
    public function write(string $id, array $data): string
    {
        $this->sessions[$id] = ['data' => $data, 'expires' => time() + $this->lifetime];

        return $id;
    }

    /**
     * Refresh the lifetime of a session without rewriting its data
     *
     * @param string $id
     * @return void
     */
    public function touch(string $id): void
    {
        if (isset($this->sessions[$id])) {
            $this->sessions[$id]['expires'] = time() + $this->lifetime;
        }
    }

    /**
     * Remove a session
     *
     * @param string $id
     * @return void
     */
    public function destroy(string $id): void
    {
        unset($this->sessions[$id]);
    }

    /**
     * Remove expired sessions
     *
     * @param int $maxLifetime
     * @return int
     */
    public function gc(int $maxLifetime): int
    {
        $removed = 0;

        foreach ($this->sessions as $id => $session) {
            if ($session['expires'] < time()) {
                unset($this->sessions[$id]);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Remove expired sessions, doing only a bounded slice of the work
     *
     * @param int $maxLifetime
     * @return int
     */
    public function sweep(int $maxLifetime): int
    {
        return $this->gc($maxLifetime);
    }

    /**
     * Remove every stored session
     *
     * @return int
     */
    public function flush(): int
    {
        $count = count($this->sessions);
        $this->sessions = [];

        return $count;
    }

    /**
     * Whether the session data lives in the cookie instead of on the server
     *
     * @return bool
     */
    public function isClientSide(): bool
    {
        return false;
    }
}
