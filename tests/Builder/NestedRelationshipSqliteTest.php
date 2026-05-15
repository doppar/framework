<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/NestedRelationshipBehavior.php';

final class NestedRelationshipSqliteTest extends NestedRelationshipTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
