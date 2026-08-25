<?php

namespace Tests\Support\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Phaseolies\Http\Request;
use Phaseolies\Support\UrlGenerator;
use Tests\Support\MockContainer;

abstract class ModelQueryDriverTestCase extends TestCase
{
    protected PDO $pdo;

    /**
     * @var array<class-string, bool>
     */
    private static array $schemaBooted = [];

    final protected function setUp(): void
    {
        parent::setUp();

        $this->bootContainer();
        $this->pdo = $this->createDriverPdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (!isset(self::$schemaBooted[static::class])) {
            $this->dropTablesIfExist(array_keys($this->tableDefinitions()));
            $this->createTables();
            self::$schemaBooted[static::class] = true;
        }

        $this->resetTables();
        $this->seedTables();
        $this->setupDatabaseConnections();
    }

    final protected function tearDown(): void
    {
        $this->tearDownDatabaseConnections();
        unset($this->pdo);

        parent::tearDown();
    }

    abstract protected static function driverName(): string;

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    abstract protected function tableDefinitions(): array;

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    abstract protected function seedData(): array;

    protected function bootContainer(): void
    {
        Container::setInstance(new MockContainer());
        $container = new Container();
        $container->bind('request', fn() => new Request());
        $container->bind('url', fn() => UrlGenerator::class);
        $container->bind('db', fn() => new Database('default'));
    }

    protected function createTables(): void
    {
        foreach ($this->tableDefinitions() as $table => $columns) {
            $this->createTable($table, $columns);
        }
    }

    protected function seedTables(): void
    {
        foreach ($this->seedData() as $table => $rows) {
            if ($rows === []) {
                continue;
            }

            $this->insertRows($table, $rows);
        }
    }

    protected function setupDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
        $this->setStaticProperty(Database::class, 'drivers', []);
        $this->setStaticProperty(Database::class, 'connections', [
            'default' => $this->pdo,
            static::driverName() => $this->pdo,
        ]);
    }

    protected function tearDownDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
        $this->setStaticProperty(Database::class, 'drivers', []);
    }

    /**
     * @param array<int, string> $tables
     */
    protected function dropTablesIfExist(array $tables): void
    {
        foreach (array_reverse($tables) as $table) {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS %s', $this->quoteIdentifier($table)));
        }
    }

    protected function resetTables(): void
    {
        $tables = array_keys($this->tableDefinitions());

        if ($tables === []) {
            return;
        }

        match (static::driverName()) {
            'sqlite' => $this->resetSqliteTables($tables),
            'mysql' => $this->resetMysqlTables($tables),
            'pgsql' => $this->resetPgsqlTables($tables),
            default => throw new \RuntimeException('Unsupported driver [' . static::driverName() . '].'),
        };
    }

    /**
     * @param array<int, string> $tables
     */
    protected function resetSqliteTables(array $tables): void
    {
        foreach (array_reverse($tables) as $table) {
            $this->pdo->exec(sprintf('DELETE FROM %s', $this->quoteIdentifier($table)));
        }

        $sequenceExists = $this->pdo
            ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'")
            ?->fetchColumn();

        if (!$sequenceExists) {
            return;
        }

        $statement = $this->pdo->prepare('DELETE FROM sqlite_sequence WHERE name = ?');

        foreach ($tables as $table) {
            $statement->execute([$table]);
        }
    }

    /**
     * @param array<int, string> $tables
     */
    protected function resetMysqlTables(array $tables): void
    {
        foreach (array_reverse($tables) as $table) {
            $this->pdo->exec(sprintf('TRUNCATE TABLE %s', $this->quoteIdentifier($table)));
        }
    }

    /**
     * @param array<int, string> $tables
     */
    protected function resetPgsqlTables(array $tables): void
    {
        $quotedTables = array_map(fn(string $table): string => $this->quoteIdentifier($table), array_reverse($tables));

        $this->pdo->exec(sprintf(
            'TRUNCATE TABLE %s RESTART IDENTITY CASCADE',
            implode(', ', $quotedTables)
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    protected function createTable(string $table, array $columns): void
    {
        $definitions = array_map(fn(array $column): string => $this->compileColumn($column), $columns);

        $sql = sprintf(
            'CREATE TABLE %s (%s)',
            $this->quoteIdentifier($table),
            implode(', ', $definitions)
        );

        $this->pdo->exec($sql);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    protected function insertRows(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $columns = array_keys($row);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $quotedColumns = implode(', ', array_map(fn(string $column): string => $this->quoteIdentifier($column), $columns));

            $statement = $this->pdo->prepare(sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->quoteIdentifier($table),
                $quotedColumns,
                $placeholders
            ));

            $statement->execute(array_values($row));
        }
    }

    /**
     * @param array<string, mixed> $column
     */
    protected function compileColumn(array $column): string
    {
        if (($column['type'] ?? null) === 'id') {
            return $this->compileIdentityColumn($column['name']);
        }

        $sql = sprintf(
            '%s %s',
            $this->quoteIdentifier($column['name']),
            $this->compileType($column)
        );

        if (($column['nullable'] ?? false) === false) {
            $sql .= ' NOT NULL';
        }

        if (($column['unique'] ?? false) === true) {
            $sql .= ' UNIQUE';
        }

        if (array_key_exists('default', $column)) {
            $sql .= ' DEFAULT ' . $this->compileDefaultValue($column['default'], $column['type']);
        }

        return $sql;
    }

    protected function compileIdentityColumn(string $name): string
    {
        return match (static::driverName()) {
            'sqlite' => sprintf('%s INTEGER PRIMARY KEY AUTOINCREMENT', $this->quoteIdentifier($name)),
            'mysql' => sprintf('%s INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $this->quoteIdentifier($name)),
            'pgsql' => sprintf('%s SERIAL PRIMARY KEY', $this->quoteIdentifier($name)),
            default => throw new \RuntimeException('Unsupported driver [' . static::driverName() . '].'),
        };
    }

    /**
     * @param array<string, mixed> $column
     */
    protected function compileType(array $column): string
    {
        return match ($column['type']) {
            'string' => 'VARCHAR(' . ($column['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'integer' => 'INTEGER',
            'boolean' => static::driverName() === 'sqlite' ? 'INTEGER' : 'BOOLEAN',
            'datetime' => match (static::driverName()) {
                'sqlite' => 'TEXT',
                'mysql' => 'DATETIME',
                'pgsql' => 'TIMESTAMP',
            },
            'real' => 'REAL',
            'decimal' => sprintf('DECIMAL(%d,%d)', $column['precision'] ?? 10, $column['scale'] ?? 2),
            default => throw new \InvalidArgumentException('Unsupported column type [' . $column['type'] . '].'),
        };
    }

    protected function compileDefaultValue(mixed $value, string $type): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($type === 'boolean') {
            return match (static::driverName()) {
                'pgsql' => $value ? 'TRUE' : 'FALSE',
                default => $value ? '1' : '0',
            };
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    protected function quoteIdentifier(string $identifier): string
    {
        $quote = static::driverName() === 'mysql' ? '`' : '"';

        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }

    protected function createDriverPdo(): PDO
    {
        return match (static::driverName()) {
            'sqlite' => $this->createSqlitePdo(),
            'mysql' => $this->createMysqlPdo(),
            'pgsql' => $this->createPgsqlPdo(),
            default => throw new \RuntimeException('Unsupported driver [' . static::driverName() . '].'),
        };
    }

    protected function createSqlitePdo(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for SQLite model query tests.');
        }

        return new PDO('sqlite:' . $this->sqliteschemaPath());
    }

    protected function createMysqlPdo(): PDO
    {
        if (!extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('The pdo_mysql extension is required for MySQL model query tests.');
        }

        [$dsn, $username, $password, $configured] = $this->mysqlConnectionConfig();

        if (!$configured) {
            $this->markTestSkipped('Configure DOPPAR_TEST_MYSQL_DSN or DOPPAR_TEST_MYSQL_HOST/DATABASE to run MySQL model query tests.');
        }

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\Throwable $exception) {
            $this->fail('Unable to connect to MySQL test database: ' . $exception->getMessage());
        }
    }

    protected function createPgsqlPdo(): PDO
    {
        if (!extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('The pdo_pgsql extension is required for PostgreSQL model query tests.');
        }

        [$dsn, $username, $password, $configured] = $this->pgsqlConnectionConfig();

        if (!$configured) {
            $this->markTestSkipped('Configure DOPPAR_TEST_PGSQL_DSN or DOPPAR_TEST_PGSQL_HOST/DATABASE to run PostgreSQL model query tests.');
        }

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\Throwable $exception) {
            $this->fail('Unable to connect to PostgreSQL test database: ' . $exception->getMessage());
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: bool}
     */
    protected function mysqlConnectionConfig(): array
    {
        $dsn = getenv('DOPPAR_TEST_MYSQL_DSN') ?: '';

        if ($dsn !== '') {
            return [
                $dsn,
                (string) (getenv('DOPPAR_TEST_MYSQL_USERNAME') ?: 'root'),
                (string) (getenv('DOPPAR_TEST_MYSQL_PASSWORD') ?: ''),
                true,
            ];
        }

        $database = getenv('DOPPAR_TEST_MYSQL_DATABASE') ?: '';

        if ($database === '') {
            return ['', '', '', false];
        }

        $host = getenv('DOPPAR_TEST_MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('DOPPAR_TEST_MYSQL_PORT') ?: '3306';
        $charset = getenv('DOPPAR_TEST_MYSQL_CHARSET') ?: 'utf8mb4';

        return [
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset),
            (string) (getenv('DOPPAR_TEST_MYSQL_USERNAME') ?: 'root'),
            (string) (getenv('DOPPAR_TEST_MYSQL_PASSWORD') ?: ''),
            true,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: bool}
     */
    protected function pgsqlConnectionConfig(): array
    {
        $dsn = getenv('DOPPAR_TEST_PGSQL_DSN') ?: '';

        if ($dsn !== '') {
            return [
                $dsn,
                (string) (getenv('DOPPAR_TEST_PGSQL_USERNAME') ?: 'postgres'),
                (string) (getenv('DOPPAR_TEST_PGSQL_PASSWORD') ?: ''),
                true,
            ];
        }

        $database = getenv('DOPPAR_TEST_PGSQL_DATABASE') ?: '';

        if ($database === '') {
            return ['', '', '', false];
        }

        $host = getenv('DOPPAR_TEST_PGSQL_HOST') ?: '127.0.0.1';
        $port = getenv('DOPPAR_TEST_PGSQL_PORT') ?: '5432';

        return [
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database),
            (string) (getenv('DOPPAR_TEST_PGSQL_USERNAME') ?: 'postgres'),
            (string) (getenv('DOPPAR_TEST_PGSQL_PASSWORD') ?: ''),
            true,
        ];
    }

    protected function sqliteschemaPath(): string
    {
        $className = str_replace('\\', '-', static::class);

        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . strtolower($className) . '.sqlite';
    }

    protected function setStaticProperty(string $className, string $propertyName, mixed $value): void
    {
        try {
            $reflection = new \ReflectionClass($className);
            $property = $reflection->getProperty($propertyName);
            $property->setValue(null, $value);
        } catch (\ReflectionException $exception) {
            $this->fail('Failed to set static property ' . $propertyName . ': ' . $exception->getMessage());
        }
    }
}
