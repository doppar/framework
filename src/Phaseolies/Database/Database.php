<?php

namespace Phaseolies\Database;

use Phaseolies\Database\Query\Builder;
use Phaseolies\Database\Procedure\ProcedureResult;
use Phaseolies\Database\Eloquent\Model;
use PDOException;
use PDO;

class Database
{
    /**
     * The active PDO connection
     *
     * @var PDO
     */
    protected static $pdo;

    /**
     * The transaction level counter
     *
     * @var int
     */
    protected static $transactions = 0;

    /**
     * Get the PDO instance
     * @return PDO
     */
    public static function getPdoInstance(): PDO
    {
        if (!isset(static::$pdo)) {
            $host = env('DB_HOST', config('database.connections.mysql.host'));
            $dbName = env('DB_DATABASE', config('database.connections.mysql.database'));
            $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8";
            $username = env('DB_USERNAME', config('database.connections.mysql.username'));
            $password = env('DB_PASSWORD', config('database.connections.mysql.password'));

            try {
                static::$pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                throw new \PDOException("Failed to connect to database: " . $e->getMessage());
            }
        }

        return static::$pdo;
    }

    /**
     * Start a new database transaction
     *
     * @return void
     * @throws PDOException
     */
    public static function beginTransaction(): void
    {
        if (static::$transactions == 0) {
            static::getPdoInstance()->beginTransaction();
        } else {
            static::getPdoInstance()->exec("SAVEPOINT trans" . (static::$transactions + 1));
        }

        static::$transactions++;
    }

    /**
     * Commit the active database transaction
     *
     * @return void
     * @throws PDOException
     */
    public static function commit(): void
    {
        if (static::$transactions == 1) {
            static::getPdoInstance()->commit();
        }

        static::$transactions = max(0, static::$transactions - 1);
    }

    /**
     * Rollback the active database transaction
     *
     * @return void
     * @throws PDOException
     */
    public static function rollBack(): void
    {
        if (static::$transactions == 1) {
            static::getPdoInstance()->rollBack();
        } else {
            static::getPdoInstance()->exec("ROLLBACK TO SAVEPOINT trans" . static::$transactions);
        }

        static::$transactions = max(0, static::$transactions - 1);
    }

    /**
     * Execute a Closure within a transaction
     *
     * @param  \Closure  $callback
     * @param  int  $attempts
     * @return mixed
     * @throws \Throwable
     */
    public static function transaction(\Closure $callback, int $attempts = 1)
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            static::beginTransaction();

            try {
                $result = $callback();
                static::commit();
                return $result;
            } catch (\Throwable $e) {
                static::rollBack();

                if ($currentAttempt >= $attempts) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Get the number of active transactions
     *
     * @return int
     */
    public static function transactionLevel(): int
    {
        return static::$transactions;
    }

    /**
     * Get the column names for a given table
     *
     * @param string|null $table
     * @return array
     * @throws PDOException
     */
    public static function getTableColumns(?string $table = null): array
    {
        if ($table === null) {
            throw new \InvalidArgumentException('Table name cannot be null');
        }

        try {
            $stmt = static::getPdoInstance()->query("DESCRIBE {$table}");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            throw new PDOException("Failed to get table columns: " . $e->getMessage());
        }
    }

    /**
     * Get the list of tables in the database
     *
     * @return array
     */
    public static function getTables(): array
    {
        $stmt = static::getPdoInstance()->query("SHOW TABLES");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Check if a table exists in the database
     *
     * @param string $table
     * @return bool
     */
    public static function tableExists(string $table): bool
    {
        try {
            $result = static::getPdoInstance()->query("SELECT 1 FROM {$table} LIMIT 1");
            return $result !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get table name from model instance
     *
     * @param Model $model
     * @return string
     * @throws InvalidArgumentException
     */
    public static function getTable(Model $model): string
    {
        return $model->getTable();
    }

    /**
     * Get the database connection
     *
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        return static::getPdoInstance();
    }

    /**
     * Execute a stored procedure with optional result flattening
     *
     * @param string $procedureName
     * @param array $params
     * @param array $outputParams
     * @return ProcedureResult
     * @throws PDOException
     */
    public static function executeProcedure(
        string $procedureName,
        array $params = [],
        array $outputParams = []
    ): ProcedureResult {
        $pdo = static::getPdoInstance();

        $placeholders = implode(',', array_fill(0, count($params) + count($outputParams), '?'));
        $stmt = $pdo->prepare("CALL {$procedureName}({$placeholders})");

        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i++, $param);
        }

        foreach ($outputParams as &$param) {
            $stmt->bindParam($i++, $param, PDO::PARAM_INPUT_OUTPUT);
        }

        $stmt->execute();

        $results = [];
        do {
            $results[] = $stmt->fetchAll();
        } while ($stmt->nextRowset());

        return new ProcedureResult($results);
    }

    /**
     * Execute a view and return results
     *
     * @param string $viewName
     * @param array $where
     * @param array $params
     * @return array
     * @throws PDOException
     */
    public static function executeView(string $viewName, array $where = [], array $params = []): array
    {
        $sql = "SELECT * FROM {$viewName}";

        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                $conditions[] = "{$column} = :{$column}";
                $params[":{$column}"] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = static::getPdoInstance()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Execute a raw SQL query
     *
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     * @throws PDOException
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = static::getPdoInstance()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Execute a raw SQL statement (INSERT, UPDATE, DELETE)
     *
     * @param string $sql
     * @param array $params
     * @return int Number of affected rows
     * @throws PDOException
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = static::getPdoInstance()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Drop all tables in the database
     *
     * @return void
     * @throws PDOException
     */
    public static function dropAllTables(): void
    {
        $pdo = static::getPdoInstance();

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $tables = static::getTables();

            if (!empty($tables)) {
                $pdo->exec('DROP TABLE ' . implode(', ', $tables));
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (PDOException $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            throw new PDOException("Failed to drop all tables: " . $e->getMessage());
        }
    }

    /**
     * Begin a fluent query against a database table
     *
     * @param string $table
     * @return Builder
     */
    public static function table(string $table): Builder
    {
        return new Builder($table);
    }
}
