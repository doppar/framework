<?php

namespace Tests\Unit\Model\ManyToManyWrites;

require_once __DIR__ . '/RelationManyToManyWritesBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('mysql')]
final class RelationManyToManyWritesMysqlTest extends RelationManyToManyWritesTest
{
    protected static function driverName(): string
    {
        return 'mysql';
    }
}
