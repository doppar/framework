<?php

namespace Phaseolies\Database\Drivers;

use PDO;
use Phaseolies\Database\Contracts\Driver\DriverInterface;

class PostgreSQLDriver implements DriverInterface
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
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['host'],
            $config['port'],
            $config['database']
        );

        if (!empty($config['sslmode'])) {
            $dsn .= ";sslmode={$config['sslmode']}";
        }
        if (!empty($config['sslrootcert'])) {
            $dsn .= ";sslrootcert={$config['sslrootcert']}";
        }
        if (!empty($config['sslcert'])) {
            $dsn .= ";sslcert={$config['sslcert']}";
        }
        if (!empty($config['sslkey'])) {
            $dsn .= ";sslkey={$config['sslkey']}";
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        $pdo = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $options
        );

        // Set client encoding (charset) if specified
        if (!empty($config['charset'])) {
            $pdo->exec("SET NAMES '{$config['charset']}'");
        }

        // Set search path
        $searchPath = $config['search_path'] ?? 'public';
        $pdo->exec("SET search_path TO {$searchPath}");

        return $pdo;
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
        $stmt = $pdo->query("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_type = 'BASE TABLE'
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
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
        $stmt = $pdo->query("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = '{$table}'
            ORDER BY ordinal_position
        ");

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
        try {
            $stmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'public'
                AND table_name = ?
            ");

            $stmt->execute([$table]);

            return $stmt->fetch() !== false;
        } catch (\PDOException $e) {
            return false;
        }
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
    public function callProcedure(PDO $pdo, string $name, array $params = [], array &$outputParams = []): array
    {
        $placeholders = implode(',', array_fill(0, count($params), '?'));

        $stmt = $pdo->prepare("SELECT {$name}({$placeholders})");

        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i++, $param);
        }

        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute();

        $results = [];
        do {
            $results[] = $stmt->fetchAll();
        } while ($stmt->nextRowset());

        return $results;
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
        $this->disableForeignKeyConstraints($pdo);
        try {
            $tables = $this->getTables($pdo);
            if (!empty($tables)) {
                $pdo->exec('DROP TABLE ' . implode(', ', $tables) . ' CASCADE');
            }
            $this->enableForeignKeyConstraints($pdo);
            return count($tables);
        } catch (\PDOException $e) {
            $this->enableForeignKeyConstraints($pdo);
            throw $e;
        }
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
        $pdo->exec('SET CONSTRAINTS ALL DEFERRED');
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
        $pdo->exec('SET CONSTRAINTS ALL IMMEDIATE');
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
        if ($resetAutoIncrement) {
            return (int) $pdo->exec("TRUNCATE TABLE {$table} RESTART IDENTITY CASCADE");
        }
        return (int) $pdo->exec("TRUNCATE TABLE {$table} CONTINUE IDENTITY CASCADE");
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
        return (int) $pdo->exec("DROP TABLE {$table} CASCADE");
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
        return (int) $pdo->exec("DELETE FROM {$table}");
    }

    /**
     * PostgreSQL-specific method to get sequences
     *
     * @param PDO $pdo
     * @return array
     */
    public function getSequences(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT sequence_name
            FROM information_schema.sequences
            WHERE sequence_schema = 'public'
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * PostgreSQL-specific method to reset sequence
     *
     * @param PDO $pdo
     * @param string $sequence
     * @param int $value
     * @return bool
     */
    public function resetSequence(PDO $pdo, string $sequence, int $value = 1): bool
    {
        return (bool) $pdo->exec("ALTER SEQUENCE {$sequence} RESTART WITH {$value}");
    }
}
