<?php

namespace Phaseolies\Database\Migration;

use Phaseolies\Support\Facades\DB;

class MigrationRepository
{
    /**
     * Name of the table used to store migration records
     *
     * @var string
     */
    protected string $table = 'migrations';

    /**
     * Whether the optional audit columns exist, keyed by connection
     *
     * @var array<string, bool>
     */
    protected array $extended = [];

    /**
     * Checks if the migrations table exists in the database
     *
     * @return bool True if table exists, false otherwise
     */
    public function exists(?string $connection = null): bool
    {
        return (bool) DB::connection($connection)->tableExists($this->table);
    }

    /**
     * Creates the migrations table in the database
     *
     * @param string|null $connection
     * @return void
     */
    public function create(?string $connection = null): void
    {
        if ($this->exists($connection)) {
            return;
        }

        Schema::connection($connection)->create($this->table, function ($table) {
            $table->string('migration');
            $table->integer('batch');
            $table->string('checksum', 64)->nullable();
            $table->integer('execution_time')->nullable();
            $table->timestamp('ran_at')->nullable();
        });

        $this->extended[$this->key($connection)] = true;
    }

    /**
     * Add the audit columns
     *
     * @param string|null $connection
     * @return void
     */
    public function upgrade(?string $connection = null): void
    {
        if ($this->hasAuditColumns($connection) || !$this->exists($connection)) {
            return;
        }

        $schema = Schema::connection($connection);

        $missing = array_filter(
            ['checksum', 'execution_time', 'ran_at'],
            fn($column) => !$schema->hasColumn($this->table, $column)
        );

        if ($missing === []) {
            $this->extended[$this->key($connection)] = true;

            return;
        }

        $schema->table($this->table, function ($table) use ($missing) {
            if (in_array('checksum', $missing, true)) {
                $table->string('checksum', 64)->nullable();
            }

            if (in_array('execution_time', $missing, true)) {
                $table->integer('execution_time')->nullable();
            }

            if (in_array('ran_at', $missing, true)) {
                $table->timestamp('ran_at')->nullable();
            }
        });

        $this->extended[$this->key($connection)] = true;
    }

    /**
     * Gets the list of migrations that have already been run
     *
     * @param string|null $connection
     * @return array
     */
    public function getRan(?string $connection = null): array
    {
        $connection = $connection ?? config('database.default');

        if (!$this->exists($connection)) {
            return [];
        }

        $stmt = DB::connection($connection)->statement(
            "SELECT migration FROM {$this->table} ORDER BY batch ASC, migration ASC"
        );

        $results = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $results ?: [];
    }

    /**
     * Get every recorded migration keyed by name, oldest first
     *
     * @param string|null $connection
     * @return array<string, array{migration: string, batch: int, checksum: ?string, execution_time: ?int, ran_at: ?string}>
     */
    public function getRecords(?string $connection = null): array
    {
        $connection = $connection ?? config('database.default');

        if (!$this->exists($connection)) {
            return [];
        }

        $columns = $this->hasAuditColumns($connection)
            ? 'migration, batch, checksum, execution_time, ran_at'
            : 'migration, batch';

        $rows = DB::connection($connection)
            ->statement("SELECT {$columns} FROM {$this->table} ORDER BY batch ASC, migration ASC")
            ->fetchAll(\PDO::FETCH_ASSOC);

        $records = [];

        foreach ($rows as $row) {
            $records[$row['migration']] = [
                'migration' => $row['migration'],
                'batch' => (int) $row['batch'],
                'checksum' => $row['checksum'] ?? null,
                'execution_time' => isset($row['execution_time']) ? (int) $row['execution_time'] : null,
                'ran_at' => $row['ran_at'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * Get the migrations to roll back, newest first.
     *
     * @param string|null $connection
     * @param int|null $steps
     * @param int|null $batch
     * @return array<int, string>
     */
    public function getRollbackCandidates(?string $connection = null, ?int $steps = null, ?int $batch = null): array
    {
        $records = array_values($this->getRecords($connection));

        usort($records, fn($a, $b) => [$b['batch'], $b['migration']] <=> [$a['batch'], $a['migration']]);

        if ($records === []) {
            return [];
        }

        if ($batch !== null) {
            $records = array_filter($records, fn($record) => $record['batch'] === $batch);
        } elseif ($steps !== null) {
            $records = array_slice($records, 0, max(0, $steps));
        } else {
            $last = $records[0]['batch'];
            $records = array_filter($records, fn($record) => $record['batch'] === $last);
        }

        return array_column($records, 'migration');
    }

    /**
     * Logs a migration file as having been run
     *
     * @param string $file
     * @param string|null $connection
     * @param int|null $batch
     * @param string|null $checksum
     * @param int|null $executionTime
     */
    public function log(
        string $file,
        ?string $connection = null,
        ?int $batch = null,
        ?string $checksum = null,
        ?int $executionTime = null
    ): void {
        $batch ??= $this->getNextBatchNumber($connection);

        if ($this->hasAuditColumns($connection)) {
            DB::connection($connection)->execute(
                "INSERT INTO {$this->table} (migration, batch, checksum, execution_time, ran_at) VALUES (?, ?, ?, ?, ?)",
                [$file, $batch, $checksum, $executionTime, date('Y-m-d H:i:s')]
            );

            return;
        }

        DB::connection($connection)->execute(
            "INSERT INTO {$this->table} (migration, batch) VALUES (?, ?)",
            [$file, $batch]
        );
    }

    /**
     * Remove a migration record, as when it has been rolled back
     *
     * @param string $file
     * @param string|null $connection
     * @return void
     */
    public function delete(string $file, ?string $connection = null): void
    {
        DB::connection($connection)->execute(
            "DELETE FROM {$this->table} WHERE migration = ?",
            [$file]
        );
    }

    /**
     * Gets the next batch number for new migrations
     *
     * @param string|null $connection
     * @return int
     */
    public function getNextBatchNumber(?string $connection = null): int
    {
        return $this->getLastBatchNumber($connection) + 1;
    }

    /**
     * Gets the highest batch number in use, or 0 when nothing has run
     *
     * @param string|null $connection
     * @return int
     */
    public function getLastBatchNumber(?string $connection = null): int
    {
        if (!$this->exists($connection)) {
            return 0;
        }

        $stmt = DB::connection($connection)->statement("SELECT MAX(batch) FROM {$this->table}");

        return (int) $stmt->fetchColumn();
    }

    /**
     * Whether the checksum / execution time / run date columns are available
     *
     * @param string|null $connection
     * @return bool
     */
    protected function hasAuditColumns(?string $connection = null): bool
    {
        $key = $this->key($connection);

        if (!isset($this->extended[$key])) {
            $this->extended[$key] = $this->exists($connection)
                && Schema::connection($connection)->hasColumn($this->table, 'checksum')
                && Schema::connection($connection)->hasColumn($this->table, 'execution_time')
                && Schema::connection($connection)->hasColumn($this->table, 'ran_at');
        }

        return $this->extended[$key];
    }

    /**
     * @param string|null $connection
     * @return string
     */
    protected function key(?string $connection): string
    {
        return $connection ?? (string) config('database.default');
    }
}
