<?php

namespace Phaseolies\Database\Drivers;

use PDO;
use Pdo\Mysql;
use Phaseolies\Database\Contracts\Driver\DriverInterface;

class MySQLDriver implements DriverInterface
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
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        $sslCa = $this->mysqlAttr('ATTR_SSL_CA', 'MYSQL_ATTR_SSL_CA');

        if ($sslCa !== null && !empty($config['options'][$sslCa])) {
            $options[$sslCa] = $config['options'][$sslCa];

            $verify = $this->mysqlAttr(
                'ATTR_SSL_VERIFY_SERVER_CERT',
                'MYSQL_ATTR_SSL_VERIFY_SERVER_CERT'
            );

            if ($verify !== null) {
                $options[$verify] = false;
            }

            foreach (
                [
                    ['ATTR_SSL_CERT', 'MYSQL_ATTR_SSL_CERT'],
                    ['ATTR_SSL_KEY', 'MYSQL_ATTR_SSL_KEY'],
                    ['ATTR_SSL_CAPATH', 'MYSQL_ATTR_SSL_CAPATH'],
                    ['ATTR_SSL_CIPHER', 'MYSQL_ATTR_SSL_CIPHER'],
                ] as [$new, $old]
            ) {

                $attr = $this->mysqlAttr($new, $old);

                if ($attr !== null && isset($config['options'][$attr])) {
                    $options[$attr] = $config['options'][$attr];
                }
            }
        }

        $attr = $this->mysqlAttr('ATTR_INIT_COMMAND', 'MYSQL_ATTR_INIT_COMMAND');

        if ($attr !== null && isset($config['options'][$attr])) {
            $options[$attr] = $config['options'][$attr];
        }

        $pdo = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $options
        );

        if (!empty($config['collation'])) {
            $pdo->exec("SET NAMES '{$config['charset']}' COLLATE '{$config['collation']}'");
        }

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
        $stmt = $pdo->query('SHOW TABLES');

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
        $stmt = $pdo->query("DESCRIBE {$table}");

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
            $result = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            return $result !== false;
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
        $placeholders = implode(',', array_fill(0, count($params) + count($outputParams), '?'));
        $stmt = $pdo->prepare("CALL {$name}({$placeholders})");

        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i++, $param);
        }

        foreach ($outputParams as &$param) {
            $stmt->bindParam($i++, $param, PDO::PARAM_INPUT_OUTPUT);
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
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $tables = $this->getTables($pdo);
            if (!empty($tables)) {
                $pdo->exec('DROP TABLE ' . implode(', ', $tables));
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            return count($tables);
        } catch (\PDOException $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
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
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
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
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
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
            return (int) $pdo->exec("TRUNCATE TABLE {$table}");
        }

        return (int) $pdo->exec("DELETE FROM {$table}");
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
        return (int) $pdo->exec("DROP TABLE {$table}");
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
     * Resolve a MySQL PDO attribute constant in a version-compatible way
     *
     * PHP 8.5 moved MySQL-specific PDO constants from PDO::MYSQL_ATTR_*
     * to the namespaced Pdo\Mysql::ATTR_* class and deprecated the old ones.
     *
     * This helper safely resolves the correct constant for:
     *  - PHP 8.5+ (Pdo\Mysql::ATTR_*)
     *  - PHP 8.3 / 8.4 (PDO::MYSQL_ATTR_*)
     *
     * If neither constant exists (e.g., extension not available),
     * null is returned.
     *
     * @param string $new The new constant name (e.g. 'ATTR_SSL_CA')
     * @param string $old The legacy constant name (e.g. 'MYSQL_ATTR_SSL_CA')
     * @return int|null
     */
    private function mysqlAttr(string $new, string $old): ?int
    {
        if (class_exists(Mysql::class) && defined("Pdo\\Mysql::$new")) {
            return constant("Pdo\\Mysql::$new");
        }

        if (defined("PDO::$old")) {
            return constant("PDO::$old");
        }

        return null;
    }
}
