<?php

namespace Phaseolies\Database\Drivers;

use PDO;
use PDOException;
use Phaseolies\Database\Contracts\Driver\DriverInterface;
use Phaseolies\Database\Query\Builder;
use Phaseolies\Database\Procedure\ProcedureResult;

class SQLiteDriver implements DriverInterface
{
    /**
     * Get the MySQL PDO Instance
     *
     * @param array $config
     * @return PDO
     */
    #[\Override]
    public function connect(array $config): PDO
    {
        $path = $config['database'];

        if ($path === ':memory:') {
            return new PDO('sqlite::memory:');
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return new PDO("sqlite:" . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Get table columns
     *
     * @param PDO $pdo
     * @param string $table
     * @return array
     */
    #[\Override]
    public function getTableColumns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query("PRAGMA table_info({$table})");

        return $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    }

    /**
     * Get table list
     *
     * @param PDO $pdo
     * @return array
     */
    #[\Override]
    public function getTables(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Check whether a table exists or not
     *
     * @param PDO $pdo
     * @param string $table
     * @return bool
     */
    #[\Override]
    public function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :name");
        $stmt->execute([':name' => $table]);
        return $stmt->fetch() !== false;
    }

    /**
     * Call store procedure and get the results
     *
     * @param PDO $pdo
     * @param string $name
     * @param array $params
     * @param array $outputParams
     * @return array
     */
    #[\Override]
    public function callProcedure(PDO $pdo, string $procedureName, array $params = [], array &$outputParams = []): array
    {
        // SQLite doesn't support stored procedures natively, so we'll use a transaction
        try {
            $pdo->beginTransaction();

            // For SQLite, we'll just execute the procedure name as a query
            // This is a simple implementation and may need to be adjusted
            $stmt = $pdo->prepare($procedureName);
            $stmt->execute($params);

            $results = [];
            do {
                $results[] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } while ($stmt->nextRowset());

            $pdo->commit();

            return $results;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Drop all the tables
     *
     * @param PDO $pdo
     * @return int
     */
    #[\Override]
    public function dropAllTables(PDO $pdo): int
    {
        $tables = $this->getTables($pdo);
        $count = 0;

        foreach ($tables as $table) {
            if ($table === 'sqlite_sequence') {
                // Reset auto-increment counters
                $pdo->exec("DELETE FROM $table");
                continue; // Skip SQLite internal table
            }
            $pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
            $count++;
        }

        return $count;
    }

    /**
     * Disable foreign key constraints for table
     *
     * @param PDO $pdo
     * @return void
     */
    #[\Override]
    public function disableForeignKeyConstraints(PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys = OFF');
    }

    /**
     * Eisable foreign key constraints for table
     *
     * @param PDO $pdo
     * @return void
     */
    #[\Override]
    public function enableForeignKeyConstraints(PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    /**
     * Truncate a given table
     *
     * @param PDO $pdo
     * @param string $table
     * @param bool $resetAutoIncrement
     * @return int
     */
    #[\Override]
    public function truncate(PDO $pdo, string $table, bool $resetAutoIncrement = true): int
    {
        $count = $pdo->exec("DELETE FROM \"{$table}\"");

        if ($resetAutoIncrement) {
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }

        return $count;
    }

    /**
     * Drop a given table
     *
     * @param PDO $pdo
     * @param string $table
     * @return int
     */
    #[\Override]
    public function dropTable(PDO $pdo, string $table): int
    {
        return $pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
    }

    /**
     * Delete all records from a given table
     *
     * @param PDO $pdo
     * @param string $table
     * @return int
     */
    #[\Override]
    public function deleteAll(PDO $pdo, string $table): int
    {
        return $pdo->exec("DELETE FROM \"{$table}\"");
    }
}
