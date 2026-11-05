<?php

namespace Tests\Unit\Model\SoftDelete;

require_once __DIR__ . '/SoftDeleteBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('mysql')]
final class SoftDeleteMysqlTest extends SoftDeleteTest
{
    protected static function driverName(): string
    {
        return 'mysql';
    }
}
