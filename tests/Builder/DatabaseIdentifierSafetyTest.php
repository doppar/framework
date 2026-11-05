<?php

namespace Tests\Unit\Builder;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Phaseolies\Config\Config;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Tests\Support\MockContainer;

/**
 * Database::view() and Database::getTableColumns() must treat every
 * identifier as untrusted. Behaviour is verified against a real SQLite
 * database; the generated SQL for every driver is verified with a recording
 * PDO.
 */
final class DatabaseIdentifierSafetyTest extends TestCase
{
    private const CONNECTION = 'identifier_safety_test';

    private PDO $pdo;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());

        $config = new \ReflectionClass(Config::class);
        foreach (['config' => [], 'cacheFile' => null, 'loadedFromCache' => false, 'fileHashes' => []] as $name => $value) {
            if ($config->hasProperty($name)) {
                $config->getProperty($name)->setValue(null, $value);
            }
        }
        Config::set('database.default', self::CONNECTION);

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('CREATE TABLE users (id INTEGER, name TEXT, "order" TEXT)');
        $this->pdo->exec("INSERT INTO users VALUES (1, 'alice', 'a'), (2, 'bob', 'b'), (3, 'carol', 'a')");
        $this->pdo->exec('CREATE VIEW user_view AS SELECT id, name, "order" FROM users');
        $this->pdo->exec('CREATE TABLE audit (id INTEGER)');

        $this->useConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->useConnection(null);
    }

    private function useConnection(?PDO $pdo): void
    {
        (new \ReflectionClass(Database::class))
            ->getProperty('connections')
            ->setValue(null, $pdo === null ? [] : [self::CONNECTION => $pdo]);
    }

    private function tableCount(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function maliciousNames(): array
    {
        return [
            'empty'             => [''],
            'space'             => ['user_view u'],
            'trailing newline'  => ["user_view\n"],
            'null byte'         => ["user_view\0"],
            'stacked statement' => ['user_view; DROP TABLE audit;--'],
            'union'             => ['user_view UNION SELECT * FROM users'],
            'quote'             => ["user_view'"],
            'double quote'      => ['user_view"'],
            'backtick'          => ['user_view`'],
            'backtick breakout' => ['user_view` WHERE 1=1; --'],
            'comment'           => ['user_view--'],
            'subselect'         => ['(SELECT 1)'],
            'function'          => ['randomblob(10)'],
            'too many dots'     => ['a.b.c'],
            'unicode quote'     => ["user_view\u{FF07}"],
        ];
    }

    // -- Database::view(): behaviour ----------------------------------------

    public function testViewReturnsAllRows(): void
    {
        $this->assertCount(3, Database::view('user_view'));
    }

    public function testViewReturnsAssociativeRows(): void
    {
        $this->assertSame(
            ['id' => 1, 'name' => 'alice', 'order' => 'a'],
            Database::view('user_view', ['id' => 1])[0]
        );
    }

    public function testViewFiltersBySingleColumn(): void
    {
        $rows = Database::view('user_view', ['name' => 'alice']);

        $this->assertCount(1, $rows);
        $this->assertSame('alice', $rows[0]['name']);
    }

    public function testViewCombinesMultipleConditionsWithAnd(): void
    {
        $this->assertCount(2, Database::view('user_view', ['order' => 'a']));
        $this->assertCount(1, Database::view('user_view', ['order' => 'a', 'name' => 'carol']));
        $this->assertSame([], Database::view('user_view', ['order' => 'b', 'name' => 'carol']));
    }

    public function testViewWorksWithReservedWordColumnBecauseItIsQuoted(): void
    {
        $this->assertCount(2, Database::view('user_view', ['order' => 'a']));
    }

    public function testViewAcceptsUnderscoreAndDigitNames(): void
    {
        $this->pdo->exec("CREATE VIEW _v2 AS SELECT 'x' AS _c1");

        $this->assertSame([['_c1' => 'x']], Database::view('_v2', ['_c1' => 'x']));
    }

    public function testViewAcceptsSchemaQualifiedViewName(): void
    {
        $this->assertCount(3, Database::view('main.user_view'));
    }

    public function testViewReturnsEmptyArrayWhenNothingMatches(): void
    {
        $this->assertSame([], Database::view('user_view', ['name' => 'nobody']));
    }

    public function testViewSupportsNullAndNumericValues(): void
    {
        $this->assertSame([], Database::view('user_view', ['name' => null]));
        $this->assertCount(1, Database::view('user_view', ['id' => 2]));
    }

    public function testViewPassesCallerSuppliedParamsThroughWithGeneratedOnes(): void
    {
        $recorder = $this->recordingPdo('mysql');
        $this->useConnection($recorder->pdo);

        Database::view('v', ['a' => 1], [':extra' => 'e']);

        $this->assertSame([[':extra' => 'e', ':a' => 1]], $recorder->params);
    }

    // -- Database::view(): values are data -----------------------------------

    #[DataProvider('injectionValues')]
    public function testViewTreatsValuesAsDataNotSql(string $value): void
    {
        $this->assertSame([], Database::view('user_view', ['name' => $value]));
        $this->assertSame(3, $this->tableCount('users'));
        $this->assertSame(0, $this->tableCount('audit'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function injectionValues(): array
    {
        return [
            'tautology'  => ["x' OR '1'='1"],
            'stacked'    => ["x'; DROP TABLE users;--"],
            'union'      => ["x' UNION SELECT 1,2,3--"],
            'comment'    => ["x'--"],
            'backslash'  => ["x\\' OR 1=1--"],
            'null byte'  => ["x\0' OR '1'='1"],
        ];
    }

    // -- Database::view(): identifiers are validated -------------------------

    #[DataProvider('maliciousNames')]
    public function testViewRejectsMaliciousViewName(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Database::view($name);
    }

    #[DataProvider('maliciousNames')]
    public function testViewRejectsMaliciousColumnName(string $column): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Database::view('user_view', [$column => 'x']);
    }

    public function testViewRejectsQualifiedColumnName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Database::view('user_view', ['user_view.name' => 'alice']);
    }

    public function testViewRejectsIntegerKeyedWhere(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Database::view('user_view', ['alice']);
    }

    public function testViewRejectsAnyBadColumnEvenAfterGoodOnes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Database::view('user_view', ['name' => 'alice', '1=1; DROP TABLE audit;--' => 1]);
    }

    public function testViewDoesNotExecuteAnythingWhenRejected(): void
    {
        foreach (self::maliciousNames() as [$name]) {
            try {
                Database::view('user_view', [$name => 'x']);
            } catch (\InvalidArgumentException) {
            }
            try {
                Database::view($name);
            } catch (\InvalidArgumentException) {
            }
        }

        $this->assertSame(3, $this->tableCount('users'));
        $this->assertSame(0, $this->tableCount('audit'));
        $this->assertCount(3, Database::view('user_view'));
    }

    public function testViewRejectsBeforeTouchingTheConnection(): void
    {
        $this->useConnection(null);

        // No connection is configured; a rejected name must fail on validation
        // (InvalidArgumentException), not on connecting (RuntimeException).
        $this->expectException(\InvalidArgumentException::class);
        Database::view('users; DROP TABLE users');
    }

    // -- Database::view(): SQL shape per driver ------------------------------

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function viewSqlPerDriver(): array
    {
        return [
            'sqlite' => ['sqlite', 'SELECT * FROM `v` WHERE `a` = :a AND `b` = :b', 'SELECT * FROM `v`'],
            'mysql'  => ['mysql', 'SELECT * FROM `v` WHERE `a` = :a AND `b` = :b', 'SELECT * FROM `v`'],
            'pgsql'  => ['pgsql', 'SELECT * FROM "v" WHERE "a" = :a AND "b" = :b', 'SELECT * FROM "v"'],
        ];
    }

    #[DataProvider('viewSqlPerDriver')]
    public function testViewQuotesIdentifiersPerDriver(string $driver, string $withWhere, string $withoutWhere): void
    {
        $recorder = $this->recordingPdo($driver);
        $this->useConnection($recorder->pdo);

        Database::view('v');
        Database::view('v', ['a' => 1, 'b' => 2]);

        $this->assertSame([$withoutWhere, $withWhere], $recorder->sql);
        $this->assertSame([[], [':a' => 1, ':b' => 2]], $recorder->params);
    }

    public function testViewQuotesEachPartOfAQualifiedNameSeparately(): void
    {
        $mysql = $this->recordingPdo('mysql');
        $this->useConnection($mysql->pdo);
        Database::view('app.v');
        $this->assertSame(['SELECT * FROM `app`.`v`'], $mysql->sql);

        $pgsql = $this->recordingPdo('pgsql');
        $this->useConnection($pgsql->pdo);
        Database::view('app.v');
        $this->assertSame(['SELECT * FROM "app"."v"'], $pgsql->sql);
    }

    public function testViewNeverPlacesValuesInTheSqlText(): void
    {
        $recorder = $this->recordingPdo('mysql');
        $this->useConnection($recorder->pdo);

        Database::view('v', ['a' => "x' OR '1'='1"]);

        $this->assertStringNotContainsString('OR', $recorder->sql[0]);
        $this->assertSame([':a' => "x' OR '1'='1"], $recorder->params[0]);
    }

    private function recordingPdo(string $driver): object
    {
        $recorder = new class {
            public array $sql = [];
            public array $params = [];
            public PDO $pdo;
        };

        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn($driver);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($recorder) {
            $recorder->sql[] = $sql;

            $stmt = $this->createStub(PDOStatement::class);
            $stmt->method('execute')->willReturnCallback(function (?array $params = null) use ($recorder) {
                $recorder->params[] = $params ?? [];

                return true;
            });
            $stmt->method('fetchAll')->willReturn([]);

            return $stmt;
        });
        $recorder->pdo = $pdo;

        return $recorder;
    }

    // -- Database::getTableColumns() -----------------------------------------

    public function testGetTableColumnsReturnsColumnNames(): void
    {
        $this->assertSame(['id', 'name', 'order'], (new Database(self::CONNECTION))->getTableColumns('users'));
    }

    public function testGetTableColumnsWorksForReservedWordTableNames(): void
    {
        $this->pdo->exec('CREATE TABLE "group" (id INTEGER, label TEXT)');

        $this->assertSame(['id', 'label'], (new Database(self::CONNECTION))->getTableColumns('group'));
    }

    #[DataProvider('maliciousNames')]
    public function testGetTableColumnsRejectsMaliciousTableName(string $table): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Database(self::CONNECTION))->getTableColumns($table);
    }

    public function testGetTableColumnsRejectionIsNotWrappedAsPdoException(): void
    {
        try {
            (new Database(self::CONNECTION))->getTableColumns('users; DROP TABLE audit');
            $this->fail('Expected InvalidArgumentException');
        } catch (\PDOException) {
            $this->fail('Validation failure must not surface as a database error');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, $this->tableCount('audit'));
        }
    }

    public function testGetTableColumnsStillRejectsNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Database(self::CONNECTION))->getTableColumns(null);
    }

    public function testGetTableColumnsReturnsEmptyForUnknownTable(): void
    {
        $this->assertSame([], (new Database(self::CONNECTION))->getTableColumns('does_not_exist'));
    }
}
