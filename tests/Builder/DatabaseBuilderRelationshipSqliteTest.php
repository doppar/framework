<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/DatabaseBuilderRelationshipBehavior.php';

final class DatabaseBuilderRelationshipSqliteTest extends DatabaseBuilderRelationshipTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
