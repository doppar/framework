<?php

namespace Phaseolies\Session\Stores;

use Phaseolies\Session\Contracts\SessionStoreInterface;
use Phaseolies\Session\Exceptions\SessionException;

class FileStore implements SessionStoreInterface
{
    /**
     * Pattern every generated session id matches.
     *
     * Anything else coming from a cookie is rejected before it can reach the
     * filesystem, which rules out path traversal and fixation with crafted ids.
     */
    public const ID_PATTERN = '/^[a-f0-9]{40}$/';

    /**
     * @param string $path
     * @param int $lifetime
     */
    public function __construct(protected string $path, protected int $lifetime = 7200)
    {
        $trimmed = rtrim($path, '/\\');

        // Keep a filesystem root ("/" or "C:\") intact instead of trimming it away.
        $this->path = ($trimmed === '' || str_ends_with($trimmed, ':')) ? $path : $trimmed;
    }

    /**
     * Load a session from the value of the session cookie
     *
     * @param string $cookieValue
     * @return array{id: string, data: array}|null
     */
    public function read(string $cookieValue): ?array
    {
        if (!self::isValidId($cookieValue)) {
            return null;
        }

        $file = $this->file($cookieValue);

        // No lock is taken: writes replace the file atomically, so a reader
        // sees either the old or the new session. File locks are mandatory on
        // Windows and would only make readers and writers collide.
        // PHP caches stat results; long-lived workers must not see stale times.
        clearstatcache(true, $file);

        $modified = @filemtime($file);
        $payload = $modified === false ? false : @file_get_contents($file);

        if ($payload === false) {
            return null;
        }

        if ($modified + $this->lifetime < time()) {
            @unlink($file);

            return null;
        }

        $data = $payload === '' ? null : @unserialize($payload);

        if (!is_array($data)) {
            // Empty, corrupted, or caught mid-write on a platform where the
            // write is not atomic: behave like an unknown session. The file is
            // left for gc() so a live session is never deleted by a racing read.
            return null;
        }

        return ['id' => $cookieValue, 'data' => $data];
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
        $this->assertValidId($id);

        $file = $this->file($id);
        $directory = dirname($file);

        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new SessionException('Unable to create the session storage directory.');
        }

        // Write to a private temp file and rename it over the target. Rename is
        // atomic, so a concurrent reader sees the old or the new session, never
        // a half-written one.
        $temp = $directory . '/.tmp_' . bin2hex(random_bytes(8));

        if (@file_put_contents($temp, serialize($data), LOCK_EX) === false) {
            @unlink($temp);

            throw new SessionException('Unable to write the session.');
        }

        // Permissions are a no-op on Windows, where the profile directory ACLs apply.
        @chmod($temp, 0600);

        if (!$this->replace($temp, $file)) {
            @unlink($temp);

            throw new SessionException('Unable to write the session.');
        }

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
        if (!self::isValidId($id)) {
            return;
        }

        $file = $this->file($id);

        // Expiry is the file's mtime, so skip the write while it is still fresh.
        clearstatcache(true, $file);

        $modified = @filemtime($file);

        if ($modified !== false && time() - $modified > min(60, intdiv($this->lifetime, 10))) {
            @touch($file);
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
        if (!self::isValidId($id)) {
            return;
        }

        $file = $this->file($id);

        if (@unlink($file) || !file_exists($file)) {
            return;
        }

        // Windows refuses to delete a file another process has open. The
        // session must still stop working, so empty it: an empty file reads
        // as an unknown session and gc() removes it later.
        @file_put_contents($file, '');
    }

    /**
     * Remove every expired session
     *
     * @param int $maxLifetime
     * @return int
     */
    public function gc(int $maxLifetime): int
    {
        $removed = 0;
        $cutoff = time() - $maxLifetime;

        if (!is_dir($this->path)) {
            return 0;
        }

        // Plain directory iteration instead of glob(): a storage path that
        // contains [ ] { } * or ? would make glob() match nothing.
        foreach (new \FilesystemIterator($this->path, \FilesystemIterator::SKIP_DOTS) as $entry) {
            $name = $entry->getFilename();

            if ($entry->isDir() && preg_match('/^[a-f0-9]{2}$/', $name) === 1) {
                $removed += $this->sweepDirectory($entry->getPathname(), $cutoff);
            } elseif ($entry->isFile() && str_starts_with($name, 'sess_') && $entry->getMTime() <= $cutoff && @unlink($entry->getPathname())) {
                // Left behind by the native PHP session handler this store replaced.
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Remove expired sessions from one random shard
     *
     * @param int $maxLifetime
     * @return int
     */
    public function sweep(int $maxLifetime): int
    {
        return $this->sweepDirectory(
            $this->path . '/' . sprintf('%02x', random_int(0, 255)),
            time() - $maxLifetime
        );
    }

    /**
     * Remove every stored session
     *
     * @return int
     */
    public function flush(): int
    {
        return $this->gc(-3600);
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

    /**
     * Whether the given value is a well formed session id.
     *
     * @param string $id
     * @return bool
     */
    public static function isValidId(string $id): bool
    {
        return preg_match(self::ID_PATTERN, $id) === 1;
    }

    /**
     * Delete the files of one directory last modified before the cutoff.
     *
     * @param string $directory
     * @param int $cutoff
     * @return int
     */
    protected function sweepDirectory(string $directory, int $cutoff): int
    {
        $removed = 0;

        if (!is_dir($directory)) {
            return 0;
        }

        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $file) {
            if ($file->isFile() && $file->getMTime() <= $cutoff && @unlink($file->getPathname())) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Move a freshly written temporary file over the session file.
     *
     * rename() is atomic and replaces the target on every supported platform,
     * but on Windows it can fail for a moment while antivirus or another
     * process holds the target open. Retry briefly, then fall back to writing
     * in place, which is not atomic but never loses the session.
     *
     * @param string $temp
     * @param string $file
     * @return bool
     */
    protected function replace(string $temp, string $file): bool
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($this->move($temp, $file)) {
                return true;
            }

            usleep(10000 * ($attempt + 1));
        }

        $payload = @file_get_contents($temp);
        @unlink($temp);

        return $payload !== false && @file_put_contents($file, $payload, LOCK_EX) !== false;
    }

    /**
     * @param string $from
     * @param string $to
     * @return bool
     */
    protected function move(string $from, string $to): bool
    {
        return @rename($from, $to);
    }

    /**
     * Absolute path of the file backing a session.
     *
     * @param string $id
     * @return string
     */
    protected function file(string $id): string
    {
        return $this->path . '/' . substr($id, 0, 2) . '/' . $id;
    }

    /**
     * @param string $id
     * @return void
     * @throws SessionException
     */
    protected function assertValidId(string $id): void
    {
        if (!self::isValidId($id)) {
            throw new SessionException('Invalid session id.');
        }
    }
}
