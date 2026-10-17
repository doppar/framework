<?php

namespace Tests\Unit\Model\TemporalBranch;

require_once __DIR__ . '/TemporalBranchBehavior.php';

final class TemporalBranchSqliteTest extends TemporalBranchTest
{
    /**
     * Run the branch tests against SQLite.
     *
     * @return string
     */
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
