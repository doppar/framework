<?php

namespace Tests\Unit\Builder;

use Phaseolies\Config\Config;
use Phaseolies\Database\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Database\ModelQueryDriverTestCase;

/**
 * Identifier safety of Database::view() and Database::getTableColumns()
 * against a real server. Shared by the SQLite, MySQL and PostgreSQL suites.
 *
 * Every object this creates is prefixed `dpr_ident_`, so pointing the opt-in
 * driver suites at a database that holds other data never touches it.
 */
abstract class DatabaseIdentifierDriverTestCase extends ModelQueryDriverTestCase
{
    protected const USERS = 'dpr_ident_users';
    protected const AUDIT = 'dpr_ident_audit';
    protected const RESERVED = 'dpr_ident_group';
    protected const VIEW = 'dpr_ident_user_view';

    protected function tableDefinitions(): array
    {
        return [
            self::USERS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'order', 'type' => 'string', 'nullable' => true],
            ],
            self::AUDIT => [
                ['name' => 'id', 'type' => 'integer'],
            ],
            self::RESERVED => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'label', 'type' => 'string', 'nullable' => true],
            ],
        ];
    }

    protected function seedData(): array
    {
        return [
            self::USERS => [
                ['id' => 1, 'name' => 'alice', 'order' => 'a'],
                ['id' => 2, 'name' => 'bob', 'order' => 'b'],
                ['id' => 3, 'name' => 'carol', 'order' => 'a'],
            ],
        ];
    }

    protected function bootContainer(): void
    {
        parent::bootContainer();

        $config = new \ReflectionClass(Config::class);
        foreach (['config' => [], 'cacheFile' => null, 'loadedFromCache' => false, 'fileHashes' => []] as $name => $value) {
            if ($config->hasProperty($name)) {
                $config->getProperty($name)->setValue(null, $value);
            }
        }

        // Database::view() resolves its connection from this
        Config::set('database.default', 'default');
    }

    /**
     * Views depend on their tables; PostgreSQL refuses to drop a table that
     * still has a view on it.
     */
    protected function dropTablesIfExist(array $tables): void
    {
        $this->dropView();
        parent::dropTablesIfExist($tables);
    }

    protected function setupDatabaseConnections(): void
    {
        parent::setupDatabaseConnections();

        $this->dropView();
        $this->pdo->exec(sprintf(
            'CREATE VIEW %s AS SELECT %s, %s, %s FROM %s',
            $this->quoteIdentifier(self::VIEW),
            $this->quoteIdentifier('id'),
            $this->quoteIdentifier('name'),
            $this->quoteIdentifier('order'),
            $this->quoteIdentifier(self::USERS)
        ));
    }

    protected function tearDownDatabaseConnections(): void
    {
        if (isset($this->pdo)) {
            $this->dropView();
        }

        parent::tearDownDatabaseConnections();
    }

    private function dropView(): void
    {
        $this->pdo->exec('DROP VIEW IF EXISTS ' . $this->quoteIdentifier(self::VIEW));
    }

    private function rowCount(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $this->quoteIdentifier($table))->fetchColumn();
    }

    /**
     * The schema/database that qualifies objects on this server
     */
    private function currentSchema(): string
    {
        return (string) $this->pdo->query(match (static::driverName()) {
            'sqlite' => "SELECT 'main'",
            'mysql' => 'SELECT DATABASE()',
            'pgsql' => 'SELECT current_schema()',
        })->fetchColumn();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function maliciousNames(): array
    {
        return [
            'empty'             => [''],
            'space'             => [self::VIEW . ' u'],
            'trailing newline'  => [self::VIEW . "\n"],
            'null byte'         => [self::VIEW . "\0"],
            'stacked statement' => [self::VIEW . '; DROP TABLE ' . self::AUDIT . ';--'],
            'union'             => [self::VIEW . ' UNION SELECT * FROM ' . self::USERS],
            'single quote'      => [self::VIEW . "'"],
            'double quote'      => [self::VIEW . '"'],
            'backtick'          => [self::VIEW . '`'],
            'backtick breakout' => [self::VIEW . '` WHERE 1=1; --'],
            'double quote breakout' => [self::VIEW . '" WHERE 1=1; --'],
            'comment'           => [self::VIEW . '--'],
            'subselect'         => ['(SELECT 1)'],
            'too many dots'     => ['a.b.c'],
            'unicode quote'     => [self::VIEW . "\u{FF07}"],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function injectionValues(): array
    {
        return [
            'tautology' => ["x' OR '1'='1"],
            'stacked'   => ["x'; DROP TABLE " . self::AUDIT . ';--'],
            'union'     => ["x' UNION SELECT 1,2,3--"],
            'comment'   => ["x'--"],
            'backslash' => ["x\\' OR 1=1--"],
            'double'    => ['x" OR "1"="1'],
        ];
    }

    // -- Database::view() ----------------------------------------------------

    public function testViewReturnsAllRows(): void
    {
        $this->assertCount(3, Database::view(self::VIEW));
    }

    public function testViewReturnsAssociativeRows(): void
    {
        $this->assertEquals(
            ['id' => 1, 'name' => 'alice', 'order' => 'a'],
            Database::view(self::VIEW, ['id' => 1])[0]
        );
    }

    public function testViewFiltersAndCombinesConditionsWithAnd(): void
    {
        $this->assertCount(1, Database::view(self::VIEW, ['name' => 'alice']));
        $this->assertCount(2, Database::view(self::VIEW, ['order' => 'a']));
        $this->assertCount(1, Database::view(self::VIEW, ['order' => 'a', 'name' => 'carol']));
        $this->assertSame([], Database::view(self::VIEW, ['order' => 'b', 'name' => 'carol']));
    }

    public function testViewQuotesReservedWordColumn(): void
    {
        // `order` is a reserved word on every supported server; this only
        // works because the column identifier is quoted.
        $this->assertCount(2, Database::view(self::VIEW, ['order' => 'a']));
    }

    public function testViewAcceptsSchemaQualifiedName(): void
    {
        $this->assertCount(3, Database::view($this->currentSchema() . '.' . self::VIEW));
    }

    public function testViewReturnsEmptyArrayWhenNothingMatches(): void
    {
        $this->assertSame([], Database::view(self::VIEW, ['name' => 'nobody']));
    }

    #[DataProvider('injectionValues')]
    public function testViewTreatsValuesAsDataNotSql(string $value): void
    {
        $this->assertSame([], Database::view(self::VIEW, ['name' => $value]));
        $this->assertSame(3, $this->rowCount(self::USERS));
        $this->assertSame(0, $this->rowCount(self::AUDIT));
    }

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
        Database::view(self::VIEW, [$column => 'x']);
    }

    public function testViewRejectsQualifiedColumnAndIntegerKey(): void
    {
        foreach ([[self::VIEW . '.name' => 'alice'], ['alice']] as $where) {
            try {
                Database::view(self::VIEW, $where);
                $this->fail('Expected InvalidArgumentException for ' . json_encode($where));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRejectedNamesLeaveTheDatabaseUntouched(): void
    {
        foreach (self::maliciousNames() as [$name]) {
            foreach ([fn() => Database::view($name), fn() => Database::view(self::VIEW, [$name => 'x'])] as $call) {
                try {
                    $call();
                } catch (\InvalidArgumentException) {
                }
            }
        }

        $this->assertSame(3, $this->rowCount(self::USERS));
        $this->assertSame(0, $this->rowCount(self::AUDIT));
        $this->assertCount(3, Database::view(self::VIEW));
    }

    // -- Database::getTableColumns() -----------------------------------------

    public function testGetTableColumnsReturnsColumnNames(): void
    {
        $columns = (new Database('default'))->getTableColumns(self::USERS);

        $this->assertEqualsCanonicalizing(['id', 'name', 'order'], $columns);
    }

    public function testGetTableColumnsReturnsEmptyOrFailsCleanlyForUnknownTable(): void
    {
        try {
            $this->assertSame([], (new Database('default'))->getTableColumns('dpr_ident_missing'));
        } catch (\PDOException $e) {
            // MySQL's DESCRIBE errors on a missing table; that is a database
            // error, never an identifier-validation one.
            $this->assertNotInstanceOf(\InvalidArgumentException::class, $e);
        }
    }

    #[DataProvider('maliciousNames')]
    public function testGetTableColumnsRejectsMaliciousTableName(string $table): void
    {
        try {
            (new Database('default'))->getTableColumns($table);
            $this->fail('Expected InvalidArgumentException for ' . json_encode($table));
        } catch (\PDOException) {
            $this->fail('Validation failure must not reach the database');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, $this->rowCount(self::AUDIT));
        }
    }

    public function testGetTableColumnsRejectsNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Database('default'))->getTableColumns(null);
    }

    public function testGetTableColumnsHandlesReservedWordTableName(): void
    {
        // The table is dpr_ident_group, so this stays on a prefixed object
        // while still exercising quoting of a multi-part identifier.
        $this->assertEqualsCanonicalizing(['id', 'label'], (new Database('default'))->getTableColumns(self::RESERVED));
    }
}
