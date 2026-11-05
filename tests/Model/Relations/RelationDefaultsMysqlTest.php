<?php

namespace Tests\Unit\Model\Relations;

require_once __DIR__ . '/RelationDefaultsBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('mysql')]
final class RelationDefaultsMysqlTest extends RelationDefaultsTest
{
    protected static function driverName(): string
    {
        return 'mysql';
    }
}
