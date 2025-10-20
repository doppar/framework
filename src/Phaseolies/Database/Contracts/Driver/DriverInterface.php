<?php

namespace Phaseolies\Database\Contracts\Driver;

use PDO;

interface DriverInterface
{
    /**
     * Create a PDO connection for this driver using the given config.
     *
     * @param array $config
     * @return PDO
     */
    public function connect(array $config): PDO;

    /**
     * List tables in the current database/schema.
     *
     * @param PDO $pdo
     * @return array<string>
     */
    public function getTables(PDO $pdo): array;

    /**
     * Get column names for a specific table.
     *
     * @param PDO $pdo
     * @param string $table
     * @return array<string>
     */
    public function getTableColumns(PDO $pdo, string $table): array;

    /**
     * Check if a table exists.
     *
     * @param PDO $pdo
     * @param string $table
     * @return bool
     */
    public function tableExists(PDO $pdo, string $table): bool;

    /**
     * Call a stored procedure and return an array of rowsets.
     *
     * @param PDO $pdo
     * @param string $name
     * @param array $params
     * @param array $outputParams
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function callProcedure(PDO $pdo, string $name, array $params = [], array &$outputParams = []): array;

    /**
     * Drop all tables in the database and return the count of dropped tables.
     *
     * @param PDO $pdo
     * @return int
     */
    public function dropAllTables(PDO $pdo): int;

    /**
     * Disable foreign key constraints.
     *
     * @param PDO $pdo
     * @return void
     */
    public function disableForeignKeyConstraints(PDO $pdo): void;

    /**
     * Enable foreign key constraints.
     *
     * @param PDO $pdo
     * @return void
     */
    public function enableForeignKeyConstraints(PDO $pdo): void;

    /**
     * Truncate a table; if $resetAutoIncrement is false, fall back to delete-all
     * @param PDO $pdo
     * @param string $table
     * @param bool $resetAutoIncrement
     * @return int
     */
    public function truncate(PDO $pdo, string $table, bool $resetAutoIncrement = true): int;

    /**
     * Drop a table. Return affected rows if applicable
     *
     * @param PDO $pdo
     * @param string $table
     * @return int
     */
    public function dropTable(PDO $pdo, string $table): int;

    /**
     * Delete all rows from a table
     *
     * @param PDO $pdo
     * @param string $table
     * @return int
     */
    public function deleteAll(PDO $pdo, string $table): int;
}
