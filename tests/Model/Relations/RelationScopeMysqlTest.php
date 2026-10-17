<?php

namespace Tests\Unit\Model\RelationScope;

require_once __DIR__ . '/RelationScopeBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('mysql')]
final class RelationScopeMysqlTest extends RelationScopeTest
{
    protected static function driverName(): string
    {
        return 'mysql';
    }
}
