<?php

namespace Phaseolies\Database\Contracts\Driver;

use PDO;

interface DriverInterface
{
    /**
     * Create a PDO connection for this driver using the given config.
     */
    public function connect(array $config): PDO;

    /**
     * List tables in the current database/schema.
     *
     * @return array<string>
     */
    public function getTables(PDO $pdo): array;

    /**
     * Get column names for a specific table.
     *
     * @return array<string>
     */
    public function getTableColumns(PDO $pdo, string $table): array;

    /** Check if a table exists. */
    public function tableExists(PDO $pdo, string $table): bool;

    /**
     * Call a stored procedure and return an array of rowsets.
     * Each element is an array of associative rows.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function callProcedure(PDO $pdo, string $name, array $params = [], array &$outputParams = []): array;

    /** Drop all tables in the database and return the count of dropped tables. */
    public function dropAllTables(PDO $pdo): int;

    /** Disable foreign key constraints. */
    public function disableForeignKeyConstraints(PDO $pdo): void;

    /** Enable foreign key constraints. */
    public function enableForeignKeyConstraints(PDO $pdo): void;

    /**
     * Truncate a table; if $resetAutoIncrement is false, fall back to delete-all.
     * Return affected rows if applicable.
     */
    public function truncate(PDO $pdo, string $table, bool $resetAutoIncrement = true): int;

    /** Drop a table. Return affected rows if applicable. */
    public function dropTable(PDO $pdo, string $table): int;

    /** Delete all rows from a table. */
    public function deleteAll(PDO $pdo, string $table): int;
}
