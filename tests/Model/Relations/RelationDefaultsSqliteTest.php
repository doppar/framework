<?php

namespace Tests\Unit\Model\Relations;

require_once __DIR__ . '/RelationDefaultsBehavior.php';

final class RelationDefaultsSqliteTest extends RelationDefaultsTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
