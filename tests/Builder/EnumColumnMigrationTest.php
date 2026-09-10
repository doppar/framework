<?php

namespace Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Migration\Blueprint;
use Phaseolies\DI\Container;
use PDO;

/**
 * Enum columns must reject values outside the declared list on every
 * supported driver:
 *  - MySQL:    native ENUM(...) type (already enforced before this test).
 *  - Postgres: TEXT + inline CHECK constraint.
 *  - SQLite:   TEXT + inline CHECK constraint.
 *
 * Postgres and SQLite have no native enum type reachable from a portable
 * migration column definition, so both use an inline CHECK clause
 * instead of leaving the column unconstrained.
 */
class EnumColumnMigrationTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(Container::class);
        $property = $reflection->getProperty('instance');
        $property->setValue(null, null);
    }

    /**
     * Bind a fake 'db' service reporting the given PDO driver name, so
     * Blueprint/ColumnDefinition pick the matching grammar.
     */
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

    public function testMySqlEnumProducesNativeEnumType(): void
    {
        $this->bindFakeDriver('mysql');

        $blueprint = new Blueprint('posts', 'mysql');
        $blueprint->enum('status', ['draft', 'published']);

        $sql = $blueprint->toSql();

        $this->assertStringContainsString("status ENUM('draft','published')", $sql);
    }

    public function testPostgresEnumAddsInlineCheckConstraint(): void
    {
        $this->bindFakeDriver('pgsql');

        $blueprint = new Blueprint('posts', 'pgsql');
        $blueprint->enum('status', ['draft', 'published']);

        $sql = $blueprint->toSql();

        $this->assertStringContainsString(
            "status TEXT CHECK (status IN ('draft', 'published'))",
            $sql
        );
    }

    public function testSqliteEnumAddsInlineCheckConstraint(): void
    {
        $this->bindFakeDriver('sqlite');

        $blueprint = new Blueprint('posts', 'sqlite');
        $blueprint->enum('status', ['draft', 'published']);

        $sql = $blueprint->toSql();

        $this->assertStringContainsString(
            "status TEXT CHECK (status IN ('draft', 'published'))",
            $sql
        );
    }

    public function testEnumWithoutValuesThrowsOnEveryDriver(): void
    {
        foreach (['mysql', 'pgsql', 'sqlite'] as $driver) {
            $this->bindFakeDriver($driver);

            $blueprint = new Blueprint('posts', $driver);
            $blueprint->addColumn('enum', 'status', ['values' => []]);

            try {
                $blueprint->toSql();
                $this->fail("Expected InvalidArgumentException for driver {$driver}");
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('Enum type requires an array of allowed values', $e->getMessage());
            }
        }
    }

    public function testEnumValuesContainingQuotesAreEscapedOnEveryDriver(): void
    {
        foreach (['mysql', 'pgsql', 'sqlite'] as $driver) {
            $this->bindFakeDriver($driver);

            $blueprint = new Blueprint('posts', $driver);
            $blueprint->enum('label', ["O'Brien", 'plain']);

            // Must not throw and must double the embedded quote rather
            // than leave it able to break out of the string literal.
            $sql = $blueprint->toSql();
            $this->assertStringContainsString("O''Brien", $sql, "Driver: {$driver}");
        }
    }

    public function testSqliteEnumConstraintIsEnforcedAtDatabaseLevel(): void
    {
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

        $blueprint = new Blueprint('posts', 'sqlite');
        $blueprint->id();
        $blueprint->enum('status', ['draft', 'published']);

        $pdo->exec($blueprint->toSql());

        // A declared value must be accepted.
        $pdo->exec("INSERT INTO posts (status) VALUES ('draft')");
        $this->assertSame('1', (string) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn());

        // A value outside the declared set must be rejected by the
        // database itself, not merely by application-level validation.
        $this->expectException(\PDOException::class);
        $pdo->exec("INSERT INTO posts (status) VALUES ('archived')");
    }

    public function testPostgresEnumConstraintIsEnforcedAtDatabaseLevel(): void
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

        $table = 'doppar_enum_check_test_' . uniqid();
        $pdo->exec("DROP TABLE IF EXISTS {$table}");

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

            $blueprint = new Blueprint($table, 'pgsql');
            $blueprint->id();
            $blueprint->enum('status', ['draft', 'published']);

            $pdo->exec($blueprint->toSql());

            $pdo->exec("INSERT INTO {$table} (status) VALUES ('draft')");
            $this->assertSame('1', (string) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn());

            $this->expectException(\PDOException::class);
            $pdo->exec("INSERT INTO {$table} (status) VALUES ('archived')");
        } finally {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
