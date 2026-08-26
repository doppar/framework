<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/NestedRelationshipBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('pgsql')]
final class NestedRelationshipPgsqlTest extends NestedRelationshipTest
{
    protected static function driverName(): string
    {
        return 'pgsql';
    }
}
