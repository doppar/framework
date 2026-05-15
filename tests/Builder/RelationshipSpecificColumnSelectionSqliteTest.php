<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/RelationshipSpecificColumnSelectionBehavior.php';

final class RelationshipSpecificColumnSelectionSqliteTest extends RelationshipSpecificColumnSelectionTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
