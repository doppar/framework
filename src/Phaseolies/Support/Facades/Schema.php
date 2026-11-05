<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static void create(string $table, callable $callback)
 * @method static void table(string $table, callable $callback)
 * @method static void drop(string $table)
 * @method static void dropIfExists(string $table)
 * @method static void rename(string $from, string $to)
 * @method static void dropColumns(string $table, string|array $columns)
 * @method static void renameColumn(string $table, string $from, string $to)
 * @method static bool hasColumn(string $table, string $column)
 * @method static bool hasColumns(string $table, array $columns)
 * @method static array getColumnListing(string $table)
 * @method static bool hasIndex(string $table, string|array $index)
 * @method static bool hasTable(string $table)
 * @method static void disableForeignKeyConstraints()
 * @method static void enableForeignKeyConstraints()
 *
 * @see \Phaseolies\Database\Migration\Schema
 */
class Schema extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'schema';
    }
}
