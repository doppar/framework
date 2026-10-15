<?php

namespace Tests\Unit\Model;

require_once __DIR__ . '/../Support/Model/MockBranchableRecord.php';
require_once __DIR__ . '/../Support/Model/MockTemporalRecord.php';
require_once __DIR__ . '/../Support/MockContainer.php';

use Carbon\Carbon;
use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Hooks\HookHandler;
use Phaseolies\Database\Temporal\TemporalManager;
use Phaseolies\DI\Container;
use RuntimeException;
use Tests\Support\MockContainer;
use Tests\Support\Model\MockBranchableRecord;
use Tests\Support\Model\MockTemporalRecord;

class TemporalBranchTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?MockContainer $container = null;

    protected function setUp(): void
    {
        $this->container = new MockContainer();
        Container::setInstance($this->container);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTables();
        $this->setupDatabaseConnections();

        HookHandler::$hooks = [];
        TemporalManager::resetCache();
        $this->registerTemporalHooks(MockBranchableRecord::class);
        $this->registerTemporalHooks(MockTemporalRecord::class);

        Carbon::setTestNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        HookHandler::$hooks = [];
        TemporalManager::resetCache();
        $this->tearDownDatabaseConnections();
        Container::forgetInstance();
        $this->container = null;
        $this->pdo = null;
    }

    public function testBranchForksFromMainWithoutDisturbingMainHistory(): void
    {
        $this->travelTo('2026-02-01 10:00:00.000000');
        $created = MockBranchableRecord::create([
            'title' => 'Widget',
            'price' => '100',
            'internal_note' => 'cost basis is high',
        ]);
        $record = MockBranchableRecord::find($created->id);

        $this->travelTo('2026-02-01 11:00:00.000000');
        $record->price = '120';
        $record->save();

        $this->travelTo('2026-02-01 12:00:00.000000');
        $this->assertTrue($record->branch('proposed-pricing', '2026-02-01 10:30:00'));

        $mainHistory = $record->history();
        $this->assertCount(2, $mainHistory);
        $this->assertSame(['created', 'updated'], $mainHistory->map->__action->all());

        $branchHistory = $record->history('proposed-pricing');
        $this->assertCount(1, $branchHistory);
        $this->assertSame('branched', $branchHistory[0]->__action);
        $this->assertSame(100, $branchHistory[0]->price);

        $onMain = MockBranchableRecord::at('2026-02-01 12:30:00')->find($record->id);
        $onBranch = MockBranchableRecord::at('2026-02-01 12:30:00', 'proposed-pricing')->find($record->id);

        $this->assertSame(120, $onMain->price);
        $this->assertSame(100, $onBranch->price);
    }

    public function testBranchReturnsFalseWhenSourceSnapshotIsMissing(): void
    {
        $this->travelTo('2026-02-01 10:00:00.000000');
        $created = MockBranchableRecord::create([
            'title' => 'Widget',
            'price' => '100',
            'internal_note' => 'n/a',
        ]);
        $record = MockBranchableRecord::find($created->id);

        $this->assertFalse($record->branch('too-early', '2026-01-01 00:00:00'));
    }

    public function testBranchRejectsEmptyAndReservedNames(): void
    {
        $created = MockBranchableRecord::create([
            'title' => 'Widget',
            'price' => '100',
            'internal_note' => 'n/a',
        ]);
        $record = MockBranchableRecord::find($created->id);

        $this->expectException(\InvalidArgumentException::class);
        $record->branch('main', '2026-02-01 10:00:00');
    }

    public function testBranchThrowsForModelsNotConfiguredForBranching(): void
    {
        $created = MockTemporalRecord::create([
            'title' => 'Contract A',
            'status' => 'draft',
            'body' => 'Original body',
        ]);
        $record = MockTemporalRecord::find($created->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not support branches');
        $record->branch('attempt', '2026-01-01 00:00:00');
    }

    public function testAtWithNonMainBranchThrowsForModelsNotConfiguredForBranching(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not support branches');
        MockTemporalRecord::at('2026-01-01 00:00:00', 'some-branch');
    }

    public function testDiffRespectsCastsAndHidesUnexposableAttributes(): void
    {
        $this->travelTo('2026-02-01 10:00:00.000000');
        $created = MockBranchableRecord::create([
            'title' => 'Widget',
            'price' => '100',
            'internal_note' => 'cost basis is high',
        ]);
        $record = MockBranchableRecord::find($created->id);

        $this->travelTo('2026-02-01 11:00:00.000000');
        $record->price = '150';
        $record->internal_note = 'revised note';
        $record->save();

        $diff = $record->diff('2026-02-01 10:30:00', '2026-02-01 11:30:00');

        $this->assertIsInt($diff['changes']['price']['from']);
        $this->assertIsInt($diff['changes']['price']['to']);
        $this->assertSame(100, $diff['changes']['price']['from']);
        $this->assertSame(150, $diff['changes']['price']['to']);
        $this->assertArrayNotHasKey('internal_note', $diff['changes']);
        $this->assertArrayNotHasKey('internal_note', $diff['added']);
        $this->assertArrayNotHasKey('internal_note', $diff['removed']);
    }

    public function testRestoreFromBranchCreatesANewMainVersionAndNeverRewritesHistory(): void
    {
        $this->travelTo('2026-02-01 10:00:00.000000');
        $created = MockBranchableRecord::create([
            'title' => 'Widget',
            'price' => '100',
            'internal_note' => 'n/a',
        ]);
        $record = MockBranchableRecord::find($created->id);

        $this->travelTo('2026-02-01 11:00:00.000000');
        $record->price = '120';
        $record->save();

        $this->travelTo('2026-02-01 12:00:00.000000');
        $record->branch('rollback-candidate', '2026-02-01 10:30:00');

        $this->travelTo('2026-02-01 13:00:00.000000');
        $this->assertTrue($record->restoreTo('2026-02-01 12:30:00', 'rollback-candidate'));

        $fresh = MockBranchableRecord::find($record->id);
        $this->assertSame(100, $fresh->price);

        // Restoring persisted through the normal save()/snapshot path, so the
        // main timeline only ever grew — nothing was rewritten.
        $this->assertCount(3, $fresh->history());
        $this->assertSame(['created', 'updated', 'updated'], $fresh->history()->map->__action->all());
    }

    public function testRestoreToNonMainBranchThrowsForModelsNotConfiguredForBranching(): void
    {
        $created = MockTemporalRecord::create([
            'title' => 'Contract A',
            'status' => 'draft',
            'body' => 'Original body',
        ]);
        $record = MockTemporalRecord::find($created->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not support branches');
        $record->restoreTo('2026-01-01 00:00:00', 'some-branch');
    }

    public function testMigrateTemporalAddsBranchColumnToExistingTable(): void
    {
        // A table name unique to this test, so the process-lifetime column
        // cache can't carry a stale answer in from another test method.
        $table  = 'migration_check_history';
        $driver = 'sqlite';

        $statements = TemporalManager::createHistoryTableSql($table, $driver, false, false);
        foreach ($statements as $statement) {
            $this->pdo->exec($statement);
        }

        $this->assertFalse(
            TemporalManager::hasBranchColumn($this->pdo, $table, $driver)
        );

        $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN branch TEXT NOT NULL DEFAULT 'main'");
        TemporalManager::resetCache();

        $this->assertTrue(
            TemporalManager::hasBranchColumn($this->pdo, $table, $driver)
        );

        $this->pdo->exec("DROP TABLE {$table}");
    }

    public function testTemporalManagerDdlIncludesBranchColumnAndIndexWhenEnabled(): void
    {
        $mysql = TemporalManager::createHistoryTableSql('widgets_history', 'mysql', false, true);
        $pgsql = TemporalManager::createHistoryTableSql('widgets_history', 'pgsql', false, true);
        $sqlite = TemporalManager::createHistoryTableSql('widgets_history', 'sqlite', false, true);

        $this->assertStringContainsString("`branch` VARCHAR(50) NOT NULL DEFAULT 'main'", $mysql[0]);
        $this->assertStringContainsString('idx_branch', $mysql[0]);

        $this->assertCount(4, $pgsql);
        $this->assertStringContainsString("branch VARCHAR(50) NOT NULL DEFAULT 'main'", $pgsql[0]);
        $this->assertStringContainsString('idx_widgets_history_branch', $pgsql[3]);

        $this->assertCount(4, $sqlite);
        $this->assertStringContainsString("branch TEXT NOT NULL DEFAULT 'main'", $sqlite[0]);
        $this->assertStringContainsString('idx_widgets_history_branch', $sqlite[3]);
    }

    private function createTables(): void
    {
        $this->pdo->exec(
            'CREATE TABLE branchable_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                price TEXT NOT NULL,
                internal_note TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )'
        );

        $historyTable = (new MockBranchableRecord())->historyTable();
        $statements = TemporalManager::createHistoryTableSql($historyTable, 'sqlite', false, true);
        foreach ($statements as $statement) {
            $this->pdo->exec($statement);
        }

        $this->pdo->exec(
            'CREATE TABLE temporal_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                status TEXT NOT NULL,
                body TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )'
        );

        $temporalHistoryTable = (new MockTemporalRecord())->historyTable();
        $temporalStatements = TemporalManager::createHistoryTableSql($temporalHistoryTable, 'sqlite', true, false);
        foreach ($temporalStatements as $statement) {
            $this->pdo->exec($statement);
        }
    }

    private function setupDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);

        $this->setStaticProperty(Database::class, 'connections', [
            'default' => $this->pdo,
            'sqlite' => $this->pdo,
        ]);
    }

    private function tearDownDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function setStaticProperty(string $className, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass($className);
        $property = $reflection->getProperty($propertyName);
        $property->setValue(null, $value);
    }

    private function registerTemporalHooks(string $class): void
    {
        $model = new $class();
        $register = \Closure::bind(function (): void {
            $this->registerTemporalHooks();
        }, $model, $class);

        $register();
    }

    private function travelTo(string $datetime): void
    {
        Carbon::setTestNow(Carbon::parse($datetime, 'UTC'));
    }
}
