<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static void create(string $table, callable $callback)
 * @method static void table(string $table, callable $callback)
 * @method static void dropIfExists(string $table)
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
