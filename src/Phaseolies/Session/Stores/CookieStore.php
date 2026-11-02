<?php

namespace Phaseolies\Session\Stores;

use Phaseolies\Session\Contracts\SessionStoreInterface;
use Phaseolies\Session\Exceptions\SessionException;
use Phaseolies\Support\Contracts\EncryptionInterface;

class CookieStore implements SessionStoreInterface
{
    /**
     * Browsers drop cookies above roughly 4096 bytes, so refuse to emit one
     * instead of silently losing the session.
     */
    public const MAX_COOKIE_BYTES = 4000;

    /**
     * Prefix of the authenticated envelope produced by the encrypter.
     */
    protected const ENVELOPE_PREFIX = 'v2:';

    /**
     * @param EncryptionInterface $encrypter Authenticated encryption (AES-256-GCM)
     * @param int $lifetime Seconds
     */
    public function __construct(protected EncryptionInterface $encrypter, protected int $lifetime = 7200)
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
        // Only the authenticated (AES-256-GCM) envelope is accepted. The
        // encrypter still understands the old unauthenticated CBC format, which
        // must never be trusted for session data.
        if (!str_starts_with($cookieValue, self::ENVELOPE_PREFIX)) {
            return null;
        }

        try {
            $decoded = $this->encrypter->decrypt($cookieValue);
        } catch (\Throwable) {
            return null;
        }

        if (
            !is_array($decoded) ||
            !isset($decoded['id'], $decoded['exp'], $decoded['data']) ||
            !is_string($decoded['id']) ||
            !FileStore::isValidId($decoded['id']) ||
            !is_array($decoded['data']) ||
            !is_int($decoded['exp']) ||
            $decoded['exp'] < time()
        ) {
            return null;
        }

        return ['id' => $decoded['id'], 'data' => $decoded['data']];
    }

    /**
     * Persist a session and return the value the session cookie must carry
     *
     * @param string $id
     * @param array $data
     * @return string
     * @throws SessionException
     */
    public function write(string $id, array $data): string
    {
        $payload = ['id' => $id, 'exp' => time() + $this->lifetime, 'data' => $data];

        if (json_encode($payload) === false) {
            throw new SessionException('Session data cannot be stored in a cookie because it is not JSON serializable.');
        }

        $value = $this->encrypter->encrypt($payload);

        if (strlen($value) > self::MAX_COOKIE_BYTES) {
            throw new SessionException(sprintf(
                'Session data is too large for the cookie session driver (%d of %d bytes). Largest keys: %s. Store less in the session or use the file driver.',
                strlen($value),
                self::MAX_COOKIE_BYTES,
                $this->largestKeys($data)
            ));
        }

        return $value;
    }

    /**
     * Describe the keys that take up the most room, by name and size only.
     *
     * @param array $data
     * @return string
     */
    protected function largestKeys(array $data): string
    {
        $sizes = array_map(static fn($value): int => strlen((string) json_encode($value)), $data);

        arsort($sizes);

        return implode(', ', array_map(
            static fn($key, $size): string => "{$key} ({$size}B)",
            array_keys($top = array_slice($sizes, 0, 3, true)),
            $top
        ));
    }

    /**
     * Refresh the lifetime of a session without rewriting its data
     *
     * @param string $id
     * @return void
     */
    public function touch(string $id): void
    {
        //
    }

    /**
     * Remove a session
     *
     * @param string $id
     * @return void
     */
    public function destroy(string $id): void
    {
        //
    }

    /**
     * Remove expired sessions
     *
     * @param int $maxLifetime
     * @return int
     */
    public function gc(int $maxLifetime): int
    {
        return 0;
    }

    /**
     * Remove expired sessions, doing only a bounded slice of the work
     *
     * @param int $maxLifetime
     * @return int
     */
    public function sweep(int $maxLifetime): int
    {
        return 0;
    }

    /**
     * Remove every stored session
     *
     * @return int
     */
    public function flush(): int
    {
        return 0;
    }

    /**
     * Whether the session data lives in the cookie instead of on the server
     *
     * @return bool
     */
    public function isClientSide(): bool
    {
        return true;
    }
}
