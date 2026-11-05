<?php

namespace Tests\Unit\Model\TemporalBranch;

require_once __DIR__ . '/TemporalBranchBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('pgsql')]
final class TemporalBranchPgsqlTest extends TemporalBranchTest
{
    /**
     * Run the branch tests against PostgreSQL.
     *
     * @return string
     */
    protected static function driverName(): string
    {
        return 'pgsql';
    }
}
