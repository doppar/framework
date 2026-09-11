<?php

namespace Tests\Unit\Console;

use Phaseolies\Console\Schedule\SchedulePool;
use PHPUnit\Framework\TestCase;

class SchedulePoolTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['SCHEDULE_POOL_TEST_BOOL_FALSE'], $_ENV['SCHEDULE_POOL_TEST_BOOL_TRUE'], $_ENV['SCHEDULE_POOL_TEST_INT']);
    }

    public function testRealFalseIsStringifiedInsteadOfBeingDroppedBySymfonyProcess(): void
    {
        // Symfony's Process treats a literal `false` env value as "unset
        // this var for the child" instead of passing it through. A real
        // `false` from env.toml must survive as the string "false", not
        // silently vanish from the subprocess's environment.
        $_ENV['SCHEDULE_POOL_TEST_BOOL_FALSE'] = false;

        $env = SchedulePool::buildEnv();

        $this->assertSame('false', $env['SCHEDULE_POOL_TEST_BOOL_FALSE']);
    }

    public function testRealTrueIsStringifiedConsistently(): void
    {
        $_ENV['SCHEDULE_POOL_TEST_BOOL_TRUE'] = true;

        $env = SchedulePool::buildEnv();

        $this->assertSame('true', $env['SCHEDULE_POOL_TEST_BOOL_TRUE']);
    }

    public function testIntValueIsStringified(): void
    {
        $_ENV['SCHEDULE_POOL_TEST_INT'] = 3306;

        $env = SchedulePool::buildEnv();

        $this->assertSame('3306', $env['SCHEDULE_POOL_TEST_INT']);
    }

    public function testOverridesAreIncludedAndStringified(): void
    {
        $env = SchedulePool::buildEnv([
            'APP_RUNNING_IN_CONSOLE' => true,
            'APP_SCHEDULE_RUNNING' => true,
        ]);

        $this->assertSame('true', $env['APP_RUNNING_IN_CONSOLE']);
        $this->assertSame('true', $env['APP_SCHEDULE_RUNNING']);
    }
}
