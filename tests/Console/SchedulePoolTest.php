<?php

namespace Tests\Unit\Console;

use Phaseolies\Console\Schedule\SchedulePool;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{string, array<int, string>}>
     */
    public static function commandProvider(): array
    {
        return [
            'plain arguments' => ['queue:run --queue=high --sleep=3', ['queue:run', '--queue=high', '--sleep=3']],
            'extra whitespace' => ["  queue:run   --queue=high \t --sleep=3  ", ['queue:run', '--queue=high', '--sleep=3']],
            'double quotes group words' => ['mail:send --subject="Hello world"', ['mail:send', '--subject=Hello world']],
            'single quotes group words' => ["mail:send --subject='Hello world'", ['mail:send', '--subject=Hello world']],
            'quote in the middle of a word' => ['run --name=Jo"hn Smith"', ['run', '--name=John Smith']],
            'apostrophe inside double quotes' => ['run --note="it\'s fine"', ['run', "--note=it's fine"]],
            'escaped quote inside double quotes' => ['run --x="a \\"b\\" c"', ['run', '--x=a "b" c']],
            'escaped space' => ['run path\\ with\\ spaces', ['run', 'path with spaces']],
            'empty quoted argument is kept' => ['run "" last', ['run', '', 'last']],
            'dollar and backtick stay literal' => ['run $HOME `id`', ['run', '$HOME', '`id`']],
            'single argument' => ['cron:list', ['cron:list']],
            'unbalanced quote falls back to whitespace' => ['run --note=it\'s ok', ['run', '--note=it\'s', 'ok']],
            'unbalanced double quote falls back to whitespace' => ['run "oops here', ['run', '"oops', 'here']],
        ];
    }

    /**
     * @param array<int, string> $expected
     */
    #[DataProvider('commandProvider')]
    public function testSplitCommandTokenizesLikeAShell(string $command, array $expected): void
    {
        $this->assertSame($expected, SchedulePool::splitCommand($command));
    }

    public function testFunctionAvailabilityReflectsWhatCanBeCalled(): void
    {
        $this->assertTrue(SchedulePool::isFunctionEnabled('strlen'));
        $this->assertFalse(SchedulePool::isFunctionEnabled('a_function_that_does_not_exist'));
    }

    public function testThePhpBinaryIsAnExecutablePath(): void
    {
        $binary = SchedulePool::phpBinary();

        $this->assertTrue($binary === 'php' || is_executable($binary), $binary);
        $this->assertDoesNotMatchRegularExpression('/(fpm|cgi|lsphp)/i', basename($binary));
    }
}
