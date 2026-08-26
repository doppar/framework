<?php

namespace Tests\Unit\Model\Query;

require_once __DIR__ . '/EntityModelQueryBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('pgsql')]
final class EntityModelQueryPgsqlTest extends EntityModelQueryTest
{
    protected static function driverName(): string
    {
        return 'pgsql';
    }
}
