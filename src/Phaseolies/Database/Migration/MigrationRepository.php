<?php

namespace Phaseolies\Database\Migration;

use Phaseolies\Support\Facades\Schema;
use Phaseolies\Support\Facades\DB;

class MigrationRepository
{
    /**
     * Name of the table used to store migration records
     * @var string
     */
    protected string $table = 'migrations';

    /**
     * Checks if the migrations table exists in the database
     * @return bool True if table exists, false otherwise
     */
    public function exists(): bool
    {
        return DB::tableExists($this->table);
    }

    /**
     * Creates the migrations table in the database
     * The table has two columns:
     * - migration: string - stores the migration filename
     * - batch: integer - stores the batch number when the migration was run
     */
    public function create(): void
    {
        if ($this->exists()) {
            return;
        }

        Schema::create($this->table, function ($table) {
            $table->string('migration');
            $table->integer('batch');
        });
    }

    /**
     * Gets the list of migrations that have already been run
     * @return array Array of migration filenames, ordered by batch and migration name
     */
    public function getRan(): array
    {
        if (!$this->exists()) {
            return [];
        }

        $stmt = DB::statement(
            "SELECT migration FROM {$this->table} ORDER BY batch ASC, migration ASC"
        );

        $results = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return $results ?: [];
    }

    /**
     * Logs a migration file as having been run
     * @param string $file The migration filename to log
     */
    public function log(string $file): void
    {
        $batch = $this->getNextBatchNumber();

        DB::execute(
            "INSERT INTO {$this->table} (migration, batch) VALUES (?, ?)",
            [$file, $batch]
        );
    }

    /**
     * Gets the next batch number for new migrations
     * @return int The next batch number (increments the highest existing batch number)
     */
    protected function getNextBatchNumber(): int
    {
        if (!$this->exists()) {
            return 1;
        }

        $stmt = DB::statement("SELECT MAX(batch) FROM {$this->table}");
        $maxBatch = $stmt->fetchColumn();

        return $maxBatch ? (int) $maxBatch + 1 : 1;
    }
}
