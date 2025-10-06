<?php

namespace Phaseolies\Database\Drivers;

use PDO;
use Phaseolies\Database\Contracts\Driver\DriverInterface;

class MySQLDriver implements DriverInterface
{
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

        if (!empty($config['options'][PDO::MYSQL_ATTR_SSL_CA])) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $config['options'][PDO::MYSQL_ATTR_SSL_CA];
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;

            $sslOptions = [
                PDO::MYSQL_ATTR_SSL_CERT,
                PDO::MYSQL_ATTR_SSL_KEY,
                PDO::MYSQL_ATTR_SSL_CAPATH,
                PDO::MYSQL_ATTR_SSL_CIPHER
            ];

            foreach ($sslOptions as $sslOption) {
                if (!empty($config['options'][$sslOption])) {
                    $options[$sslOption] = $config['options'][$sslOption];
                }
            }
        }

        if (!empty($config['options'][PDO::MYSQL_ATTR_INIT_COMMAND])) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = $config['options'][PDO::MYSQL_ATTR_INIT_COMMAND];
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

    public function getTables(PDO $pdo): array
    {
        $stmt = $pdo->query('SHOW TABLES');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTableColumns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query("DESCRIBE {$table}");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $result = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            return $result !== false;
        } catch (\PDOException $e) {
            return false;
        }
    }

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

    public function disableForeignKeyConstraints(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    }

    public function enableForeignKeyConstraints(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function truncate(PDO $pdo, string $table, bool $resetAutoIncrement = true): int
    {
        if ($resetAutoIncrement) {
            return (int) $pdo->exec("TRUNCATE TABLE {$table}");
        }
        return (int) $pdo->exec("DELETE FROM {$table}");
    }

    public function dropTable(PDO $pdo, string $table): int
    {
        return (int) $pdo->exec("DROP TABLE {$table}");
    }

    public function deleteAll(PDO $pdo, string $table): int
    {
        return (int) $pdo->exec("DELETE FROM {$table}");
    }
}
