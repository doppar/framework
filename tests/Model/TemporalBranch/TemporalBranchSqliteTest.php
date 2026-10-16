<?php

namespace Tests\Unit\Model\TemporalBranch;

require_once __DIR__ . '/TemporalBranchBehavior.php';

final class TemporalBranchSqliteTest extends TemporalBranchTest
{
    protected static function driverName(): string
    {
        return 'sqlite';
    }
}
