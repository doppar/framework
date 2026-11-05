<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/DatabaseIdentifierSafetyBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('pgsql')]
final class DatabaseIdentifierSafetyPgsqlTest extends DatabaseIdentifierDriverTestCase
{
    protected static function driverName(): string
    {
        return 'pgsql';
    }
}
