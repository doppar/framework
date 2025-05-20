<?php

namespace Phaseolies\Database\Query;

use Phaseolies\Support\Facades\DB;

class Builder
{
    protected string $table;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Truncate the table
     * @return int
     */
    public function truncate(bool $resetAutoIncrement = true): int
    {
        $sql = "TRUNCATE TABLE {$this->table}";
        if (!$resetAutoIncrement) {
            $sql = "DELETE FROM {$this->table}";
        }

        if (!DB::tableExists($this->table)) {
            throw new \RuntimeException("Table {$this->table} does not exist");
        }

        return DB::execute($sql);
    }

    /**
     * Delete all records from the table
     * @return int
     */
    public function delete(): int
    {
        return DB::execute("DELETE FROM {$this->table}");
    }

    /**
     * Drop table from database
     * @return int
     */
    public function drop(): int
    {
        return DB::execute("DROP TABLE {$this->table}");
    }
}
