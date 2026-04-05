<?php

namespace Phaseolies\Database\Temporal;

use PDO;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Temporal\Attributes\Temporal;

class TemporalManager
{
    /**
     * Cache of resolved #[Temporal] attribute instances, keyed by class name
     *
     * @var array<string, Temporal|null>
     */
    private static array $cache = [];

    /**
     * Per-process cache of history table column existence checks
     *
     * @var array<string, bool>
     */
    private static array $columnCache = [];

    /**
     * Determine whether the given model class carries #[Temporal].
     *
     * @param string $class
     * @return bool
     */
    public static function isTemporal(string $class): bool
    {
        return self::getAttribute($class) !== null;
    }

    /**
     * Return the resolved #[Temporal] instance for a class, or null.
     *
     * @param string $class
     * @return Temporal|null
     */
    public static function getAttribute(string $class): ?Temporal
    {
        if (!array_key_exists($class, self::$cache)) {
            $ref   = new \ReflectionClass($class);
            $attrs = $ref->getAttributes(Temporal::class);
            self::$cache[$class] = $attrs ? $attrs[0]->newInstance() : null;
        }

        return self::$cache[$class];
    }

    /**
     * Return the history table name for the given base table and model class.
     *
     * @param string $table
     * @param string $class
     * @return string
     */
    public static function historyTable(string $table, string $class): string
    {
        $attr   = self::getAttribute($class);
        $suffix = $attr?->suffix ?? '_history';

        return $table . $suffix;
    }

    /**
     * Snapshot the model's current state into the history table.
     *
     * @param Model $model
     * @param string $action
     * @return void
     */
    public static function snapshot(Model $model, string $action): void
    {
        $pdo          = $model->getConnection();
        $historyTable = self::historyTable($model->getTable(), get_class($model));

        $recordId = $model->getKey();
        if ($recordId === null && $action === 'created') {
            $recordId = (int) $pdo->lastInsertId() ?: null;
        }

        $snapshot = $model->getAttributes();
        if (!isset($snapshot[$model->getPrimaryKey()]) && $recordId !== null) {
            $snapshot[$model->getPrimaryKey()] = $recordId;
        }

        // For updates, record which columns changed
        $changedCols = null;
        if ($action === 'updated') {
            $dirty = $model->getDirtyAttributes();
            unset($dirty['updated_at']);
            $changedCols = !empty($dirty) ? json_encode(array_keys($dirty)) : null;
        }

        $driver    = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $validFrom = self::nowMicro($driver);

        $data = [
            'record_id'    => $recordId,
            'action'       => $action,
            'valid_from'   => $validFrom,
            'snapshot'     => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'changed_cols' => $changedCols,
        ];

        $attr = self::getAttribute(get_class($model));
        if ($attr?->trackActor && self::hasActorColumn($pdo, $historyTable, $driver)) {
            $data['actor'] = self::resolveActor();
        }

        self::insertHistory($pdo, $historyTable, $data);
    }

    /**
     * Check whether the `actor` column exists in the given history table
     *
     * @param PDO $pdo
     * @param string $historyTable
     * @param string $driver
     * @return bool
     */
    private static function hasActorColumn(PDO $pdo, string $historyTable, string $driver): bool
    {
        $cacheKey = $historyTable . '.actor';

        if (!array_key_exists($cacheKey, self::$columnCache)) {
            try {
                self::$columnCache[$cacheKey] = match ($driver) {
                    'pgsql' => (function () use ($pdo, $historyTable): bool {
                        $stmt = $pdo->prepare(
                            "SELECT COUNT(*) FROM information_schema.columns
                             WHERE table_name = ? AND column_name = 'actor'"
                        );
                        $stmt->execute([$historyTable]);
                        return (bool) $stmt->fetchColumn();
                    })(),
                    'sqlite' => (function () use ($pdo, $historyTable): bool {
                        $stmt = $pdo->query("PRAGMA table_info(\"{$historyTable}\")");
                        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                            if ($col['name'] === 'actor') {
                                return true;
                            }
                        }
                        return false;
                    })(),
                    default => (function () use ($pdo, $historyTable): bool {
                        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$historyTable}` LIKE 'actor'");
                        $stmt->execute();
                        return $stmt->fetch() !== false;
                    })(),
                };
            } catch (\Throwable) {
                self::$columnCache[$cacheKey] = false;
            }
        }

        return self::$columnCache[$cacheKey];
    }

    /**
     * Insert a single row into the history table.
     *
     * @param PDO $pdo
     * @param string $table
     * @param array  $data
     * @return void
     */
    private static function insertHistory(PDO $pdo, string $table, array $data): void
    {
        $columns      = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));
    }

    /**
     * Generate a high-resolution timestamp string for the current driver.
     *
     * @param string $driver
     * @return string
     */
    private static function nowMicro(string $driver): string
    {
        $now = \Carbon\Carbon::now();

        return match ($driver) {
            'pgsql'  => $now->format('Y-m-d H:i:s.uP'),
            'sqlite' => $now->format('Y-m-d H:i:s.u'),
            default  => $now->format('Y-m-d H:i:s.u'),
        };
    }

    /**
     * Try to resolve the currently authenticated user's identifier
     *
     * @return string|int|null
     */
    private static function resolveActor(): mixed
    {
        try {
            $user = auth()->user();
            return $user?->getKey();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Generate the CREATE TABLE SQL for the history table.
     *
     * @param string $historyTable
     * @param string $driver
     * @param bool $trackActor
     * @return string[]
     */
    public static function createHistoryTableSql(string $historyTable, string $driver, bool $trackActor = false): array
    {
        $actorCol = $trackActor ? self::actorColumnSql($driver) : '';

        return match ($driver) {
            'pgsql'  => self::pgsqlDdl($historyTable, $actorCol),
            'sqlite' => self::sqliteDdl($historyTable, $actorCol),
            default  => self::mysqlDdl($historyTable, $actorCol),
        };
    }

    private static function mysqlDdl(string $table, string $actorCol): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS `{$table}` (
                `history_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `record_id`    BIGINT UNSIGNED NOT NULL,
                `action`       VARCHAR(10)     NOT NULL,
                `valid_from`   DATETIME(6)     NOT NULL,
                `snapshot`     JSON            NOT NULL,
                `changed_cols` JSON                NULL,
                {$actorCol}
                PRIMARY KEY (`history_id`),
                INDEX `idx_record_id`  (`record_id`),
                INDEX `idx_valid_from` (`valid_from`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];
    }

    private static function pgsqlDdl(string $table, string $actorCol): array
    {
        $stmts = [
            "CREATE TABLE IF NOT EXISTS \"{$table}\" (
                history_id   BIGSERIAL PRIMARY KEY,
                record_id    BIGINT       NOT NULL,
                action       VARCHAR(10)  NOT NULL,
                valid_from   TIMESTAMPTZ  NOT NULL,
                snapshot     JSONB        NOT NULL,
                changed_cols JSONB            NULL
                {$actorCol}
            )",
            "CREATE INDEX IF NOT EXISTS \"idx_{$table}_record_id\"  ON \"{$table}\" (record_id)",
            "CREATE INDEX IF NOT EXISTS \"idx_{$table}_valid_from\" ON \"{$table}\" (valid_from)",
        ];

        return $stmts;
    }

    private static function sqliteDdl(string $table, string $actorCol): array
    {
        $stmts = [
            "CREATE TABLE IF NOT EXISTS \"{$table}\" (
                history_id   INTEGER  PRIMARY KEY AUTOINCREMENT,
                record_id    INTEGER  NOT NULL,
                action       TEXT     NOT NULL,
                valid_from   TEXT     NOT NULL,
                snapshot     TEXT     NOT NULL,
                changed_cols TEXT         NULL
                {$actorCol}
            )",
            "CREATE INDEX IF NOT EXISTS \"idx_{$table}_record_id\"  ON \"{$table}\" (record_id)",
            "CREATE INDEX IF NOT EXISTS \"idx_{$table}_valid_from\" ON \"{$table}\" (valid_from)",
        ];

        return $stmts;
    }

    private static function actorColumnSql(string $driver): string
    {
        return match ($driver) {
            'pgsql'  => ",\n                actor VARCHAR(255) NULL",
            'sqlite' => ",\n                actor TEXT NULL",
            default  => "`actor` VARCHAR(255) NULL,",
        };
    }

    /**
     * Reset the internal class attribute cache. Useful in tests.
     *
     * @param string|null $class  When null, clears all entries.
     * @return void
     */
    public static function resetCache(?string $class = null): void
    {
        if ($class !== null) {
            unset(self::$cache[$class]);
        } else {
            self::$cache = [];
        }
    }
}
