<?php

namespace Tests\Unit\Builder;

use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Entity\Query\Builder as QueryBuilder;

/**
 * Values that commonly come from request input (operators, JSON paths,
 * pattern types, join parts, aggregate columns) must never be interpolated
 * into SQL unchecked.
 */
#[AllowMockObjectsWithoutExpectations]
class QueryInjectionGuardTest extends TestCase
{
    private $pdoMock;

    protected function setUp(): void
    {
        $this->useDriver('mysql');
    }

    protected function tearDown(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function useDriver(string $driver): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->pdoMock->method('getAttribute')->willReturn($driver);
        $this->setStaticProperty(Database::class, 'connections', ['default' => $this->pdoMock]);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function setStaticProperty(string $className, string $propertyName, $value): void
    {
        (new \ReflectionClass($className))->getProperty($propertyName)->setValue(null, $value);
    }

    private function builder(): Builder
    {
        return new Builder($this->pdoMock, 'users', 'App\\Models\\User', 15);
    }

    public static function injectedOperators(): array
    {
        return [
            'tautology'     => ['= 1 OR 1=1 --'],
            'union'         => ['= 0 UNION SELECT password FROM users --'],
            'comment'       => ['=/**/'],
            'semicolon'     => ['=; DROP TABLE users'],
            'empty'         => [''],
        ];
    }

    #[DataProvider('injectedOperators')]
    public function testWhereRejectsInjectedOperator(string $operator): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->where('id', $operator, 5)->toSql();
    }

    #[DataProvider('injectedOperators')]
    public function testQueryBuilderWhereRejectsInjectedOperator(string $operator): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new QueryBuilder($this->pdoMock, 'users'))->where('id', $operator, 5)->toSql();
    }

    public function testDateHelperRejectsInjectedOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->whereYear('created_at', '= 2024 OR 1=1 --', '2024')->toSql();
    }

    public function testNonStringOperatorIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->where('id', ['='], 5)->toSql();
    }

    public function testValidOperatorsAreNormalised(): void
    {
        $sql = $this->builder()
            ->where('name', 'not   like', '%a%')
            ->where('age', '>=', 18)
            ->where('status', '<>', 'banned')
            ->toSql();

        $this->assertStringContainsString('name NOT LIKE ?', $sql);
        $this->assertStringContainsString('age >= ?', $sql);
        $this->assertStringContainsString('status <> ?', $sql);
    }

    public function testMySqlJsonPathIsBoundNotInterpolated(): void
    {
        $path = "$.a') OR 1=1 -- ";

        [$expr, $bindings] = $this->builder()->jsonContains('meta', $path, 'x');

        $this->assertSame('JSON_CONTAINS(`meta`, CAST(? AS JSON), ?)', $expr);
        $this->assertSame(['"x"', $path], $bindings);
    }

    public function testPostgresJsonPathKeysAreBound(): void
    {
        $this->useDriver('pgsql');

        [$expr, $bindings] = $this->builder()->jsonContains('meta', '$.a.b.c', 3);

        $this->assertSame('(meta::jsonb -> CAST(? AS text) -> CAST(? AS text) -> CAST(? AS text))::text = ?', $expr);
        $this->assertSame(['a', 'b', 'c', '3'], $bindings);
    }

    public function testPostgresRejectsMalformedJsonPath(): void
    {
        $this->useDriver('pgsql');
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->jsonContains('meta', "$.a' OR '1'='1", 'x');
    }

    public function testJsonRejectsInvalidColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->whereJson('meta) OR 1=1 --', '$.a', 'x');
    }

    public function testWherePatternRejectsUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->wherePattern('name', 'a%', 'LIKE ? OR 1=1 --');
    }

    public function testWherePatternAcceptsKnownType(): void
    {
        $sql = $this->builder()->wherePattern('name', 'a%', 'not like')->toSql();

        $this->assertStringContainsString('name NOT LIKE ?', $sql);
    }

    public function testILikeRejectsInvalidColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->iLike('name) OR 1=1 --', 'a%');
    }

    public static function invalidJoins(): array
    {
        return [
            'table'    => ['posts; DROP TABLE users', 'users.id', '=', 'posts.user_id', 'inner'],
            'first'    => ['posts', 'users.id OR 1=1', '=', 'posts.user_id', 'inner'],
            'operator' => ['posts', 'users.id', '= 1 OR 1', 'posts.user_id', 'inner'],
            'second'   => ['posts', 'users.id', '=', 'posts.user_id --', 'inner'],
            'type'     => ['posts', 'users.id', '=', 'posts.user_id', 'inner join x on 1=1 left'],
        ];
    }

    #[DataProvider('invalidJoins')]
    public function testJoinRejectsInvalidParts(string $table, string $first, string $operator, string $second, string $type): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->join($table, $first, $operator, $second, $type);
    }

    #[DataProvider('invalidJoins')]
    public function testQueryBuilderJoinRejectsInvalidParts(string $table, string $first, string $operator, string $second, string $type): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new QueryBuilder($this->pdoMock, 'users'))->join($table, $first, $operator, $second, $type);
    }

    public function testJoinAcceptsAliasedTableAndOuterType(): void
    {
        $sql = $this->builder()
            ->join('posts as p', 'users.id', '=', 'p.user_id', 'left outer')
            ->toSql();

        $this->assertStringContainsString('LEFT OUTER JOIN posts as p ON users.id = p.user_id', $sql);
    }

    public static function aggregateCalls(): array
    {
        return [
            'sum'      => ['sum'],
            'avg'      => ['avg'],
            'min'      => ['min'],
            'max'      => ['max'],
            'variance' => ['variance'],
            'stdDev'   => ['stdDev'],
        ];
    }

    #[DataProvider('aggregateCalls')]
    public function testAggregatesRejectInvalidColumn(string $method): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->{$method}('id) FROM users; --');
    }

    public function testGroupConcatRejectsSeparatorThatBreaksOutOfLiteral(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->groupConcat('name', "') FROM users; --");
    }

    public function testGroupConcatRejectsBackslashSeparator(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->groupConcat('name', '\\');
    }

    public function testWindowHelpersRejectInvalidAlias(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->movingAverage('views', 3, 'created_at', 'x FROM users --');
    }

    public function testWindowHelpersRejectInvalidOrderColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder()->movingDifference('views', 'created_at) --');
    }
}
