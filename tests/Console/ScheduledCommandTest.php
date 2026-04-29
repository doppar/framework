<?php

namespace Phaseolies\Console\Schedule {

use Carbon\Carbon;

final class ScheduledCommandTestEnvironment
{
    public static Carbon $now;

    public static string $timezone = 'UTC';

    public static string $storageRoot;

    public static array $execQueue = [];

    public static array $execCalls = [];

    public static array $runningPids = [];

    public static array $sleepCalls = [];

    public static function reset(): void
    {
        self::$storageRoot = sys_get_temp_dir() . '/doppar-schedule-tests';
        self::purgeDirectory(self::$storageRoot);

        if (!file_exists(self::$storageRoot)) {
            mkdir(self::$storageRoot, 0755, true);
        }

        self::$execQueue = [];
        self::$execCalls = [];
        self::$runningPids = [];
        self::$sleepCalls = [];

        self::freeze('2026-04-29 12:00:00', 'UTC');
    }

    public static function cleanup(): void
    {
        Carbon::setTestNow();
        self::purgeDirectory(self::$storageRoot);
        self::$execQueue = [];
        self::$execCalls = [];
        self::$runningPids = [];
        self::$sleepCalls = [];
    }

    public static function freeze(string $dateTime, string $timezone = 'UTC'): void
    {
        self::$timezone = $timezone;
        self::$now = Carbon::parse($dateTime, $timezone);

        Carbon::setTestNow(self::$now);
    }

    public static function advanceSeconds(int $seconds): void
    {
        self::$now = self::$now->copy()->addSeconds($seconds);

        Carbon::setTestNow(self::$now);
    }

    public static function now(?string $timezone = null): Carbon
    {
        return self::$now->copy()->setTimezone($timezone ?? self::$timezone);
    }

    public static function timestamp(): int
    {
        return self::$now->getTimestamp();
    }

    public static function queueExecResponse(int $returnCode, array $output = []): void
    {
        self::$execQueue[] = [
            'return' => $returnCode,
            'output' => $output,
        ];
    }

    public static function storagePath(string $path = ''): string
    {
        $fullPath = rtrim(self::$storageRoot, '/');

        if ($path !== '') {
            $fullPath .= '/' . ltrim($path, '/');
        }

        $directory = pathinfo($fullPath, PATHINFO_EXTENSION) === ''
            ? $fullPath
            : dirname($fullPath);

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        return $fullPath;
    }

    private static function purgeDirectory(string $directory): void
    {
        if (!file_exists($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($directory);
    }
}

function config($key = null, $default = null)
{
    return $key === 'app.timezone' ? ScheduledCommandTestEnvironment::$timezone : $default;
}

function now($timezone = null)
{
    return ScheduledCommandTestEnvironment::now($timezone);
}

function storage_path($path = '')
{
    return ScheduledCommandTestEnvironment::storagePath($path);
}

function time()
{
    return ScheduledCommandTestEnvironment::timestamp();
}

function sleep(int $seconds)
{
    ScheduledCommandTestEnvironment::$sleepCalls[] = $seconds;
    ScheduledCommandTestEnvironment::advanceSeconds($seconds);

    return 0;
}

function shell_exec(string $command): string
{
    if (preg_match('/ps -p (\d+) -o pid=/', $command, $matches)) {
        $pid = (int) $matches[1];

        return in_array($pid, ScheduledCommandTestEnvironment::$runningPids, true)
            ? $pid . PHP_EOL
            : '';
    }

    return '';
}

function exec(string $command, &$output = null, &$returnVar = null): ?string
{
    ScheduledCommandTestEnvironment::$execCalls[] = $command;

    $response = array_shift(ScheduledCommandTestEnvironment::$execQueue) ?? [
        'return' => 0,
        'output' => [],
    ];

    $output = $response['output'];
    $returnVar = $response['return'];

    return null;
}
}

namespace Tests\Unit\Console {

use Phaseolies\Console\Schedule\ScheduledCommand;
use Phaseolies\Console\Schedule\ScheduledCommandTestEnvironment as Env;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScheduledCommandTest extends TestCase
{
    private array $trackedCommands = [];

    protected function setUp(): void
    {
        parent::setUp();

        Env::reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->trackedCommands as $command) {
            $this->removeFile($this->lastRunFile($command));
            $this->removeFile($this->lockFile($command));
            $this->removeFile($this->lockFile($command) . '.pid');
            $this->removeFile($this->throttleFile($command));
        }

        Env::cleanup();

        parent::tearDown();
    }

    #[DataProvider('cronExpressionProvider')]
    public function testScheduleAliasesProduceExpectedCronExpressions(
        string $method,
        array $arguments,
        string $expectedExpression
    ): void {
        $command = $this->newScheduledCommand($method);

        $command->{$method}(...$arguments);

        $this->assertSame($expectedExpression, $command->getExpression());
        $this->assertFalse($command->isSecondSchedule());
    }

    #[DataProvider('secondBasedProvider')]
    public function testSecondBasedAliasesStoreExpectedInterval(string $method, int $expectedInterval): void
    {
        $command = $this->newScheduledCommand($method);

        $command->{$method}();

        $this->assertTrue($command->isSecondSchedule());
        $this->assertSame($expectedInterval, $command->getSecondInterval());
        $this->assertSame('* * * * *', $command->getExpression());
    }

    public function testAtSecondsStoresSpecificSeconds(): void
    {
        $command = $this->newScheduledCommand('specific-seconds');

        $command->atSeconds([5, 25, 55]);

        $this->assertTrue($command->isSecondSchedule());
        $this->assertSame([5, 25, 55], $command->getSpecificSeconds());
        $this->assertSame('* * * * *', $command->getExpression());
    }

    #[DataProvider('invalidSecondIntervalProvider')]
    public function testEverySecondsRejectsOutOfRangeValues(int $seconds): void
    {
        $command = $this->newScheduledCommand('invalid-interval');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Seconds must be between 1 and 59');

        $command->everySeconds($seconds);
    }

    public function testAtSecondsRejectsInvalidValues(): void
    {
        $command = $this->newScheduledCommand('invalid-seconds');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Second must be between 0 and 59');

        $command->atSeconds([10, 75]);
    }

    public function testTimezoneRetryAndOverlapMetadataCanBeConfigured(): void
    {
        $command = $this->newScheduledCommand('metadata');

        $command
            ->timezone('Asia/Dhaka')
            ->retry(3, 15)
            ->noOverlap(45)
            ->inBackground()
            ->throttle('5/1h')
            ->exclude('2026-12-25')
            ->when(fn() => true)
            ->between('09:00', '17:00');

        $this->assertSame('Asia/Dhaka', $command->getTimezone());
        $this->assertSame(3, $command->getMaxRetries());
        $this->assertSame(15, $command->getRetryDelay());
        $this->assertSame(45, $command->getMaxLockTime());
        $this->assertTrue($command->withoutOverlapping);
        $this->assertTrue($command->shouldRunInBackground());
        $this->assertSame('5/1h', $command->getRateLimit());
        $this->assertSame(['2026-12-25'], $command->getExcludedDates());
        $this->assertTrue($command->hasCustomCondition());
        $this->assertTrue($command->hasAdditionalConditions());
    }

    public function testTimezoneRejectsInvalidTimezone(): void
    {
        $command = $this->newScheduledCommand('bad-timezone');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid timezone 'Mars/Phobos'");

        $command->timezone('Mars/Phobos');
    }

    public function testBetweenRejectsInvalidTimeFormat(): void
    {
        $command = $this->newScheduledCommand('bad-between');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Time must be in "HH:MM" format');

        $command->between('9am', '17:00');
    }

    public function testBetweenSupportsNormalAndOvernightWindows(): void
    {
        $daytime = $this->newScheduledCommand('day-window');
        $daytime->everyMinute()->between('09:00', '17:00');

        Env::freeze('2026-04-29 12:00:00', 'UTC');
        $this->assertTrue($daytime->isDue());

        Env::freeze('2026-04-29 18:00:00', 'UTC');
        $this->assertFalse($daytime->isDue());

        $overnight = $this->newScheduledCommand('overnight-window');
        $overnight->everyMinute()->between('23:00', '02:00');

        Env::freeze('2026-04-29 01:15:00', 'UTC');
        $this->assertTrue($overnight->isDue());

        Env::freeze('2026-04-29 15:00:00', 'UTC');
        $this->assertFalse($overnight->isDue());
    }

    public function testExcludedDatePreventsExecutionAndTriggersCallback(): void
    {
        $events = [];
        $command = $this->newScheduledCommand('excluded-date');

        $command
            ->everyMinute()
            ->exclude('2026-04-29')
            ->onSuccess(function ($message) use (&$events) {
                $events[] = $message;
            });

        $this->assertFalse($command->isDue());
        $this->assertSame(['Skipped due to excluded date'], $events);
    }

    public function testCustomConditionPreventsExecutionAndTriggersCallback(): void
    {
        $events = [];
        $command = $this->newScheduledCommand('custom-condition');

        $command
            ->everyMinute()
            ->when(fn() => false)
            ->onSuccess(function ($message) use (&$events) {
                $events[] = $message;
            });

        $this->assertFalse($command->isDue());
        $this->assertSame(['Skipped due to custom condition'], $events);
    }

    public function testNotDueScheduleReturnsFalseAndReportsReason(): void
    {
        $events = [];
        $command = $this->newScheduledCommand('not-due');

        Env::freeze('2026-04-29 10:01:00', 'UTC');

        $command
            ->dailyAt('10:00')
            ->onSuccess(function ($message) use (&$events) {
                $events[] = $message;
            });

        $this->assertFalse($command->isDue());
        $this->assertSame(['Not due according to schedule'], $events);
    }

    public function testInvalidCronExpressionReturnsFalseAndTriggersFailureCallback(): void
    {
        $failures = [];
        $command = $this->newScheduledCommand('invalid-cron');

        $command
            ->cron('invalid cron')
            ->onFailure(function (\Throwable $exception, int $attempt) use (&$failures) {
                $failures[] = [$exception->getMessage(), $attempt];
            });

        $this->assertFalse($command->isDue());
        $this->assertSame([['Invalid CRON expression: invalid cron', 0]], $failures);
    }

    public function testThrottleLimitsRepeatedRunsUntilWindowExpires(): void
    {
        $command = $this->newScheduledCommand('throttle');

        $command->everyMinute()->throttle('1/1h');

        $this->assertTrue($command->isDue());
        $this->assertFalse($command->isDue());

        Env::advanceSeconds(3600);

        $this->assertTrue($command->isDue());
    }

    public function testNoOverlapBlocksWhenTrackedProcessIsRunning(): void
    {
        $events = [];
        $command = $this->newScheduledCommand('running-overlap');

        $command
            ->everyMinute()
            ->noOverlap(1)
            ->onSuccess(function ($message) use (&$events) {
                $events[] = $message;
            });

        file_put_contents($command->getLockFile(), Env::timestamp());
        file_put_contents($command->getLockFile() . '.pid', json_encode(['pid' => 4321]));
        Env::$runningPids = [4321];

        $this->assertFalse($command->isDue());
        $this->assertSame(['Skipped due to overlapping prevention'], $events);
    }

    public function testNoOverlapCleansStaleLockAndAcquiresNewOne(): void
    {
        $events = [];
        $command = $this->newScheduledCommand('stale-overlap');

        $command
            ->everyMinute()
            ->noOverlap(1)
            ->onSuccess(function ($message) use (&$events) {
                $events[] = $message;
            });

        file_put_contents($command->getLockFile(), Env::timestamp() - 10);
        file_put_contents($command->getLockFile() . '.pid', json_encode(['pid' => 9999]));

        $this->assertTrue($command->isDue());
        $this->assertFileExists($command->getLockFile());
        $this->assertFileExists($command->getLockFile() . '.pid');
        $this->assertContains('Cleaned up stale lock', $events);
        $this->assertContains('Lock acquired, command can run', $events);
    }

    public function testCleanupRemovesLockThrottleAndSecondScheduleArtifacts(): void
    {
        $command = $this->newScheduledCommand('cleanup');

        $command->everyFiveSeconds()->noOverlap()->throttle('1/1h');
        $command->lock();

        file_put_contents($command->getLockFile() . '.pid', json_encode(['pid' => 1111]));
        file_put_contents($this->lastRunFile($command->getCommand()), json_encode(['last_run' => Env::timestamp()]));
        file_put_contents($this->throttleFile($command->getCommand()), json_encode([Env::timestamp()]));

        $command->cleanup();

        $this->assertFileDoesNotExist($command->getLockFile());
        $this->assertFileDoesNotExist($command->getLockFile() . '.pid');
        $this->assertFileDoesNotExist($this->lastRunFile($command->getCommand()));
        $this->assertFileDoesNotExist($this->throttleFile($command->getCommand()));
    }

    public function testLockStateReflectsConfiguredLockWindow(): void
    {
        $command = $this->newScheduledCommand('lock-state');

        $command->noOverlap(1);
        $command->lock();

        $this->assertTrue($command->isLocked());

        Env::advanceSeconds(61);

        $this->assertFalse($command->isLocked());
    }

    public function testRunExecutesForegroundCommandAndReturnsOutput(): void
    {
        $events = [];
        $command = $this->newScheduledCommand('foreground-run');

        Env::queueExecResponse(0, ['line one', 'line two']);

        $command->onSuccess(function ($result) use (&$events) {
            $events[] = $result;
        });

        $result = $command->run();

        $this->assertSame("line one\nline two", $result);
        $this->assertSame(["line one\nline two"], $events);
        $this->assertCount(1, Env::$execCalls);
    }

    public function testRunExecutesBackgroundCommandWithDetachedOutput(): void
    {
        $command = $this->newScheduledCommand('background-run');

        Env::queueExecResponse(0);

        $result = $command->inBackground()->run();

        $this->assertTrue($result);
        $this->assertStringContainsString('> /dev/null 2>&1 &', Env::$execCalls[0]);
    }

    public function testRunThrowsAndInvokesFailureCallbackWhenExecutionFails(): void
    {
        $failures = [];
        $command = $this->newScheduledCommand('failed-run');

        Env::queueExecResponse(1);

        $command->onFailure(function (\Throwable $exception, int $attempt) use (&$failures) {
            $failures[] = [$exception->getMessage(), $attempt];
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Command failed with exit code: 1');

        try {
            $command->run();
        } finally {
            $this->assertSame([['Command failed with exit code: 1', 1]], $failures);
        }
    }

    public function testRunWithRetryRetriesUntilCommandSucceeds(): void
    {
        $command = $this->newScheduledCommand('retry-run');

        Env::queueExecResponse(1);
        Env::queueExecResponse(0, ['done']);

        $result = $command->retry(1, 5)->runWithRetry();

        $this->assertSame('done', $result);
        $this->assertCount(2, Env::$execCalls);
        $this->assertSame([5], Env::$sleepCalls);
    }

    public function testSecondIntervalScheduleUsesLastRunTimestamp(): void
    {
        $command = $this->newScheduledCommand('second-interval');

        $command->everyFiveSeconds();

        $this->assertTrue($command->isDue());

        file_put_contents(
            $this->lastRunFile($command->getCommand()),
            json_encode(['last_run' => Env::timestamp()])
        );

        $this->assertFalse($command->isDue());

        Env::advanceSeconds(5);

        $this->assertTrue($command->isDue());
    }

    public function testSpecificSecondScheduleOnlyRunsOnConfiguredSecondsAndSkipsDuplicates(): void
    {
        $command = $this->newScheduledCommand('specific-second');

        Env::freeze('2026-04-29 12:00:10', 'UTC');

        $command->atSeconds([10, 40]);

        $this->assertTrue($command->isDue());

        file_put_contents(
            $this->lastRunFile($command->getCommand()),
            json_encode(['last_run' => Env::timestamp()])
        );

        $this->assertFalse($command->isDue());

        Env::advanceSeconds(30);

        $this->assertTrue($command->isDue());

        Env::advanceSeconds(1);

        $this->assertFalse($command->isDue());
    }

    public static function cronExpressionProvider(): array
    {
        return [
            'every minute' => ['everyMinute', [], '* * * * *'],
            'every two minutes' => ['everyTwoMinutes', [], '*/2 * * * *'],
            'every three minutes' => ['everyThreeMinutes', [], '*/3 * * * *'],
            'every four minutes' => ['everyFourMinutes', [], '*/4 * * * *'],
            'every five minutes' => ['everyFiveMinutes', [], '*/5 * * * *'],
            'every ten minutes' => ['everyTenMinutes', [], '*/10 * * * *'],
            'every fifteen minutes' => ['everyFifteenMinutes', [], '*/15 * * * *'],
            'every twenty minutes' => ['everyTwentyMinutes', [], '*/20 * * * *'],
            'every thirty minutes' => ['everyThirtyMinutes', [], '*/30 * * * *'],
            'hourly' => ['hourly', [], '0 * * * *'],
            'daily' => ['daily', [], '0 0 * * *'],
            'daily at' => ['dailyAt', ['13:45'], '45 13 * * *'],
            'weekly' => ['weekly', [], '0 0 * * 0'],
            'monthly' => ['monthly', [], '0 0 1 * *'],
            'quarterly' => ['quarterly', [], '0 0 1 */3 *'],
            'yearly' => ['yearly', [], '0 0 1 1 *'],
            'hourly at' => ['hourlyAt', [12], '12 * * * *'],
            'every odd hour' => ['everyOddHour', [5], '5 1-23/2 * * *'],
            'every two hours' => ['everyTwoHours', [10], '10 */2 * * *'],
            'every four hours' => ['everyFourHours', [20], '20 */4 * * *'],
            'every six hours' => ['everySixHours', [30], '30 */6 * * *'],
            'twice daily' => ['twiceDaily', [1, 13], '0 1,13 * * *'],
            'twice daily at' => ['twiceDailyAt', [1, 13, 15], '15 1,13 * * *'],
            'weekly on' => ['weeklyOn', [2, '09:30'], '30 09 * * 2'],
            'monthly on' => ['monthlyOn', [3, '04:15'], '15 04 3 * *'],
            'twice monthly' => ['twiceMonthly', [1, 15, '08:00'], '00 08 1,15 * *'],
            'last day of month' => ['lastDayOfMonth', ['23:59'], '59 23 L * *'],
        ];
    }

    public static function secondBasedProvider(): array
    {
        return [
            'every second' => ['everySecond', 1],
            'every five seconds' => ['everyFiveSeconds', 5],
            'every ten seconds' => ['everyTenSeconds', 10],
            'every fifteen seconds' => ['everyFifteenSeconds', 15],
            'every twenty seconds' => ['everyTwentySeconds', 20],
            'every thirty seconds' => ['everyThirtySeconds', 30],
        ];
    }

    public static function invalidSecondIntervalProvider(): array
    {
        return [
            'zero seconds' => [0],
            'sixty seconds' => [60],
        ];
    }

    private function newScheduledCommand(string $suffix): ScheduledCommand
    {
        $command = 'doppar:test:' . $suffix . ':' . substr(md5(uniqid((string) mt_rand(), true)), 0, 10);
        $this->trackedCommands[] = $command;

        return new ScheduledCommand($command);
    }

    private function lastRunFile(string $command): string
    {
        return sys_get_temp_dir() . '/doppar_cron_' . md5($command);
    }

    private function lockFile(string $command): string
    {
        return sys_get_temp_dir() . '/doppar_cron_lock_' . md5($command);
    }

    private function throttleFile(string $command): string
    {
        return Env::storagePath('schedule/cron_throttle_' . md5($command) . '.log');
    }

    private function removeFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
}
