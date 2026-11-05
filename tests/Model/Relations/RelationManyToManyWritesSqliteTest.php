<?php

namespace Tests\Unit\Model\ManyToManyWrites;

require_once __DIR__ . '/RelationManyToManyWritesBehavior.php';

final class RelationManyToManyWritesSqliteTest extends RelationManyToManyWritesTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
