<?php

namespace Tests\Unit\Builder;

use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Entity\Query\Builder as QueryBuilder;

/**
 * Strings such as "date" or "time" pass is_callable() because they are PHP
 * function names. The builders must treat them as column names / values,
 * never invoke them as closures.
 */
#[AllowMockObjectsWithoutExpectations]
class StringArgumentNotCallableTest extends TestCase
{
    private function entityBuilder(): Builder
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->willReturn('mysql');

        return new Builder($pdo, 'users', StringArgumentModelStub::class, 15);
    }

    private function queryBuilder(): QueryBuilder
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->willReturn('mysql');

        return new QueryBuilder($pdo, 'users');
    }

    public static function functionNamedColumns(): array
    {
        return [['date'], ['time'], ['key'], ['max'], ['min'], ['file'], ['end'], ['range']];
    }

    #[DataProvider('functionNamedColumns')]
    public function testWhereOnColumnNamedLikePhpFunctionIsACondition(string $column): void
    {
        $sql = $this->entityBuilder()->where($column, 'x')->orWhere($column, '!=', 'y')->toSql();

        $this->assertStringContainsString("{$column} = ?", $sql);
        $this->assertStringContainsString("OR {$column} != ?", $sql);
    }

    #[DataProvider('functionNamedColumns')]
    public function testQueryBuilderWhereOnColumnNamedLikePhpFunctionIsACondition(string $column): void
    {
        $sql = $this->queryBuilder()->where($column, 'x')->orWhere($column, 'y')->toSql();

        $this->assertStringContainsString("{$column} = ?", $sql);
        $this->assertStringContainsString("OR {$column} = ?", $sql);
    }

    public function testWhereStillAcceptsClosuresForNesting(): void
    {
        $sql = $this->entityBuilder()
            ->where(fn($q) => $q->where('a', 1)->orWhere('b', 2))
            ->toSql();

        $this->assertStringContainsString('(a = ? OR b = ?)', $sql);
    }

    public function testWhereStillAcceptsInvokableObjectsForNesting(): void
    {
        $nested = new class {
            public function __invoke($q): void
            {
                $q->where('a', 1);
            }
        };

        $this->assertStringContainsString('(a = ?)', $this->queryBuilder()->where($nested)->toSql());
    }

    public function testIfDoesNotInvokeStringValuesAsFunctions(): void
    {
        $called = 0;
        $callback = function () use (&$called) {
            $called++;
        };

        // "phpinfo" would print the server configuration if invoked.
        ob_start();
        $this->entityBuilder()->if('phpinfo', $callback);
        $this->queryBuilder()->if('phpinfo', $callback);
        $output = ob_get_clean();

        $this->assertSame('', $output);
        $this->assertSame(2, $called);
    }

    public function testIfStillEvaluatesClosureConditions(): void
    {
        $called = false;

        $this->entityBuilder()->if(fn() => false, function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
    }
}

class StringArgumentModelStub
{
    public function usesTimestamps(): bool
    {
        return false;
    }
}
