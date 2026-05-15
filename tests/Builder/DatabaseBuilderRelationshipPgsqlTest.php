<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/DatabaseBuilderRelationshipBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('pgsql')]
final class DatabaseBuilderRelationshipPgsqlTest extends DatabaseBuilderRelationshipTest
{
    protected static function driverName(): string
    {
        return 'pgsql';
    }
}
