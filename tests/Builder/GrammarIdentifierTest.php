<?php

namespace Tests\Unit\Builder;

use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Phaseolies\Database\Entity\Query\Grammar;

/**
 * Identifier validation and table-introspection SQL generation in the
 * Grammar trait. These tests need no database: the PDO is a stub that only
 * reports which driver it is.
 */
final class GrammarIdentifierTest extends TestCase
{
    private function grammarFor(string $driver): object
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn($driver);

        return new class($pdo) {
            use Grammar;

            public function __construct(public PDO $pdo)
            {
            }

            public function assertIdentifier(string $identifier): void
            {
                $this->assertValidIdentifier($identifier);
            }

            public static function valid(string $identifier, bool $qualified = true): bool
            {
                return static::isValidIdentifier($identifier, $qualified);
            }
        };
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validIdentifiers(): array
    {
        return [
            'plain'              => ['users'],
            'leading underscore' => ['_users'],
            'digits'             => ['users2024'],
            'mixed case'         => ['UserAccounts'],
            'single char'        => ['a'],
            'underscore only'    => ['_'],
            'qualified'          => ['main.users'],
            'qualified mixed'    => ['App_1.user_view'],
        ];
    }

    /**
     * Payloads that must never reach SQL as an identifier
     *
     * @return array<string, array{string}>
     */
    public static function maliciousIdentifiers(): array
    {
        return [
            'empty'                  => [''],
            'whitespace'             => [' '],
            'leading digit'          => ['1users'],
            'space'                  => ['users u'],
            'leading space'          => [' users'],
            'trailing space'         => ['users '],
            'trailing newline'       => ["users\n"],
            'trailing CRLF'          => ["users\r\n"],
            'embedded newline'       => ["users\nDROP TABLE users"],
            'tab'                    => ["users\t"],
            'null byte'              => ["users\0"],
            'null byte mid'          => ["us\0ers"],
            'semicolon'              => ['users;'],
            'stacked statement'      => ['users; DROP TABLE users;--'],
            'line comment'           => ['users--'],
            'block comment'          => ['users/**/'],
            'hash comment'           => ['users#'],
            'single quote'           => ["users'"],
            'double quote'           => ['users"'],
            'backtick'               => ['users`'],
            'backtick breakout'      => ['users` WHERE 1=1; --'],
            'quote tautology'        => ["x' OR '1'='1"],
            'union'                  => ['users UNION SELECT 1'],
            'subselect'              => ['(SELECT 1)'],
            'parentheses'            => ['users()'],
            'function call'          => ['SLEEP(5)'],
            'operator'               => ['id=1'],
            'wildcard'               => ['*'],
            'comma list'             => ['a,b'],
            'brackets'               => ['[users]'],
            'backslash'              => ['users\\'],
            'too many dots'          => ['a.b.c'],
            'leading dot'            => ['.users'],
            'trailing dot'           => ['users.'],
            'double dot'             => ['a..b'],
            'dot then digit'         => ['a.1b'],
            'hyphen'                 => ['user-name'],
            'unicode letter'         => ['usérs'],
            'unicode fullwidth quote' => ["users\u{FF07}"],
            'unicode fullwidth semi' => ["users\u{FF1B}"],
            'right-to-left override' => ["users\u{202E}"],
            'zero width space'       => ["users\u{200B}"],
            'emoji'                  => ["users\u{1F600}"],
            'invalid utf8'           => ["users\xC3\x28"],
        ];
    }

    // -- isValidIdentifier / assertValidIdentifier ---------------------------

    #[DataProvider('validIdentifiers')]
    public function testAcceptsValidIdentifiers(string $identifier): void
    {
        $grammar = $this->grammarFor('sqlite');

        $this->assertTrue($grammar::valid($identifier));
        $grammar->assertIdentifier($identifier);
    }

    #[DataProvider('maliciousIdentifiers')]
    public function testRejectsMaliciousIdentifiers(string $identifier): void
    {
        $grammar = $this->grammarFor('sqlite');

        $this->assertFalse($grammar::valid($identifier));
        $this->expectException(\InvalidArgumentException::class);
        $grammar->assertIdentifier($identifier);
    }

    public function testUnqualifiedModeRejectsDottedNames(): void
    {
        $grammar = $this->grammarFor('sqlite');

        $this->assertTrue($grammar::valid('users', false));
        $this->assertFalse($grammar::valid('main.users', false));
    }

    #[DataProvider('maliciousIdentifiers')]
    public function testUnqualifiedModeRejectsMaliciousIdentifiers(string $identifier): void
    {
        $this->assertFalse($this->grammarFor('sqlite')::valid($identifier, false));
    }

    public function testRejectsVeryLongIdentifierWithoutBacktrackingBlowup(): void
    {
        $grammar = $this->grammarFor('sqlite');
        $start = microtime(true);

        $this->assertFalse($grammar::valid(str_repeat('a', 100000) . '!'));
        $this->assertLessThan(1.0, microtime(true) - $start);
    }

    // -- getTableColumnsSql --------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function expectedSql(): array
    {
        return [
            'sqlite'  => ['sqlite', 'PRAGMA table_info(`users`)'],
            'mysql'   => ['mysql', 'DESCRIBE `users`'],
            'mariadb' => ['mariadb', 'DESCRIBE `users`'],
            'pgsql'   => ['pgsql', "SELECT column_name FROM information_schema.columns WHERE table_name = 'users'"],
        ];
    }

    #[DataProvider('expectedSql')]
    public function testGetTableColumnsSqlPerDriver(string $driver, string $expected): void
    {
        $this->assertSame($expected, $this->grammarFor($driver)->getTableColumnsSql('users'));
    }

    #[DataProvider('maliciousIdentifiers')]
    public function testGetTableColumnsSqlRejectsMaliciousTableNameOnEveryDriver(string $table): void
    {
        foreach (['sqlite', 'mysql', 'mariadb', 'pgsql'] as $driver) {
            try {
                $this->grammarFor($driver)->getTableColumnsSql($table);
                $this->fail("[{$driver}] accepted malicious table name " . json_encode($table));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testQuoteIdentifierUsesDriverSpecificQuoteCharacter(): void
    {
        $this->assertSame('`users`', $this->grammarFor('mysql')->quoteIdentifier('users'));
        $this->assertSame('`users`', $this->grammarFor('sqlite')->quoteIdentifier('users'));
        $this->assertSame('"users"', $this->grammarFor('pgsql')->quoteIdentifier('users'));
        $this->assertSame('"main"."users"', $this->grammarFor('pgsql')->quoteIdentifier('main.users'));
    }
}
