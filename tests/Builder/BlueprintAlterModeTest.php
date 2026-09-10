<?php

namespace Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Migration\Blueprint;
use Phaseolies\DI\Container;
use PDO;

/**
 * Blueprint must know explicitly whether it's building a CREATE TABLE
 * (Schema::create()) or modifying an existing table (Schema::table()),
 * instead of inferring this from whether any column happens to use
 * after() — that heuristic meant Schema::table() silently generated a
 * bogus CREATE TABLE (failing with "table already exists") on every
 * driver whenever no column used after(), which is the common case.
 */
class BlueprintAlterModeTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(Container::class);
        $property = $reflection->getProperty('instance');
        $property->setValue(null, null);
    }

    private function bindFakeDriver(string $driverName): void
    {
        $container = new Container();
        $container->bind('db', fn() => new class($driverName) {
            public function __construct(private string $driverName)
            {
            }
            public function getConnection()
            {
                return new class($this->driverName) {
                    public function __construct(private string $driverName)
                    {
                    }
                    public function getAttribute($attribute)
                    {
                        return $this->driverName;
                    }
                };
            }
        });

        Container::setInstance($container);
    }

    public function testAlterModeProducesAddColumnWithoutAfterOnSqlite(): void
    {
        $this->bindFakeDriver('sqlite');

        $blueprint = new Blueprint('users', 'sqlite', false);
        $blueprint->string('nickname')->nullable();

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('ALTER TABLE `users` ADD COLUMN nickname TEXT NULL', $sql);
        $this->assertStringNotContainsString('CREATE TABLE', $sql);
    }

    public function testAlterModeProducesAddColumnWithoutAfterOnPostgres(): void
    {
        $this->bindFakeDriver('pgsql');

        $blueprint = new Blueprint('users', 'pgsql', false);
        $blueprint->string('nickname')->nullable();

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('ALTER TABLE "users" ADD COLUMN nickname VARCHAR(255) NULL', $sql);
        $this->assertStringNotContainsString('CREATE TABLE', $sql);
    }

    public function testAlterModeProducesAddColumnWithoutAfterOnMysql(): void
    {
        $this->bindFakeDriver('mysql');

        $blueprint = new Blueprint('users', 'mysql', false);
        $blueprint->string('nickname')->nullable();

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('ADD COLUMN nickname VARCHAR(255) NULL', $sql);
        $this->assertStringNotContainsString('CREATE TABLE', $sql);
    }

    public function testCreateModeIgnoresColumnOrderingForBranchSelection(): void
    {
        // Regression guard: create mode must always produce CREATE
        // TABLE, even when a column uses after() (which is meaningless
        // for a brand-new table but must not flip the statement type).
        $this->bindFakeDriver('mysql');

        $blueprint = new Blueprint('users', 'mysql', true);
        $blueprint->id();
        $blueprint->string('nickname')->after('id');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringNotContainsString('ALTER TABLE', $sql);
        // AFTER is only valid in MySQL's ALTER TABLE ADD COLUMN syntax,
        // not CREATE TABLE — must not appear here.
        $this->assertStringNotContainsString('AFTER', $sql);
    }

    public function testAlterModeIncludesAfterClauseOnMysql(): void
    {
        $this->bindFakeDriver('mysql');

        $blueprint = new Blueprint('users', 'mysql', false);
        $blueprint->string('nickname')->after('id');

        $sql = $blueprint->toSql();

        $this->assertStringContainsString('AFTER id', $sql);
    }

    public function testAlteringExistingSqliteTableAddsColumnAtDatabaseLevel(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');

        $container = new Container();
        $container->bind('db', fn() => new class($pdo) {
            public function __construct(private PDO $pdo)
            {
            }
            public function getConnection()
            {
                return $this->pdo;
            }
        });
        Container::setInstance($container);

        $blueprint = new Blueprint('users', 'sqlite', false);
        $blueprint->string('nickname')->nullable();

        $pdo->exec($blueprint->toSql());

        $columns = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        $this->assertContains('nickname', $columns);
    }

    public function testAddingUniqueColumnViaAlterOnSqliteThrowsClearError(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');

        $container = new Container();
        $container->bind('db', fn() => new class($pdo) {
            public function __construct(private PDO $pdo)
            {
            }
            public function getConnection()
            {
                return $this->pdo;
            }
        });
        Container::setInstance($container);

        $blueprint = new Blueprint('users', 'sqlite', false);
        $blueprint->string('code')->unique();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot add a UNIQUE constraint');

        // toSql() itself throws — handleIndexAndUniqueColumn() runs
        // during SQL generation, before anything touches the database.
        $blueprint->toSql();
    }

    public function testCreatingTableWithUniqueColumnOnSqliteStillWorks(): void
    {
        // The unique-via-alter restriction must not affect the normal
        // CREATE TABLE case, where SQLite embeds UNIQUE inline.
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $container = new Container();
        $container->bind('db', fn() => new class($pdo) {
            public function __construct(private PDO $pdo)
            {
            }
            public function getConnection()
            {
                return $this->pdo;
            }
        });
        Container::setInstance($container);

        $blueprint = new Blueprint('users', 'sqlite');
        $blueprint->id();
        $blueprint->string('code')->unique();

        $pdo->exec($blueprint->toSql());

        $pdo->exec("INSERT INTO users (code) VALUES ('a')");
        $this->expectException(\PDOException::class);
        $pdo->exec("INSERT INTO users (code) VALUES ('a')");
    }

    public function testAlteringExistingPostgresTableAddsColumnAtDatabaseLevel(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('The pdo_pgsql extension is required for this test.');
        }

        $host = getenv('DOPPAR_TEST_PGSQL_HOST') ?: '';
        $database = getenv('DOPPAR_TEST_PGSQL_DATABASE') ?: '';

        if ($host === '' || $database === '') {
            $this->markTestSkipped(
                'Configure DOPPAR_TEST_PGSQL_HOST/DOPPAR_TEST_PGSQL_DATABASE to run this test.'
            );
        }

        $port = getenv('DOPPAR_TEST_PGSQL_PORT') ?: '5432';
        $username = getenv('DOPPAR_TEST_PGSQL_USERNAME') ?: 'postgres';
        $password = getenv('DOPPAR_TEST_PGSQL_PASSWORD') ?: '';

        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database),
            $username,
            $password
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $table = 'doppar_alter_test_' . uniqid();
        $pdo->exec("DROP TABLE IF EXISTS {$table}");
        $pdo->exec("CREATE TABLE {$table} (id SERIAL PRIMARY KEY)");

        try {
            $container = new Container();
            $container->bind('db', fn() => new class($pdo) {
                public function __construct(private PDO $pdo)
                {
                }
                public function getConnection()
                {
                    return $this->pdo;
                }
            });
            Container::setInstance($container);

            $blueprint = new Blueprint($table, 'pgsql', false);
            $blueprint->string('nickname')->nullable();

            $pdo->exec($blueprint->toSql());

            $stmt = $pdo->query(
                "SELECT column_name FROM information_schema.columns WHERE table_name = " .
                $pdo->quote($table)
            );
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $this->assertContains('nickname', $columns);
        } finally {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
