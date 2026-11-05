<?php

namespace Tests\Unit\Console;

use Phaseolies\Console\Schedule\SchedulePool;
use PHPUnit\Framework\TestCase;
use Tests\Console\Support\ScratchApp;

/**
 * SchedulePool helpers that touch real processes and the real base path.
 */
class SchedulePoolProcessTest extends TestCase
{
    private ScratchApp $app;

    protected function setUp(): void
    {
        $this->app = new ScratchApp();
    }

    protected function tearDown(): void
    {
        $this->app->destroy();
    }

    public function testProcessArgumentsUseTheRunningPhpAndTheAbsolutePoolScript(): void
    {
        $arguments = SchedulePool::buildProcessArguments('queue:run --queue="high priority"');

        $this->assertSame([
            SchedulePool::phpBinary(),
            $this->app->root . DIRECTORY_SEPARATOR . 'pool',
            'queue:run',
            '--queue=high priority',
        ], $arguments);
    }

    public function testTheCurrentProcessIsRunning(): void
    {
        $this->assertTrue(SchedulePool::isProcessRunning(getmypid()));
    }

    public function testNonPositiveAndUnusedPidsAreNotRunning(): void
    {
        $this->assertFalse(SchedulePool::isProcessRunning(0));
        $this->assertFalse(SchedulePool::isProcessRunning(-5));
        $this->assertFalse(SchedulePool::isProcessRunning(PHP_INT_MAX));
    }

    public function testAProcessThatHasEndedIsNotRunning(): void
    {
        $process = proc_open([PHP_BINARY, '-r', 'exit(0);'], [], $pipes);
        $pid = proc_get_status($process)['pid'];
        proc_close($process);

        $this->assertFalse(SchedulePool::isProcessRunning($pid));
    }

    public function testADetachedCommandReturnsItsPidWithoutWaitingForIt(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Uses POSIX shell syntax (background & and $!).');
        }

        $started = microtime(true);
        $pid = SchedulePool::startDetached('(sleep 2) > /dev/null 2>&1 < /dev/null & echo $!');
        $elapsed = microtime(true) - $started;

        $this->assertNotNull($pid);
        $this->assertLessThan(1.0, $elapsed, 'must not wait for the two second command');
        $this->assertTrue(SchedulePool::isProcessRunning($pid));

        // SIGKILL (9); the constant needs pcntl and posix may be absent, so use kill.
        exec('kill -9 ' . (int) $pid);
    }

    public function testANonNumericResultIsNotAPid(): void
    {
        $this->assertNull(SchedulePool::startDetached('echo not-a-pid'));
    }
}
