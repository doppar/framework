<?php

namespace Tests\Unit\Model\RelationExists;

require_once __DIR__ . '/RelationshipExistenceCallbackBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('pgsql')]
final class RelationshipExistenceCallbackPgsqlTest extends RelationshipExistenceCallbackTest
{
    protected static function driverName(): string
    {
        return 'pgsql';
    }
}
