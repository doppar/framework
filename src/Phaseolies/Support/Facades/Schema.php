<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Database\Migration\Schema create(string $table, callable $callback): void
 * @method static \Phaseolies\Database\Migration\Schema table(string $table, callable $callback): void
 * @method static \Phaseolies\Database\Migration\Schema dropIfExists(string $table): void
 * @method static \Phaseolies\Database\Migration\Schema hasTable(string $table): bool
 * @method static \Phaseolies\Database\Migration\Schema disableForeignKeyConstraints(): void
 * @method static \Phaseolies\Database\Migration\Schema enableForeignKeyConstraints(): void
 *
 * @see \Phaseolies\Database\Migration\Schema
 */

use Phaseolies\Facade\BaseFacade;

class Schema extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'schema';
    }
}
