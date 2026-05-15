<?php

namespace Tests\Unit\Model\Query;

require_once __DIR__ . '/EntityModelQueryBehavior.php';

final class EntityModelQuerySqliteTest extends EntityModelQueryTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
