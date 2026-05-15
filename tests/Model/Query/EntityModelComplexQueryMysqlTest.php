<?php

namespace Tests\Unit\Model\Query;

require_once __DIR__ . '/EntityModelComplexQueryBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('mysql')]
final class EntityModelComplexQueryMysqlTest extends EntityModelComplexQueryTest
{
    protected static function driverName(): string
    {
        return 'mysql';
    }
}
