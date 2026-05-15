<?php

namespace Tests\Unit\Model\Query;

require_once __DIR__ . '/EntityModelComplexQueryBehavior.php';

final class EntityModelComplexQuerySqliteTest extends EntityModelComplexQueryTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
