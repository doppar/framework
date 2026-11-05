<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/MigratorTestCase.php';
require_once __DIR__ . '/../Console/Support/CommandTestEnvironment.php';

use Phaseolies\Console\Commands\Migrations\DbWipeCommand;
use Phaseolies\Console\Commands\Migrations\MigrateCommand;
use Phaseolies\Console\Commands\Migrations\MigrateFreshCommand;
use Phaseolies\Console\Commands\Migrations\MigrateRefreshCommand;
use Phaseolies\Console\Commands\Migrations\MigrateResetCommand;
use Phaseolies\Console\Commands\Migrations\MigrateRollbackCommand;
use Phaseolies\Console\Commands\Migrations\MigrateStatusCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Unit\Console\Support\CommandTestEnvironment;

/**
 * Drives the migration commands end to end (SQLite file + real migration files).
 */
class MigrateCommandsTest extends MigratorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Phaseolies\Config\Config::set('app.env', 'local');

        // Commands resolve the migrator through app(). Other command tests shadow app() in this
        // namespace for the whole process, so it is bound there as well as in the container.
        CommandTestEnvironment::bind('migrator', $this->migrator);
        $container = \Phaseolies\DI\Container::getInstance();
        $container->bind('migrator', fn() => $this->migrator);
        $container->bind('log', fn() => new class {
            public function __call($method, $arguments)
            {
                throw $arguments[0] instanceof \Throwable ? $arguments[0] : new \RuntimeException((string) $arguments[0]);
            }
        });

        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->createTable('2025_01_01_000002_create_b_table', 'b');
    }

    protected function tearDown(): void
    {
        CommandTestEnvironment::$appBindings = [];

        parent::tearDown();
    }

    private function run_(SymfonyCommand $command, array $input = []): CommandTester
    {
        $tester = new CommandTester($command);
        $tester->execute($input, ['interactive' => false, 'decorated' => false]);

        return $tester;
    }

    public function testMigrateReportsEachMigrationWithItsTime(): void
    {
        $tester = $this->run_(new MigrateCommand());

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertMatchesRegularExpression('/2025_01_01_000001_create_a_table \.+ \d+ms DONE/', $tester->getDisplay());
        $this->assertStringContainsString('2 migrations executed', $tester->getDisplay());
        $this->assertTrue($this->hasTable('a'));
    }

    public function testMigratePretendPrintsSqlAndLeavesDatabaseUntouched(): void
    {
        $tester = $this->run_(new MigrateCommand(), ['--pretend' => true]);

        $this->assertStringContainsString('CREATE TABLE', $tester->getDisplay());
        $this->assertFalse($this->hasTable('a'));
        $this->assertFalse($this->hasTable('migrations'));
    }

    public function testMigrateFailureIsReportedWithAnErrorExitCode(): void
    {
        $this->write('2025_01_01_000003_broken', "throw new \\RuntimeException('kaboom');");

        $tester = $this->run_(new MigrateCommand());

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('2025_01_01_000003_broken.php failed: kaboom', $tester->getDisplay());
    }

    public function testRollbackStepRollsBackThatManyMigrations(): void
    {
        $this->run_(new MigrateCommand());

        $tester = $this->run_(new MigrateRollbackCommand(), ['--step' => '1']);

        $this->assertStringContainsString('1 migration rolled back', $tester->getDisplay());
        $this->assertTrue($this->hasTable('a'));
        $this->assertFalse($this->hasTable('b'));
    }

    public function testRollbackRejectsConflictingAndInvalidOptions(): void
    {
        $this->run_(new MigrateCommand());

        $both = $this->run_(new MigrateRollbackCommand(), ['--step' => '1', '--batch' => '1']);
        $this->assertSame(1, $both->getStatusCode());
        $this->assertStringContainsString('either --step or --batch', $both->getDisplay());

        $invalid = $this->run_(new MigrateRollbackCommand(), ['--step' => 'abc']);
        $this->assertSame(1, $invalid->getStatusCode());
        $this->assertTrue($this->hasTable('a') && $this->hasTable('b'));
    }

    public function testResetIsDestructiveSoItRefusesWithoutForceWhenNotInteractive(): void
    {
        $this->run_(new MigrateCommand());

        $refused = $this->run_(new MigrateResetCommand());
        $this->assertSame(1, $refused->getStatusCode());
        $this->assertStringContainsString('--force', $refused->getDisplay());
        $this->assertTrue($this->hasTable('a'));

        $forced = $this->run_(new MigrateResetCommand(), ['--force' => true]);
        $this->assertSame(0, $forced->getStatusCode());
        $this->assertFalse($this->hasTable('a') || $this->hasTable('b'));
    }

    public function testRefreshRollsBackAndMigratesAgain(): void
    {
        $this->run_(new MigrateCommand());

        $tester = $this->run_(new MigrateRefreshCommand(), ['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Refreshed (2 rolled back, 2 executed)', $tester->getDisplay());
        $this->assertTrue($this->hasTable('a') && $this->hasTable('b'));
    }

    public function testFreshAndWipeDropEverything(): void
    {
        $this->run_(new MigrateCommand());

        $this->assertSame(1, $this->run_(new MigrateFreshCommand())->getStatusCode());
        $this->assertTrue($this->hasTable('a'));

        $this->assertSame(0, $this->run_(new MigrateFreshCommand(), ['--force' => true])->getStatusCode());
        $this->assertTrue($this->hasTable('a'));

        $this->assertSame(0, $this->run_(new DbWipeCommand(), ['--force' => true])->getStatusCode());
        $this->assertFalse($this->hasTable('a') || $this->hasTable('migrations'));
    }

    public function testStatusPendingExitCodeIsUsableInCi(): void
    {
        $this->assertSame(1, $this->run_(new MigrateStatusCommand(), ['--pending' => true])->getStatusCode());

        $this->run_(new MigrateCommand());

        $this->assertSame(0, $this->run_(new MigrateStatusCommand(), ['--pending' => true])->getStatusCode());
    }

    public function testStatusJsonIsMachineReadable(): void
    {
        $this->run_(new MigrateCommand());
        $this->write('2025_01_01_000003_create_c_table', "Schema::create('c', fn(Blueprint \$t) => \$t->id());");

        $data = json_decode($this->run_(new MigrateStatusCommand(), ['--json' => true])->getDisplay(), true);

        $this->assertSame(1, $data['pending']);
        $this->assertSame(
            [true, true, false],
            array_column($data['migrations'], 'ran')
        );
    }

    public function testProductionRequiresForceEvenForPlainMigrate(): void
    {
        \Phaseolies\Config\Config::set('app.env', 'production');

        $refused = $this->run_(new MigrateCommand());

        $this->assertSame(1, $refused->getStatusCode());
        $this->assertStringContainsString('production', $refused->getDisplay());
        $this->assertFalse($this->hasTable('a'));

        $this->assertSame(0, $this->run_(new MigrateCommand(), ['--force' => true])->getStatusCode());
        $this->assertTrue($this->hasTable('a'));
    }
}
