<?php

namespace Tests\Unit\Model\Query;

require_once __DIR__ . '/EntityRelationshipBehavior.php';

final class EntityRelationshipSqliteTest extends EntityRelationshipTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
