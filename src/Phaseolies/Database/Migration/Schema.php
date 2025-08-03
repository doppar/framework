<?php

namespace Phaseolies\Database\Migration;

use Phaseolies\Support\Facades\DB;

class Schema
{
    /**
     * The database connection name
     */
    protected ?string $connection = null;

    /**
     * Create a new Schema instance with a specific connection
     *
     * @param string|null $connection
     */
    public function __construct(?string $connection = null)
    {
        $this->connection = $connection;
    }

    /**
     * Create a new database table
     *
     * @param string $table Name of the table to create
     * @param callable $callback Blueprint callback that defines the table structure
     */
    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);

        $callback($blueprint);

        DB::connection($this->connection)->execute($blueprint->toSql());
    }

    /**
     * Modify an existing database table
     *
     * @param string $table Name of the table to modify
     * @param callable $callback Blueprint callback that defines the modifications
     */
    public function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);

        $callback($blueprint);

        $statements = $blueprint->toSql();

        DB::connection($this->connection)->execute($statements);
    }

    /**
     * Drop a table if it exists
     *
     * @param string $table Name of the table to drop
     */
    public function dropIfExists(string $table): void
    {
        DB::connection($this->connection)->execute("DROP TABLE IF EXISTS {$table}");
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $table Name of the table to check
     * @return bool True if table exists, false otherwise
     */
    public function hasTable(string $table): bool
    {
        return DB::connection($this->connection)->tableExists($table);
    }

    /**
     * Disable foreign key constraints
     * Useful for operations that might violate foreign key rules temporarily
     */
    public function disableForeignKeyConstraints(): void
    {
        DB::connection($this->connection)->execute('SET FOREIGN_KEY_CHECKS = 0');
    }

    /**
     * Enable foreign key constraints
     * Should be called after disableForeignKeyConstraints() to re-enable checks
     */
    public function enableForeignKeyConstraints(): void
    {
        DB::connection($this->connection)->execute('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Get a new Schema instance for the specified connection
     *
     * @param string|null $connection
     * @return static
     */
    public static function connection(?string $connection): self
    {
        return new static($connection);
    }
}
