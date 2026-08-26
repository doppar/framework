<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/RelationshipSpecificColumnSelectionBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('mysql')]
final class RelationshipSpecificColumnSelectionMysqlTest extends RelationshipSpecificColumnSelectionTest
{
    protected static function driverName(): string
    {
        return 'mysql';
    }
}
