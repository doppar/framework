<?php

namespace Phaseolies\Session\Stores;

use Closure;
use PDO;
use PDOException;
use PDOStatement;
use Phaseolies\Session\Contracts\LockableSessionStore;
use Phaseolies\Session\Contracts\UserSessionStore;
use Phaseolies\Session\Exceptions\SessionException;
use Phaseolies\Session\RotationPointer;

class DatabaseStore implements LockableSessionStore, UserSessionStore
{
    /**
     * Most expired sessions a single sweep() removes.
     */
    protected const SWEEP_LIMIT = 1000;

    /**
     * Longest user agent kept, so a hostile header cannot bloat the table.
     */
    protected const MAX_USER_AGENT = 1024;

    /**
     * Most last-activity times remembered for touch(), for long-running workers.
     */
    protected const MAX_TRACKED = 1000;

    /**
     * Namespace of the PostgreSQL advisory locks taken for sessions.
     */
    protected const PGSQL_LOCK_NAMESPACE = 'doppar_session';

    /**
     * Most rotation pointers followed from one session, which only matters for a loop.
     */
    protected const MAX_HOPS = 3;

    /**
     * The connection prepared statements belong to.
     */
    private ?PDO $pdo = null;

    /**
     * Name of the driver of the current connection.
     */
    private string $driver = '';

    /**
     * Prepared statements of the current connection, keyed by SQL.
     *
     * @var array<string, PDOStatement>
     */
    private array $statements = [];

    /**
     * Last activity of the sessions read or written by this process.
     *
     * @var array<string, int>
     */
    private array $activity = [];

    /**
     * Sessions this instance holds the lock of right now.
     *
     * @var array<string, true>
     */
    private array $held = [];

    /**
     * Whether the missing lock support of the driver was already reported.
     */
    private bool $reportedNoLocking = false;

    /**
     * The quoted table name.
     */
    private string $quotedTable = '';

    /**
     * The SQLite connection's own busy timeout, restored after taking a lock.
     */
    private ?int $sqliteBusyTimeout = null;

    /**
     * @param Closure(): PDO $connection Resolves the connection to use
     * @param string $table
     * @param int $lifetime Seconds
     * @param Closure(array): (int|string|null)|null $userResolver
     * @param Closure(): array{ip_address?: string|null, user_agent?: string|null}|null $metadataResolver
     * @param float $lockTimeout
     * @throws SessionException
     */
    public function __construct(
        protected Closure $connection,
        protected string $table = 'sessions',
        protected int $lifetime = 7200,
        protected ?Closure $userResolver = null,
        protected ?Closure $metadataResolver = null,
        protected float $lockTimeout = 2.0
    ) {
        if (!self::isValidTableName($table)) {
            throw new SessionException("Invalid session table name [{$table}].");
        }
    }

    /**
     * Whether a session table name is a plain identifier the store and the migration quote the same way.
     *
     * @param string $table
     * @return bool
     */
    public static function isValidTableName(string $table): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*\z/', $table) === 1;
    }

    /**
     * Load a session from the value of the session cookie
     *
     * @param string $cookieValue
     * @return array{id: string, data: array}|null
     * @throws SessionException
     */
    public function read(string $cookieValue): ?array
    {
        if (!FileStore::isValidId($cookieValue)) {
            return null;
        }

        $row = $this->attempt('read', function () use ($cookieValue): array|false {
            $statement = $this->execute(
                "SELECT payload, last_activity FROM {$this->table()} WHERE id = ?",
                [$cookieValue]
            );

            $row = $statement->fetch(PDO::FETCH_NUM);
            $statement->closeCursor();

            return $row;
        });

        if ($row === false) {
            unset($this->activity[$cookieValue]);

            return null;
        }

        [$payload, $lastActivity] = [(string) $row[0], (int) $row[1]];

        if ($lastActivity + $this->lifetime < time()) {
            // Guarded by the activity time, so a session another request has
            // just refreshed is not deleted by this one.
            $this->attempt('read', fn() => $this->execute(
                "DELETE FROM {$this->table()} WHERE id = ? AND last_activity = ?",
                [$cookieValue, $lastActivity]
            ));

            unset($this->activity[$cookieValue]);

            return null;
        }

        $data = $this->decode($payload);

        if ($data === null) {
            return null;
        }

        $this->remember($cookieValue, $lastActivity);

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
        if (!FileStore::isValidId($id)) {
            throw new SessionException('Invalid session id.');
        }

        $now = time();
        $metadata = $this->metadata();

        $this->attempt('write', fn() => $this->upsert([
            $id,
            $this->userOf($data),
            $metadata['ip_address'],
            $metadata['user_agent'],
            base64_encode(serialize($data)),
            $now,
        ]));

        $this->remember($id, $now);

        return $id;
    }

    /**
     * Refresh the lifetime of a session without rewriting its data
     *
     * @param string $id
     * @return void
     * @throws SessionException
     */
    public function touch(string $id): void
    {
        if (!FileStore::isValidId($id)) {
            return;
        }

        $now = time();
        $threshold = min(60, intdiv($this->lifetime, 10));

        // Expiry only needs minute precision: skip the write while it is fresh.
        if (isset($this->activity[$id]) && $now - $this->activity[$id] <= $threshold) {
            return;
        }

        $metadata = $this->metadata();

        $this->attempt('touch', fn() => $this->execute(
            "UPDATE {$this->table()} SET last_activity = ?, ip_address = ?, user_agent = ? WHERE id = ? AND last_activity < ?",
            [$now, $metadata['ip_address'], $metadata['user_agent'], $id, $now - $threshold]
        ));

        $this->remember($id, $now);
    }

    /**
     * Remove a session
     *
     * @param string $id
     * @return void
     * @throws SessionException
     */
    public function destroy(string $id): void
    {
        if (!FileStore::isValidId($id)) {
            return;
        }

        // Under the lock, so a request that is saving this session right now
        // finishes first and then finds it gone instead of writing it back.
        $this->synchronized($id, function () use ($id): void {
            $this->attempt('destroy', fn() => $this->execute(
                "DELETE FROM {$this->table()} WHERE id = ?",
                [$id]
            ));
        });

        unset($this->activity[$id]);
    }

    /**
     * Remove every expired session
     *
     * @param int $maxLifetime
     * @return int
     * @throws SessionException
     */
    public function gc(int $maxLifetime): int
    {
        return $this->attempt('gc', fn() => $this->execute(
            "DELETE FROM {$this->table()} WHERE last_activity <= ?",
            [time() - $maxLifetime]
        )->rowCount());
    }

    /**
     * Remove expired sessions, doing only a bounded slice of the work
     *
     * @param int $maxLifetime
     * @return int
     * @throws SessionException
     */
    public function sweep(int $maxLifetime): int
    {
        return $this->attempt('sweep', function () use ($maxLifetime): int {
            $table = $this->table();
            $limit = self::SWEEP_LIMIT;

            $sql = $this->driver() === 'mysql'
                ? "DELETE FROM {$table} WHERE last_activity <= ? LIMIT {$limit}"
                : "DELETE FROM {$table} WHERE id IN (SELECT id FROM {$table} WHERE last_activity <= ? LIMIT {$limit})";

            return $this->execute($sql, [time() - $maxLifetime])->rowCount();
        });
    }

    /**
     * Remove every stored session
     *
     * @return int
     * @throws SessionException
     */
    public function flush(): int
    {
        $this->activity = [];

        return $this->attempt('flush', fn() => $this->execute("DELETE FROM {$this->table()}")->rowCount());
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
     * The live sessions of a user, most recently active first
     *
     * @param int|string $userId
     * @return array<int, array{id: string, ip_address: string|null, user_agent: string|null, last_activity: int}>
     * @throws SessionException
     */
    public function userSessions(int|string $userId): array
    {
        $rows = $this->attempt('list', function () use ($userId): array {
            $statement = $this->execute(
                "SELECT id, ip_address, user_agent, last_activity FROM {$this->table()} WHERE user_id = ? AND last_activity >= ? ORDER BY last_activity DESC",
                [$userId, time() - $this->lifetime]
            );

            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $statement->closeCursor();

            return $rows;
        });

        return array_map(static fn(array $row): array => [
            'id' => (string) $row['id'],
            'ip_address' => $row['ip_address'] === null ? null : (string) $row['ip_address'],
            'user_agent' => $row['user_agent'] === null ? null : (string) $row['user_agent'],
            'last_activity' => (int) $row['last_activity'],
        ], $rows);
    }

    /**
     * End every session of a user, except the ones given and what they were rotated into
     *
     * @param int|string $userId
     * @param array<int, string> $keep
     * @return int
     * @throws SessionException
     */
    public function destroyUserSessions(int|string $userId, array $keep = []): int
    {
        $ids = $this->attempt('destroy', function () use ($userId): array {
            $statement = $this->execute("SELECT id FROM {$this->table()} WHERE user_id = ?", [$userId]);

            $ids = $statement->fetchAll(PDO::FETCH_COLUMN);
            $statement->closeCursor();

            return $ids;
        });

        $keep = $this->lineage($keep);

        $ended = 0;

        foreach ($ids as $id) {
            $id = (string) $id;

            if (!isset($keep[$id])) {
                $this->destroyFollowing($id, $keep);
                $ended++;
            }
        }

        return $ended;
    }

    /**
     * The given sessions and every session their rotation pointers lead to.
     *
     * @param array<int, string> $ids
     * @return array<string, true>
     */
    protected function lineage(array $ids): array
    {
        $lineage = [];

        foreach ($ids as $id) {
            for ($hop = 0; is_string($id) && FileStore::isValidId($id) && !isset($lineage[$id]) && $hop <= self::MAX_HOPS; $hop++) {
                $lineage[$id] = true;

                $record = $this->read($id);

                $id = $record === null ? null : RotationPointer::target($record['data'], false);
            }
        }

        return $lineage;
    }

    /**
     * End a session and the sessions a rotation made of it, stopping at a kept one.
     *
     * @param string $id
     * @param array<string, true> $keep
     * @return void
     */
    protected function destroyFollowing(string $id, array $keep): void
    {
        for ($hop = 0; $hop <= self::MAX_HOPS; $hop++) {
            $next = $this->synchronized($id, function () use ($id): ?string {
                $record = $this->read($id);

                $this->destroy($id);

                return $record === null ? null : RotationPointer::target($record['data'], false);
            });

            if (!is_string($next) || isset($keep[$next])) {
                return;
            }

            $id = $next;
        }
    }

    /**
     * Run a callback while no other request can save or destroy the same session
     *
     * @param string $id
     * @param callable $callback
     * @return mixed
     */
    public function synchronized(string $id, callable $callback): mixed
    {
        if (!FileStore::isValidId($id) || isset($this->held[$id])) {
            return $callback();
        }

        try {
            $driver = $this->driver();
        } catch (\Throwable) {
            return $callback();
        }

        return match ($driver) {
            'mysql' => $this->withMysqlLock($id, $callback),
            'pgsql' => $this->withPgsqlLock($id, $callback),
            'sqlite' => $this->withSqliteLock($id, $callback),
            default => $this->withoutLock($driver, $callback),
        };
    }

    /**
     * Run a callback under a MySQL/MariaDB named lock.
     *
     * @param string $id
     * @param callable $callback
     * @return mixed
     */
    protected function withMysqlLock(string $id, callable $callback): mixed
    {
        $name = 'doppar_session_' . $id;

        try {
            $statement = $this->execute('SELECT GET_LOCK(?, ?)', [$name, (int) ceil($this->lockTimeout)]);
            $acquired = $statement->fetchColumn();
            $statement->closeCursor();
        } catch (PDOException) {
            $acquired = null;
        }

        if ((string) $acquired !== '1') {
            $this->reportLockTimeout();

            return $callback();
        }

        $this->held[$id] = true;

        try {
            return $callback();
        } finally {
            unset($this->held[$id]);

            $this->quietly(function () use ($name): void {
                $this->execute('SELECT RELEASE_LOCK(?)', [$name])->closeCursor();
            });
        }
    }

    /**
     * Run a callback under a PostgreSQL session-level advisory lock.
     *
     * @param string $id
     * @param callable $callback
     * @return mixed
     */
    protected function withPgsqlLock(string $id, callable $callback): mixed
    {
        $keys = [self::int32(crc32(self::PGSQL_LOCK_NAMESPACE)), self::int32((int) hexdec(substr($id, 0, 8)))];
        $deadline = microtime(true) + $this->lockTimeout;

        while (true) {
            try {
                $statement = $this->execute('SELECT pg_try_advisory_lock(CAST(? AS integer), CAST(? AS integer))', $keys);
                $acquired = $statement->fetchColumn();
                $statement->closeCursor();
            } catch (PDOException) {
                $this->reportLockTimeout();

                return $callback();
            }

            if (self::isTrue($acquired)) {
                break;
            }

            if (microtime(true) > $deadline) {
                $this->reportLockTimeout();

                return $callback();
            }

            usleep(5000);
        }

        $this->held[$id] = true;

        try {
            return $callback();
        } finally {
            unset($this->held[$id]);

            $this->quietly(function () use ($keys): void {
                $this->execute('SELECT pg_advisory_unlock(CAST(? AS integer), CAST(? AS integer))', $keys)->closeCursor();
            });
        }
    }

    /**
     * Run a callback inside an immediate (write-locking) SQLite transaction.
     *
     * @param string $id
     * @param callable $callback
     * @return mixed
     */
    protected function withSqliteLock(string $id, callable $callback): mixed
    {
        $pdo = $this->pdo();

        // A transaction of the application is already open on this connection:
        // SQLite cannot nest one, and that transaction already holds the writes.
        if ($pdo->inTransaction()) {
            return $callback();
        }

        try {
            // Wait for the write lock no longer than the lock timeout, then put
            // the connection's own busy timeout back for the application.
            $original = $this->sqliteBusyTimeout ??= (int) $pdo->query('PRAGMA busy_timeout')->fetchColumn();
            $pdo->exec('PRAGMA busy_timeout = ' . max(1, (int) round($this->lockTimeout * 1000)));

            try {
                $pdo->exec('BEGIN IMMEDIATE');
            } finally {
                $pdo->exec('PRAGMA busy_timeout = ' . $original);
            }
        } catch (PDOException) {
            $this->reportLockTimeout();

            return $callback();
        }

        $this->held[$id] = true;

        try {
            $result = $callback();
            $pdo->exec('COMMIT');

            return $result;
        } catch (\Throwable $e) {
            $this->quietly(fn() => $pdo->exec('ROLLBACK'));

            throw $e;
        } finally {
            unset($this->held[$id]);
        }
    }

    /**
     * Run a callback without a lock, on a driver that offers none.
     *
     * @param string $driver
     * @param callable $callback
     * @return mixed
     */
    protected function withoutLock(string $driver, callable $callback): mixed
    {
        if (!$this->reportedNoLocking) {
            $this->reportedNoLocking = true;

            $this->report("Session locking is not supported for the [{$driver}] driver. Saving without a lock, so concurrent changes to a session may be lost.");
        }

        return $callback();
    }

    /**
     * Insert the session, or replace it when it exists.
     *
     * @param array $values id, user_id, ip_address, user_agent, payload, last_activity
     * @return void
     */
    protected function upsert(array $values): void
    {
        $table = $this->table();
        $columns = 'id, user_id, ip_address, user_agent, payload, last_activity';

        switch ($this->driver()) {
            case 'mysql':
                $this->execute(
                    "INSERT INTO {$table} ({$columns}) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE "
                        . 'user_id = VALUES(user_id), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent), '
                        . 'payload = VALUES(payload), last_activity = VALUES(last_activity)',
                    $values
                );

                return;

            case 'pgsql':
            case 'sqlite':
                $this->execute(
                    "INSERT INTO {$table} ({$columns}) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT (id) DO UPDATE SET "
                        . 'user_id = excluded.user_id, ip_address = excluded.ip_address, user_agent = excluded.user_agent, '
                        . 'payload = excluded.payload, last_activity = excluded.last_activity',
                    $values
                );

                return;
        }

        // Portable fallback for other drivers.
        [$id, $userId, $ip, $agent, $payload, $time] = $values;

        $updated = $this->execute(
            "UPDATE {$table} SET user_id = ?, ip_address = ?, user_agent = ?, payload = ?, last_activity = ? WHERE id = ?",
            [$userId, $ip, $agent, $payload, $time, $id]
        )->rowCount();

        if ($updated === 0) {
            $this->execute("INSERT INTO {$table} ({$columns}) VALUES (?, ?, ?, ?, ?, ?)", $values);
        }
    }

    /**
     * Execute a prepared statement, reusing it for the same SQL.
     *
     * @param string $sql
     * @param array $bindings
     * @return PDOStatement
     */
    protected function execute(string $sql, array $bindings = []): PDOStatement
    {
        $pdo = $this->pdo();
        $statement = $this->statements[$sql] ??= $pdo->prepare($sql);

        foreach (array_values($bindings) as $index => $value) {
            $statement->bindValue($index + 1, $value, match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        }

        $statement->execute();

        return $statement;
    }

    /**
     * The connection, resetting the prepared statements when it changed.
     *
     * @return PDO
     */
    protected function pdo(): PDO
    {
        $pdo = ($this->connection)();

        if ($pdo !== $this->pdo) {
            $this->pdo = $pdo;
            $this->statements = [];
            $this->driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $this->quotedTable = '';
            $this->sqliteBusyTimeout = null;
        }

        return $pdo;
    }

    /**
     * Name of the driver of the connection.
     *
     * @return string
     */
    protected function driver(): string
    {
        $this->pdo();

        return $this->driver;
    }

    /**
     * The table name, quoted for the driver.
     *
     * @return string
     */
    protected function table(): string
    {
        if ($this->quotedTable === '') {
            $quote = $this->driver() === 'mysql' ? '`' : '"';

            $this->quotedTable = $quote . $this->table . $quote;
        }

        return $this->quotedTable;
    }

    /**
     * Run a database operation, explaining a failure.
     *
     * @template T
     * @param string $operation
     * @param callable(): T $callback
     * @return T
     * @throws SessionException
     */
    protected function attempt(string $operation, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (PDOException $e) {
            throw new SessionException(sprintf(
                'Unable to %s the session in table [%s]: %s. If the table does not exist, run "php pool session:table" and migrate.',
                $operation,
                $this->table,
                $e->getMessage()
            ), 0, $e);
        }
    }

    /**
     * Decode a stored payload, or null when it is not a session.
     *
     * @param string $payload
     * @return array|null
     */
    protected function decode(string $payload): ?array
    {
        $serialized = base64_decode($payload, true);

        if ($serialized === false || $serialized === '') {
            return null;
        }

        $data = @unserialize($serialized);

        return is_array($data) ? $data : null;
    }

    /**
     * The user a session belongs to.
     *
     * @param array $data
     * @return int|string|null
     */
    protected function userOf(array $data): int|string|null
    {
        if ($this->userResolver === null) {
            return null;
        }

        $user = ($this->userResolver)($data);

        return is_int($user) || (is_string($user) && $user !== '') ? $user : null;
    }

    /**
     * The client the session is being saved for.
     *
     * @return array{ip_address: string|null, user_agent: string|null}
     */
    protected function metadata(): array
    {
        $metadata = [];

        if ($this->metadataResolver !== null) {
            try {
                $metadata = ($this->metadataResolver)();
            } catch (\Throwable) {
            }
        }

        $ip = $metadata['ip_address'] ?? null;
        $agent = $metadata['user_agent'] ?? null;

        return [
            'ip_address' => is_string($ip) && $ip !== '' ? substr($ip, 0, 45) : null,
            'user_agent' => is_string($agent) ? self::printable($agent, self::MAX_USER_AGENT) : null,
        ];
    }

    /**
     * Reduce client-supplied text to printable ASCII of a bounded length.
     *
     * @param string $value
     * @param int $length
     * @return string|null
     */
    private static function printable(string $value, int $length): ?string
    {
        $value = substr((string) preg_replace('/[^\x20-\x7E]/', '?', $value), 0, $length);

        return $value === '' ? null : $value;
    }

    /**
     * Remember the last activity of a session, bounded for long-running workers.
     *
     * @param string $id
     * @param int $time
     * @return void
     */
    protected function remember(string $id, int $time): void
    {
        if (count($this->activity) >= self::MAX_TRACKED && !isset($this->activity[$id])) {
            $this->activity = [];
        }

        $this->activity[$id] = $time;
    }

    /**
     * Run a cleanup step whose failure must not hide the original outcome.
     *
     * @param callable $callback
     * @return void
     */
    protected function quietly(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable) {
        }
    }

    /**
     * Report that a session lock could not be taken.
     *
     * @return void
     */
    protected function reportLockTimeout(): void
    {
        $this->report(sprintf(
            'Session lock was not acquired within %s seconds. Saving without a lock, so concurrent changes to this session may be lost.',
            rtrim(rtrim(number_format($this->lockTimeout, 3, '.', ''), '0'), '.')
        ));
    }

    /**
     * Report a condition that does not stop the request but should not go unnoticed.
     *
     * @param string $message
     * @return void
     */
    protected function report(string $message): void
    {
        try {
            if (function_exists('warning')) {
                warning('[Doppar] ' . $message);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Whether a boolean column came back true: drivers return true, 1, "1" or "t".
     *
     * @param mixed $value
     * @return bool
     */
    private static function isTrue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(is_scalar($value) ? strtolower((string) $value) : '', ['1', 't', 'true'], true);
    }

    /**
     * Interpret an unsigned 32-bit value as a signed PostgreSQL integer.
     *
     * @param int $value
     * @return int
     */
    private static function int32(int $value): int
    {
        $value &= 0xFFFFFFFF;

        return $value > 0x7FFFFFFF ? $value - 0x100000000 : $value;
    }
}
