<?php

namespace Tests\Unit\Model\RelationWrites;

require_once __DIR__ . '/RelationWritesBehavior.php';

final class RelationWritesSqliteTest extends RelationWritesTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
