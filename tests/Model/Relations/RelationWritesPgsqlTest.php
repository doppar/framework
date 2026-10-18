<?php

namespace Tests\Unit\Model\RelationWrites;

require_once __DIR__ . '/RelationWritesBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('pgsql')]
final class RelationWritesPgsqlTest extends RelationWritesTest
{
    protected static function driverName(): string
    {
        return 'pgsql';
    }
}
