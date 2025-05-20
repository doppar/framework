<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Database\Database getPdoInstance(): PDO
 * @method static \Phaseolies\Database\Database beginTransaction(): void
 * @method static \Phaseolies\Database\Database commit(): void
 * @method static \Phaseolies\Database\Database rollBack(): void
 * @method static \Phaseolies\Database\Database transaction(\Closure $callback, int $attempts = 1)
 * @method static \Phaseolies\Database\Database transactionLevel(): int
 * @method static \Phaseolies\Database\Database getTableColumns(?string $table = null): array
 * @method static \Phaseolies\Database\Database dropAllTables(): void
 * @method static \Phaseolies\Database\Database table(string $table): Builder
 * @method static \Phaseolies\Database\Database getTables(): array
 * @method static \Phaseolies\Database\Database tableExists(string $table): bool
 * @method static \Phaseolies\Database\Database getTable(Model $model): string
 * @method static \Phaseolies\Database\Database getConnection(): PDO
 * @method static \Phaseolies\Database\Database procedure(string $procedureName,array $params = [],array $outputParams = []): ProcedureResult
 * @method static \Phaseolies\Database\Database view(string $viewName, array $where = [], array $params = []): array
 * @method static \Phaseolies\Database\Database query(string $sql, array $params = []): \PDOStatement
 * @method static \Phaseolies\Database\Database execute(string $sql, array $params = []): int
 * @method static \Phaseolies\Database\Database dropAllTables(): void
 * @see \Phaseolies\Database\Database
 */

use Phaseolies\Facade\BaseFacade;

class DB extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'db';
    }
}
