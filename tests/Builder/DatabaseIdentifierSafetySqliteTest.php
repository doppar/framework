<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/DatabaseIdentifierSafetyBehavior.php';

final class DatabaseIdentifierSafetySqliteTest extends DatabaseIdentifierDriverTestCase
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
