<?php

namespace Phaseolies\Console\Commands\Migrations {
    if (!function_exists(__NAMESPACE__ . '\config')) {
        function config($key = null, $default = null)
        {
            return $key === 'database.default' ? 'default_connection' : $default;
        }
    }
}

namespace Tests\Unit\Console {

require_once __DIR__ . '/../Support/Model/MockTemporalRecord.php';
require_once __DIR__ . '/../Support/Model/MockMigratableRecord.php';
require_once __DIR__ . '/Support/CommandTestEnvironment.php';

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Console\Commands\Migrations\MigrateTemporalCommand;
use Phaseolies\Database\Database;
use Phaseolies\Database\Temporal\TemporalManager;
use Tests\Support\Model\MockTemporalRecord;
use Tests\Support\Model\MockMigratableRecord;
use Tests\Unit\Console\Support\InteractsWithFakeCommandIO;

class MigrateTemporalCommandTest extends TestCase
{
    private ?PDO $pdo = null;

    /** @var string[] */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(\Tests\Unit\Console\Support\CommandTestEnvironment::class)) {
            \Tests\Unit\Console\Support\CommandTestEnvironment::$config['database.default'] = 'default_connection';
        }

        TemporalManager::resetCache();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->setStaticProperty(Database::class, 'connections', ['default' => $this->pdo]);
    }

    protected function tearDown(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        TemporalManager::resetCache();
        $this->pdo = null;

        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
        $this->tempDirs = [];

        parent::tearDown();
    }

    public function testResolveConnectionForModelPrefersModelConnectionWhenNoOverride(): void
    {
        $command = new MigrateTemporalCommand();
        $method = new \ReflectionMethod($command, 'resolveConnectionForModel');

        $connection = $method->invoke($command, new MockTemporalRecord(), null);

        $this->assertSame('default', $connection);
    }

    public function testResolveConnectionForModelPrefersCommandOverride(): void
    {
        $command = new MigrateTemporalCommand();
        $method = new \ReflectionMethod($command, 'resolveConnectionForModel');

        $connection = $method->invoke($command, new MockTemporalRecord(), 'mysql_second');

        $this->assertSame('mysql_second', $connection);
    }

    public function testResolveConnectionForModelFallsBackToDefaultConfig(): void
    {
        $command = new MigrateTemporalCommand();
        $method = new \ReflectionMethod($command, 'resolveConnectionForModel');

        $model = new class extends \Phaseolies\Database\Entity\Model {
            protected $table = 'plain_temporal_records';
            protected $connection = null;
        };

        $connection = $method->invoke($command, $model, null);

        $this->assertSame('default_connection', $connection);
    }

    public function testHandleCreatesHistoryTableWithActorAndBranchColumnsForFreshTable(): void
    {
        $modelsPath = $this->writeDiscoverableModel();

        $command = $this->makeCommand(['path' => $modelsPath]);
        $exitCode = $command->handle();

        $historyTable = (new MockMigratableRecord())->historyTable();

        $this->assertSame(0, $exitCode);
        $this->assertEmpty($command->capturedErrors);
        $this->assertTrue($this->tableExists($historyTable));
        $this->assertTrue(TemporalManager::hasActorColumn($this->pdo, $historyTable, 'sqlite'));
        $this->assertTrue(TemporalManager::hasBranchColumn($this->pdo, $historyTable, 'sqlite'));
        $this->assertStringContainsString('Created: 1', implode(' ', $command->capturedSuccesses));
    }

    public function testHandleAddsMissingActorAndBranchColumnsToAnExistingTable(): void
    {
        $modelsPath = $this->writeDiscoverableModel();
        $historyTable = (new MockMigratableRecord())->historyTable();

        // Simulate a table that predates both the actor and branch columns.
        foreach (TemporalManager::createHistoryTableSql($historyTable, 'sqlite', false, false) as $sql) {
            $this->pdo->exec($sql);
        }
        TemporalManager::resetCache();

        $this->assertFalse(TemporalManager::hasActorColumn($this->pdo, $historyTable, 'sqlite'));
        $this->assertFalse(TemporalManager::hasBranchColumn($this->pdo, $historyTable, 'sqlite'));

        $command = $this->makeCommand(['path' => $modelsPath]);
        $exitCode = $command->handle();

        // The command's own column check is uncached, but our pre-check above
        // cached a stale "false" in TemporalManager's process-lifetime cache.
        TemporalManager::resetCache();

        $this->assertSame(0, $exitCode);
        $this->assertEmpty($command->capturedErrors);
        $this->assertTrue(TemporalManager::hasActorColumn($this->pdo, $historyTable, 'sqlite'));
        $this->assertTrue(TemporalManager::hasBranchColumn($this->pdo, $historyTable, 'sqlite'));

        $status = implode(' ', array_map(fn ($line) => $line[0], $command->capturedLines));
        $this->assertStringContainsString('actor, branch columns added', $status);
    }

    public function testHandleIsIdempotentOnceColumnsExist(): void
    {
        $modelsPath = $this->writeDiscoverableModel();
        $historyTable = (new MockMigratableRecord())->historyTable();

        foreach (TemporalManager::createHistoryTableSql($historyTable, 'sqlite', true, true) as $sql) {
            $this->pdo->exec($sql);
        }
        TemporalManager::resetCache();

        $command = $this->makeCommand(['path' => $modelsPath]);
        $exitCode = $command->handle();

        $this->assertSame(0, $exitCode);
        $status = implode(' ', array_map(fn ($line) => $line[0], $command->capturedLines));
        $this->assertStringContainsString('already exists', $status);
        $this->assertStringNotContainsString('column', $status);
    }

    public function testHandleDryRunPrintsBranchColumnSqlWithoutCreatingTheTable(): void
    {
        $modelsPath = $this->writeDiscoverableModel();
        $historyTable = (new MockMigratableRecord())->historyTable();

        $command = $this->makeCommand(['path' => $modelsPath, 'show' => true]);
        $exitCode = $command->handle();

        $this->assertSame(0, $exitCode);
        $this->assertFalse($this->tableExists($historyTable));

        $output = implode(' ', array_map(fn ($line) => $line[0], $command->capturedLines));
        $this->assertStringContainsString("branch TEXT NOT NULL DEFAULT 'main'", $output);
        $this->assertStringContainsString('actor TEXT NULL', $output);
    }

    /**
     * Write a copy of MockMigratableRecord into a temp directory so
     * MigrateTemporalCommand's filesystem-based discovery can find it. The
     * class itself is already loaded via the require_once at the top of
     * this file; discovery only reads this file's text to resolve the FQCN.
     *
     * @return string
     */
    private function writeDiscoverableModel(): string
    {
        $dir = sys_get_temp_dir() . '/doppar-migrate-temporal-' . bin2hex(random_bytes(5));
        mkdir($dir, 0755, true);
        $this->tempDirs[] = $dir;

        copy(
            __DIR__ . '/../Support/Model/MockMigratableRecord.php',
            $dir . '/MockMigratableRecord.php'
        );

        return $dir;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function makeCommand(array $options): MigrateTemporalCommand
    {
        $command = new class extends MigrateTemporalCommand
        {
            use InteractsWithFakeCommandIO;
        };
        $command->fakeOptions = $options;

        return $command;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }

    private function setStaticProperty(string $className, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass($className);
        $property = $reflection->getProperty($propertyName);
        $property->setValue(null, $value);
    }
}
}
