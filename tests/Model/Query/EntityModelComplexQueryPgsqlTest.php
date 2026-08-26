<?php

namespace Tests\Unit\Model\Query;

require_once __DIR__ . '/EntityModelComplexQueryBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('pgsql')]
final class EntityModelComplexQueryPgsqlTest extends EntityModelComplexQueryTest
{
    protected static function driverName(): string
    {
        return 'pgsql';
    }
}
