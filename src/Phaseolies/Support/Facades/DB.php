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
