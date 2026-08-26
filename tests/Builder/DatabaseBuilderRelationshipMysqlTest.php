<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/DatabaseBuilderRelationshipBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('mysql')]
final class DatabaseBuilderRelationshipMysqlTest extends DatabaseBuilderRelationshipTest
{
    protected static function driverName(): string
    {
        return 'mysql';
    }
}
