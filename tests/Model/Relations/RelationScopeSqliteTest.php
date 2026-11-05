<?php

namespace Tests\Unit\Model\RelationScope;

require_once __DIR__ . '/RelationScopeBehavior.php';

final class RelationScopeSqliteTest extends RelationScopeTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
