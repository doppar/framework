<?php

namespace Tests\Unit\Model\RelationExists;

require_once __DIR__ . '/RelationshipExistenceCallbackBehavior.php';

final class RelationshipExistenceCallbackSqliteTest extends RelationshipExistenceCallbackTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
