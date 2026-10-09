<?php

namespace Tests\Unit\Model\SoftDelete;

require_once __DIR__ . '/SoftDeleteBehavior.php';

final class SoftDeleteSqliteTest extends SoftDeleteTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
