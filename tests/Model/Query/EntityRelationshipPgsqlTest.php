<?php

namespace Tests\Unit\Model\Query;

require_once __DIR__ . '/EntityRelationshipBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('pgsql')]
final class EntityRelationshipPgsqlTest extends EntityRelationshipTest
{
    protected static function driverName(): string
    {
        return 'pgsql';
    }
}
