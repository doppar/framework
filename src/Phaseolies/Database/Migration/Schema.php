<?php

namespace Phaseolies\Database\Migration;

use Phaseolies\Support\Facades\DB;

class Schema
{
    /**
     * The database connection name
     *
     * @var string|null
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
     * @param string $table
     * @param callable $callback
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
     * @param string $table
     * @param callable $callback
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
     * @param string $table
     */
    public function dropIfExists(string $table): void
    {
        DB::connection($this->connection)->execute("DROP TABLE IF EXISTS {$table}");
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $table
     * @return bool
     */
    public function hasTable(string $table): bool
    {
        return (bool) DB::connection($this->connection)->tableExists($table);
    }

    /**
     * Disable foreign key constraints
     *
     * @return void
     */
    public function disableForeignKeyConstraints(): void
    {
        DB::connection($this->connection)->disableForeignKeyConstraints();
    }

    /**
     * Enable foreign key constraints
     *
     * @return void
     */
    public function enableForeignKeyConstraints(): void
    {
        DB::connection($this->connection)->enableForeignKeyConstraints();
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
