<?php

namespace Phaseolies\Database\Migration;

use Phaseolies\Support\Facades\DB;

class Schema
{
    /**
     * Create a new database table
     *
     * @param string $table Name of the table to create
     * @param callable $callback Blueprint callback that defines the table structure
     */
    public function create(string $table, callable $callback): void
    {
        // Create a new blueprint instance for the table
        $blueprint = new Blueprint($table);

        // Execute the callback to define table columns and properties
        $callback($blueprint);

        // Execute the generated SQL to create the table
        DB::execute($blueprint->toSql());
    }

    /**
     * Modify an existing database table
     *
     * @param string $table Name of the table to modify
     * @param callable $callback Blueprint callback that defines the modifications
     */
    public function table(string $table, callable $callback): void
    {
        // Create a new blueprint instance for the table
        $blueprint = new Blueprint($table);

        // Execute the callback to define table alterations
        $callback($blueprint);

        // Note: The method currently doesn't execute any SQL
        // Typically you would execute the blueprint SQL here
    }

    /**
     * Drop a table if it exists
     *
     * @param string $table Name of the table to drop
     */
    public function dropIfExists(string $table): void
    {
        // Execute SQL to drop the table if it exists
        DB::execute("DROP TABLE IF EXISTS {$table}");
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $table Name of the table to check
     * @return bool True if table exists, false otherwise
     */
    public function hasTable(string $table): bool
    {
        return DB::tableExists($table);
    }

    /**
     * Disable foreign key constraints
     * Useful for operations that might violate foreign key rules temporarily
     */
    public function disableForeignKeyConstraints(): void
    {
        // MySQL-specific command to disable foreign key checks
        DB::execute('SET FOREIGN_KEY_CHECKS = 0');
    }

    /**
     * Enable foreign key constraints
     * Should be called after disableForeignKeyConstraints() to re-enable checks
     */
    public function enableForeignKeyConstraints(): void
    {
        // MySQL-specific command to enable foreign key checks
        DB::execute('SET FOREIGN_KEY_CHECKS = 1');
    }
}
