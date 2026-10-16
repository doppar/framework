<?php

namespace Tests\Unit\Model\TemporalBranch;

require_once __DIR__ . '/TemporalBranchBehavior.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('database-external')]
#[Group('mysql')]
final class TemporalBranchMysqlTest extends TemporalBranchTest
{
    protected static function driverName(): string
    {
        return 'mysql';
    }
}
