<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static  getPdoInstance(?string $connection = null): PDO
 * @method static  beginTransaction(): void
 * @method static  commit(): void
 * @method static  rollBack(): void
 * @method static  transaction(\Closure $callback, int $attempts = 1)
 * @method static  transactionLevel(): int
 * @method static  getTableColumns(?string $table = null): array
 * @method static  dropAllTables(): int
 * @method static  table(string $table): Builder
 * @method static  getTables(): array
 * @method static  tableExists(string $table): bool
 * @method static  getTable(Model $model): string
 * @method static  getConnection(): PDO
 * @method static  procedure(string $procedureName,array $params = [],array $outputParams = []): \Phaseolies\Support\Collection
 * @method static  view(string $viewName, array $where = [], array $params = []): array
 * @method static  query(string $sql, array $params = []): \Phaseolies\Support\Collection
 * @method static  execute(string $sql, array $params = []): int
 * @method static  statement(string $sql, array $params = []): \PDOStatement
 * @method static  connection(?string $name = null): self
 * @method static  bucket(string $table): \Phaseolies\Database\Entity\Query\Builder
 * @method static  disconnect(?string $connection = null): bool
 * @method static  reconnect(?string $connection = null): PDO
 * @method static  isConnected(?string $connection = null): bool
 * @method static  getFreshConnection(?string $connection = null): PDO
 * @method static  cleanupAllConnections(): void
 *
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
