<?php

namespace Phaseolies\Database\Migration;

use Phaseolies\Support\Facades\Schema;
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
        });
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
     * Logs a migration file as having been run
     *
     * @param string $file
     * @param string|null $connection
     */
    public function log(string $file, ?string $connection = null): void
    {
        $batch = $this->getNextBatchNumber($connection);

        DB::connection($connection)->execute(
            "INSERT INTO {$this->table} (migration, batch) VALUES (?, ?)",
            [$file, $batch]
        );
    }

    /**
     * Gets the next batch number for new migrations
     *
     * @param string|null $connection
     * @return int
     */
    protected function getNextBatchNumber(?string $connection = null): int
    {
        if (!$this->exists($connection)) {
            return 1;
        }

        $stmt = DB::connection($connection)->statement("SELECT MAX(batch) FROM {$this->table}");
        $maxBatch = $stmt->fetchColumn();

        return $maxBatch ? (int) $maxBatch + 1 : 1;
    }
}
