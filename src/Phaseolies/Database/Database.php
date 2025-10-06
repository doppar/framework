<?php

namespace Phaseolies\Database;

use Phaseolies\Support\Collection;
use Phaseolies\Database\Query\RawExpression;
use Phaseolies\Database\Query\Builder;
use Phaseolies\Database\Procedure\ProcedureResult;
use Phaseolies\Database\Eloquent\Model;
use Phaseolies\Database\Connectors\ConnectionFactory;
use PDOException;
use PDO;

class Database
{
    /**
     * The active PDO connections
     *
     * @var array
     */
    protected static $connections = [];

    /**
     * The transaction level counters for each connection
     *
     * @var array
     */
    protected static $transactions = [];

    /**
     * The driver instances for each connection
     *
     * @var array<string, \Phaseolies\Database\Contracts\Driver\DriverInterface>
     */
    protected static $drivers = [];

    /**
     * The connection name for this instance
     */
    protected ?string $connection;

    /**
     * Create a new database manager instance
     *
     * @param string|null $connection
     */
    public function __construct(?string $connection = null)
    {
        $this->connection = $connection;
    }

    /**
     * Get a PDO instance for the specified connection
     *
     * @param string|null $connection Connection name (null for default)
     * @return PDO
     * @throws \RuntimeException When database configuration is invalid
     * @throws \PDOException When connection fails
     */
    public static function getPdoInstance(?string $connection = null): PDO
    {
        $connection = $connection ?: config('database.default');

        if (!isset(static::$connections[$connection])) {
            $config = config("database.connections.{$connection}");

            if (empty($config)) {
                throw new \RuntimeException("Database connection [{$connection}] not configured.");
            }

            try {
                // Create and store the driver instance
                $driver = ConnectionFactory::createDriver($config);
                static::$drivers[$connection] = $driver;

                // Create the connection using the driver
                static::$connections[$connection] = $driver->connect($config);
            } catch (PDOException $e) {
                throw new \PDOException(
                    "Failed to connect to database [{$connection}]: " . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            } catch (\RuntimeException $e) {
                throw new \RuntimeException(
                    "Database connection error [{$connection}]: " . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }
        }

        return static::$connections[$connection];
    }

    /**
     * Get the PDO instance for this connection
     *
     * @return PDO
     */
    protected function getPdo(): PDO
    {
        return static::getPdoInstance($this->connection);
    }

    /**
     * Get the driver instance for a connection
     *
     * @param string|null $connection
     * @return \Phaseolies\Database\Contracts\Driver\DriverInterface
     */
    protected static function getDriverInstance(?string $connection = null)
    {
        $connection = $connection ?: config('database.default');

        if (!isset(static::$drivers[$connection])) {
            static::getPdoInstance($connection);
        }

        return static::$drivers[$connection];
    }

    /**
     * Begin a transaction on the specified connection
     *
     * @throws PDOException
     * @return void
     */
    public function beginTransaction(): void
    {
        $pdo = $this->getPdo();
        $connection = $this->connection ?? config('database.default');

        if (!isset(static::$transactions[$connection])) {
            static::$transactions[$connection] = 0;
        }

        if (static::$transactions[$connection] === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec("SAVEPOINT trans" . (static::$transactions[$connection] + 1));
        }

        static::$transactions[$connection]++;
    }

    /**
     * Commit a transaction on the specified connection
     *
     * @return void
     * @throws PDOException
     */
    public function commit(): void
    {
        $pdo = $this->getPdo();
        $connection = $this->connection ?? config('database.default');

        if (static::$transactions[$connection] === 1) {
            $pdo->commit();
        }

        static::$transactions[$connection] = max(0, static::$transactions[$connection] - 1);
    }

    /**
     * Rollback a transaction on the specified connection
     *
     * @return void
     * @throws PDOException
     */
    public function rollBack(): void
    {
        $pdo = $this->getPdo();
        $connection = $this->connection ?? config('database.default');

        if (static::$transactions[$connection] === 1) {
            $pdo->rollBack();
        } else {
            $pdo->exec("ROLLBACK TO SAVEPOINT trans" . static::$transactions[$connection]);
        }

        static::$transactions[$connection] = max(0, static::$transactions[$connection] - 1);
    }

    /**
     * Execute a Closure within a transaction
     *
     * @param \Closure $callback
     * @param int $attempts
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(\Closure $callback, int $attempts = 1)
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $this->beginTransaction();

            try {
                $result = $callback();
                $this->commit();
                return $result;
            } catch (\Throwable $e) {
                $this->rollBack();

                if ($currentAttempt >= $attempts) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Get the transaction level for a connection
     *
     * @param string|null $connection
     * @return int
     */
    public static function transactionLevel(?string $connection = null): int
    {
        $connection = $connection ?: config('database.default');

        return static::$transactions[$connection] ?? 0;
    }

    /**
     * Get the column names for a given table
     *
     * @param string|null $table
     * @return array
     * @throws PDOException
     */
    public function getTableColumns(?string $table = null): array
    {
        if ($table === null) {
            throw new \InvalidArgumentException('Table name cannot be null');
        }

        try {
            $stmt = $this->getPdo()->query("DESCRIBE {$table}");

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
    public function getTables(): array
    {
        $stmt = $this->getPdo()->query("SHOW TABLES");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Check if a table exists in the database
     *
     * @param string $table
     * @return bool
     */
    public function tableExists(string $table): bool
    {
        try {
            $result = $this->getPdo()->query("SELECT 1 FROM {$table} LIMIT 1");

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
     * @throws \InvalidArgumentException
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
    public function procedure(string $procedureName, array $params = [], array $outputParams = []): ProcedureResult
    {
        $pdo = $this->getPdo();

        $placeholders = implode(',', array_fill(0, count($params) + count($outputParams), '?'));
        $stmt = $pdo->prepare("CALL {$procedureName}({$placeholders})");

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
    public static function view(string $viewName, array $where = [], array $params = []): array
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
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Execute a raw SQL query and return results as a Collection
     *
     * @param string $sql
     * @param array $params
     * @return \Phaseolies\Support\Collection
     * @throws PDOException
     */
    public function query(string $sql, array $params = []): Collection
    {
        try {
            $stmt = $this->getPdo()->prepare($sql);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $stmt->execute($params);
            $results = $stmt->fetchAll();

            return new \Phaseolies\Support\Collection(
                'array',
                count($results) > 1 ? $results : $results[0] ?? []
            );
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Execute a raw SQL query
     *
     * @param string $sql
     * @param array $params
     * @return \PDOStatement
     * @throws \PDOException
     */
    public function statement(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
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
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->getPdo()->prepare($sql);

        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Drop all tables in the database
     *
     * @return int Number of dropped tables
     * @throws PDOException
     */
    public function dropAllTables(): int
    {
        $driver = static::getDriverInstance($this->connection);
        return $driver->dropAllTables($this->getPdo());
    }

    /**
     * Disable foreign key constraints
     *
     * @return void
     */
    public function disableForeignKeyConstraints(): void
    {
        $driver = static::getDriverInstance($this->connection);
        $driver->disableForeignKeyConstraints($this->getPdo());
    }

    /**
     * Enable foreign key constraints
     *
     * @return void
     */
    public function enableForeignKeyConstraints(): void
    {
        $driver = static::getDriverInstance($this->connection);
        $driver->enableForeignKeyConstraints($this->getPdo());
    }

    /**
     * Create a raw SQL expression
     *
     * @param string $expression
     * @param array $bindings
     * @return RawExpression
     */
    public function sql(string $expression, array $bindings = []): RawExpression
    {
        return new RawExpression($expression, $bindings);
    }

    /**
     * Begin a fluent query against a database table
     *
     * @param string $table
     * @return Builder
     */
    public function table(string $table): Builder
    {
        $driver = self::getDriverInstance($this->connection);
        return new Builder($table, $this->getPdo(), $driver);
    }

    /**
     * Get a connection instance for the specified connection name
     *
     * @param string|null $name Connection name (null for default)
     * @return self
     */
    public function connection(?string $name = null): self
    {
        return new static($name);
    }
}
