<?php

namespace Tests\Unit\Console;

use Phaseolies\Console\Commands\Cron\CronFinishCommand;
use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Schedule\ScheduledCommand;
use Phaseolies\Console\Schedule\SchedulePool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\Console\Support\ScratchApp;
use Tests\Console\Support\ScratchCronRunCommand;

/**
 * Runs the real CronRunCommand against a scratch application whose "pool" is a
 * stub, using real child processes and real shell commands. Each test runs in
 * its own PHP process because ScheduledCommandTest defines namespaced stubs
 * (shell_exec, time, posix_kill...) that would otherwise stay active.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class CronRunProcessTest extends TestCase
{
    private ScratchApp $app;

    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        $this->app = new ScratchApp();
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        // Let background jobs finish before their directory disappears.
        ScratchApp::waitUntil(fn() => !$this->anyJobRunning(), 8);

        $this->app->destroy();
    }

    private function anyJobRunning(): bool
    {
        foreach (glob($this->app->path('storage/schedule/*.pid')) ?: [] as $pidFile) {
            $info = json_decode((string) file_get_contents($pidFile), true);

            if (SchedulePool::isProcessRunning((int) ($info['pid'] ?? 0))) {
                return true;
            }
        }

        return false;
    }

    private function task(string $command): ScheduledCommand
    {
        return (new ScheduledCommand($command))->everyMinute()->timezone('UTC');
    }

    private function runner(ScheduledCommand ...$tasks): ScratchCronRunCommand
    {
        $runner = new ScratchCronRunCommand();
        $runner->scheduled = $tasks;

        return $runner;
    }

    // ------------------------------------------------------------------
    // Background tasks
    // ------------------------------------------------------------------

    public function testABackgroundTaskDoesNotMakeTheSchedulerWaitForIt(): void
    {
        $log = $this->app->path('job.log');
        $runner = $this->runner($this->task('slow 3')->inBackground()->sendOutputTo($log));

        $started = microtime(true);
        $status = $runner->handle();
        $elapsed = microtime(true) - $started;

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertLessThan(1.5, $elapsed, 'cron:run must return while the 3 second job is still running');

        $this->assertTrue(
            ScratchApp::waitUntil(fn() => str_contains((string) @file_get_contents($log), 'slow done')),
            'the job still ran to completion in the background'
        );
    }

    public function testTheFinishCallbackReceivesTheJobsExitCode(): void
    {
        $log = $this->app->path('job.log');
        $runner = $this->runner($this->task('fail')->inBackground()->sendOutputTo($log));

        $runner->handle();

        $this->assertTrue(ScratchApp::waitUntil(fn() => $this->app->callsTo('cron:finish') !== []));

        $finish = $this->app->callsTo('cron:finish')[0]['args'];

        $this->assertSame('cron:finish', $finish[0]);
        $this->assertSame('3', $finish[3], '$? of the failed job (3) is passed on to cron:finish');
    }

    public function testABackgroundTaskRunsFromTheAppRootWhateverDirectoryCronStartedIn(): void
    {
        // cPanel starts cron jobs in the account's home directory.
        chdir('/');

        $runner = $this->runner($this->task('echo hi')->inBackground()->sendOutputTo($this->app->path('job.log')));
        $status = $runner->handle();

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertTrue(ScratchApp::waitUntil(fn() => $this->app->callsTo('echo') !== []));
        $this->assertSame(realpath($this->app->root), realpath($this->app->callsTo('echo')[0]['cwd']));
    }

    public function testBackgroundTasksReceiveTheScheduleEnvironmentFlag(): void
    {
        $runner = $this->runner($this->task('echo hi')->inBackground()->sendOutputTo($this->app->path('job.log')));
        $runner->handle();

        $this->assertTrue(ScratchApp::waitUntil(fn() => $this->app->callsTo('echo') !== []));
        $this->assertSame('true', $this->app->callsTo('echo')[0]['flag']);
    }

    public function testAProtectedBackgroundTaskIsNotStartedASecondTimeWhileItRuns(): void
    {
        $log = $this->app->path('job.log');

        $first = $this->runner($this->task('slow 3')->inBackground()->noOverlap()->sendOutputTo($log));
        $first->handle();

        // The scheduler process ends: everything it built is destroyed. The job must keep its lock.
        unset($first);
        gc_collect_cycles();

        $this->assertTrue(ScratchApp::waitUntil(fn() => $this->app->callsTo('slow') !== []), 'the first job started');

        $second = $this->runner($this->task('slow 3')->inBackground()->noOverlap()->sendOutputTo($log));
        $second->handle();
        unset($second);
        gc_collect_cycles();

        // A run that only *skipped* the task must not have released the running job's lock.
        $third = $this->runner($this->task('slow 3')->inBackground()->noOverlap()->sendOutputTo($log));
        $third->handle();

        usleep(500_000);

        $this->assertFalse($third->said('Running:'), 'no run may start another copy while the job is still running');
        $this->assertCount(1, $this->app->callsTo('slow'));
    }

    public function testTheLockIsReleasedWhenTheJobFinishesSoTheTaskCanRunAgain(): void
    {
        $log = $this->app->path('job.log');
        $first = $this->runner($this->task('echo hi')->inBackground()->noOverlap()->sendOutputTo($log));
        $first->handle();
        unset($first);

        $this->assertTrue(ScratchApp::waitUntil(fn() => $this->app->callsTo('cron:finish') !== []));

        // The stub pool only records cron:finish, so run the real command with the recorded arguments.
        $args = $this->app->callsTo('cron:finish')[0]['args'];
        $finish = new class extends CronFinishCommand {
            public array $given = [];

            protected function argument($key = null)
            {
                return $this->given[$key] ?? null;
            }
        };
        $finish->given = ['finish_id' => $args[1], 'release_lock' => $args[2], 'exit_code' => $args[3]];

        $this->assertSame(Command::SUCCESS, $finish->handle());

        $again = $this->runner($this->task('echo hi')->inBackground()->noOverlap()->sendOutputTo($log));
        $again->handle();

        $this->assertTrue($again->said('Running:'), 'the task runs again once its job has finished');
    }

    public function testBackgroundFallsBackToRunningInTheForegroundWhenNoProcessCanBeStarted(): void
    {
        $runner = $this->runner($this->task('ok')->inBackground()->noOverlap());
        $runner->canStartDetached = false;

        $status = $runner->handle();

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertTrue($runner->said('running in the foreground'));
        $this->assertCount(1, $this->app->callsTo('ok'), 'the task must not be lost');

        // and its lock was released
        $again = $this->runner($this->task('ok')->inBackground()->noOverlap());
        $this->assertTrue($again->handle() === Command::SUCCESS && $again->said('Running:'));
    }

    // ------------------------------------------------------------------
    // Foreground tasks
    // ------------------------------------------------------------------

    public function testForegroundTasksRunWithThePhpBinaryThatIsRunningTheScheduler(): void
    {
        // A different "php" comes first on PATH, as on a host with several PHP versions.
        $decoyDir = $this->app->path('decoy');
        mkdir($decoyDir);
        file_put_contents($decoyDir . '/php', "#!/bin/sh\necho decoy >> " . escapeshellarg($this->app->path('decoy.log')) . "\nexit 1\n");
        chmod($decoyDir . '/php', 0755);

        $path = $decoyDir . ':' . getenv('PATH');
        putenv('PATH=' . $path);
        $_SERVER['PATH'] = $_ENV['PATH'] = $path;

        $runner = $this->runner($this->task('ok'));
        $status = $runner->handle();

        $this->assertSame(Command::SUCCESS, $status, implode(' | ', $runner->messages));
        $this->assertFileDoesNotExist($this->app->path('decoy.log'), 'the decoy php must not be used');
        $this->assertSame(SchedulePool::phpBinary(), $this->app->callsTo('ok')[0]['php']);
    }

    public function testQuotedArgumentsReachTheCommandAsOneArgument(): void
    {
        $runner = $this->runner($this->task('echo --message="hello world" plain \'it is\''));
        $runner->handle();

        $this->assertSame(['echo', '--message=hello world', 'plain', 'it is'], $this->app->callsTo('echo')[0]['args']);
    }

    public function testQuotedArgumentsSurviveTheBackgroundShellToo(): void
    {
        $runner = $this->runner(
            $this->task('echo --message="hello world" --note="it\'s fine"')->inBackground()->sendOutputTo($this->app->path('job.log'))
        );
        $runner->handle();

        $this->assertTrue(ScratchApp::waitUntil(fn() => $this->app->callsTo('echo') !== []));
        $this->assertSame(['echo', '--message=hello world', "--note=it's fine"], $this->app->callsTo('echo')[0]['args']);
    }

    public function testForegroundTasksReceiveTheScheduleEnvironmentFlag(): void
    {
        $this->runner($this->task('ok'))->handle();

        $this->assertSame('true', $this->app->callsTo('ok')[0]['flag']);
    }

    // ------------------------------------------------------------------
    // Exit status and failures
    // ------------------------------------------------------------------

    public function testARunWithNoFailuresExitsSuccessfully(): void
    {
        $this->assertSame(Command::SUCCESS, $this->runner($this->task('ok'))->handle());
    }

    public function testAFailingTaskMakesTheRunExitWithFailureAndShowsWhy(): void
    {
        $runner = $this->runner($this->task('fail'));

        $this->assertSame(Command::FAILURE, $runner->handle());
        $this->assertTrue($runner->said('exit code 3'));
        $this->assertTrue($runner->said('boom'));
    }

    public function testATaskThatThrowsDoesNotStopTheTasksAfterIt(): void
    {
        $broken = new class ('never-started') extends ScheduledCommand {
            public function shouldRunInBackground(): bool
            {
                throw new \Error('cannot start');
            }
        };
        $broken->everyMinute()->timezone('UTC');

        $runner = $this->runner($broken, $this->task('ok'));

        $this->assertSame(Command::FAILURE, $runner->handle());
        $this->assertTrue($runner->said('cannot start'));
        $this->assertCount(1, $this->app->callsTo('ok'), 'the second task still ran');
    }

    public function testAMissingScheduleClassIsReportedInsteadOfCrashing(): void
    {
        $runner = new ScratchCronRunCommand();

        $this->assertSame(Command::FAILURE, $runner->handle());
        $this->assertTrue($runner->said('was not found'));
    }

    public function testTasksThatAreNotDueAreLeftAlone(): void
    {
        $notDue = (new ScheduledCommand('ok'))->cron('0 0 31 2 *')->timezone('UTC');   // 31 Feb: never

        $runner = $this->runner($notDue);

        $this->assertSame(Command::SUCCESS, $runner->handle());
        $this->assertSame([], $this->app->calls());
        $this->assertTrue($runner->said('No scheduled commands are ready to run'));
    }

    // ------------------------------------------------------------------
    // Overlap protection under a real race
    // ------------------------------------------------------------------

    public function testOnlyOneOfManySimultaneousSchedulerRunsMayStartAProtectedTask(): void
    {
        $processes = [];
        $startAt = microtime(true) + 1.0;

        for ($i = 0; $i < 8; $i++) {
            $process = proc_open(
                [PHP_BINARY, __DIR__ . '/Support/overlap_probe.php', $this->app->root, (string) $startAt],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            $processes[] = [$process, $pipes];
        }

        $answers = [];

        foreach ($processes as [$process, $pipes]) {
            $answers[] = trim((string) stream_get_contents($pipes[1]));
            $this->assertSame('', trim((string) stream_get_contents($pipes[2])));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        $this->assertSame(1, count(array_filter($answers, fn($a) => $a === 'RUN')), 'answers: ' . implode(',', $answers));
        $this->assertSame(7, count(array_filter($answers, fn($a) => $a === 'SKIP')));
    }

    public function testCheckingForARunningCopyWaitsForWhoeverIsCurrentlyTakingTheLock(): void
    {
        // Hold the guard, as a scheduler run in the middle of its check-and-lock would.
        $guard = fopen((new ScheduledCommand('slow 1'))->getLockFile() . '.guard', 'c');
        $this->assertTrue(flock($guard, LOCK_EX));

        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/Support/overlap_probe.php', $this->app->root, '0'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        stream_set_blocking($pipes[1], false);

        usleep(1_200_000);
        $this->assertSame('', trim((string) fgets($pipes[1])), 'the probe must be waiting for the guard, not deciding on its own');

        flock($guard, LOCK_UN);
        fclose($guard);

        stream_set_blocking($pipes[1], true);
        $this->assertSame('RUN', trim((string) fgets($pipes[1])), 'once the guard is free it can take the lock');

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }

    public function testARunThatSkipsAProtectedTaskLeavesTheRunningTasksLockAlone(): void
    {
        $running = $this->task('slow 1')->noOverlap();
        $this->assertTrue($running->isDue(), 'the first run takes the lock');

        // Later runs find it locked, skip it, and end. Ending must not release the lock.
        $skipped = $this->task('slow 1')->noOverlap();
        $this->assertFalse($skipped->isDue());
        unset($skipped);
        gc_collect_cycles();

        $another = $this->task('slow 1')->noOverlap();
        $this->assertFalse($another->isDue(), 'the lock still belongs to the first run');

        $running->releaseLock();
        $this->assertTrue($this->task('slow 1')->noOverlap()->isDue(), 'and is free once its owner releases it');
    }

    // ------------------------------------------------------------------
    // Daemon
    // ------------------------------------------------------------------

    public function testTheDaemonRefusesToStartWhileAnotherHoldsTheDaemonLock(): void
    {
        $lock = fopen($this->app->path('storage/schedule/cron_daemon.lock'), 'c');
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB), 'the test holds the lock, as a running daemon would');

        $runner = $this->runner();
        $runner->givenOptions = ['daemon' => true];

        $this->assertSame(Command::SUCCESS, $runner->handle());
        $this->assertTrue($runner->said('already running'));
        $this->assertFileDoesNotExist($this->app->path('storage/schedule/cron_daemon.pid'), 'a refused daemon must not touch the PID file');

        fclose($lock);
    }

    public function testTheDaemonLockIsFreeWhenNoDaemonRunsAndTakenOnlyOnce(): void
    {
        $first = new ScratchCronRunCommand();
        $second = new ScratchCronRunCommand();

        $acquire = fn(object $runner) => (new \ReflectionMethod($runner, 'acquireDaemonLock'))->invoke($runner);

        $this->assertTrue($acquire($first));
        $this->assertFalse($acquire($second), 'a second daemon cannot take it');
    }

    // ------------------------------------------------------------------
    // Command building and the no-process fallback
    // ------------------------------------------------------------------

    public function testTheBackgroundShellCommandIsFullyDetachedAndUsesAbsolutePaths(): void
    {
        $runner = new ScratchCronRunCommand();

        $shell = $runner->buildBackground('queue:run --queue=mail', '/tmp/job.log', 'cron_finish_1', true, [
            'APP_SCHEDULE_RUNNING' => 'true',
            'BAD-NAME' => 'x',
        ]);

        $this->assertStringContainsString("cd '" . $this->app->root . "'", $shell);
        $this->assertStringContainsString("'" . SchedulePool::phpBinary() . "' '" . $this->app->root . "/pool'", $shell);
        $this->assertStringContainsString("APP_SCHEDULE_RUNNING='true'", $shell);
        $this->assertStringNotContainsString('BAD-NAME', $shell, 'invalid variable names are dropped');
        $this->assertStringContainsString("'queue:run' '--queue=mail'", $shell);
        $this->assertStringContainsString("cron:finish 'cron_finish_1' 1 \$?", $shell);
        $this->assertStringEndsWith('> /dev/null 2>&1 < /dev/null & echo $!', $shell, 'must not hold the caller\'s output pipe');
    }

    public function testShellSpecialCharactersInArgumentsCannotBreakOutOfTheCommand(): void
    {
        $runner = new ScratchCronRunCommand();

        $shell = $runner->buildBackground('demo --x="a; touch /tmp/pwned" $(id) `id`', '/tmp/job.log', 'id', false);

        $this->assertStringContainsString("'--x=a; touch /tmp/pwned'", $shell);
        $this->assertStringContainsString("'\$(id)'", $shell);
        $this->assertStringContainsString("'`id`'", $shell);
    }

    public function testACommandCanRunInsideTheSchedulerProcessWhenNothingCanStartAProcess(): void
    {
        $application = new Application();
        $register = method_exists($application, 'addCommand') ? 'addCommand' : 'add';
        $application->{$register}(new class ('demo') extends SymfonyCommand {
            protected function configure(): void
            {
                $this->ignoreValidationErrors();
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $output->writeln('args: ' . implode('|', array_slice($_SERVER['argv'] ?? [], 0)));
                $output->writeln('message: ' . (string) $input->getParameterOption('--message'));

                return 4;
            }
        });

        $runner = new ScratchCronRunCommand();
        $runner->setApplication($application);

        $result = $runner->runProcessInProcess('demo --message="hello world"');

        $this->assertSame(4, $result['code']);
        $this->assertStringContainsString('message: hello world', $result['stdout']);
    }

    public function testRunningInProcessWithoutAConsoleApplicationIsAClearError(): void
    {
        $runner = new ScratchCronRunCommand();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('proc_open, exec and shell_exec are disabled');

        $runner->runProcessInProcess('ok');
    }
}
