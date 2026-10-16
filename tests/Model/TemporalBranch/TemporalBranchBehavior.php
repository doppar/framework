<?php

namespace Tests\Unit\Model\TemporalBranch;

require_once __DIR__ . '/../../Support/Model/MockBranchableRecord.php';
require_once __DIR__ . '/../../Support/Model/MockTemporalRecord.php';

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\After;
use Phaseolies\Database\Entity\Hooks\HookHandler;
use Phaseolies\Database\Temporal\TemporalManager;
use RuntimeException;
use Tests\Support\Database\ModelQueryDriverTestCase;
use Tests\Support\Model\MockBranchableRecord;
use Tests\Support\Model\MockTemporalRecord;

abstract class TemporalBranchTest extends ModelQueryDriverTestCase
{
    protected function tableDefinitions(): array
    {
        return [
            'branchable_records' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'price', 'type' => 'string'],
                ['name' => 'internal_note', 'type' => 'text', 'nullable' => true],
                ['name' => 'metadata', 'type' => 'text', 'nullable' => true],
                ['name' => 'enabled', 'type' => 'boolean', 'nullable' => true],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true],
                ['name' => 'updated_at', 'type' => 'datetime', 'nullable' => true],
            ],
            'temporal_records' => [
                ['name' => 'id', 'type' => 'id'],
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'status', 'type' => 'string'],
                ['name' => 'body', 'type' => 'text'],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true],
                ['name' => 'updated_at', 'type' => 'datetime', 'nullable' => true],
            ],
        ];
    }

    protected function seedData(): array
    {
        return [];
    }

    /**
     * History tables are rebuilt for every test: some tests replace them with
     * an unmigrated (branch-less) shape, and the DDL under test is per-driver.
     */
    protected function resetTables(): void
    {
        parent::resetTables();

        $this->recreateHistoryTable((new MockBranchableRecord())->historyTable(), false, true);
        $this->recreateHistoryTable((new MockTemporalRecord())->historyTable(), true, false);
    }

    protected function setupDatabaseConnections(): void
    {
        parent::setupDatabaseConnections();

        // Hooks register once per class on first construction, so boot the
        // classes first, then clear the registry and register them exactly once.
        $branchable = new MockBranchableRecord();
        $temporal = new MockTemporalRecord();
        HookHandler::$hooks = [];
        TemporalManager::resetCache();
        \Closure::bind(fn() => $this->registerTemporalHooks(), $branchable, MockBranchableRecord::class)();
        \Closure::bind(fn() => $this->registerTemporalHooks(), $temporal, MockTemporalRecord::class)();

        Carbon::setTestNow();
    }

    #[After]
    protected function resetTemporalState(): void
    {
        Carbon::setTestNow();
        HookHandler::$hooks = [];
        TemporalManager::resetCache();
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

    public function testBranchNameValidationAndDuplicateForkGuard(): void
    {
        $this->travelTo('2026-02-01 10:00:00.000000');
        $created = MockBranchableRecord::create(['title' => 'Widget', 'price' => '100', 'internal_note' => null]);
        $record = MockBranchableRecord::find($created->id);

        foreach ([str_repeat('a', 51), '', 'has space', "quote'd"] as $invalid) {
            try {
                $record->branch($invalid, '2026-02-01 10:00:00');
                $this->fail("Expected branch name [{$invalid}] to be rejected.");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        // Read paths validate the name too, before it reaches the database.
        $this->expectInvalidBranchName(fn() => MockBranchableRecord::at('2026-02-01 10:00:00', str_repeat('a', 51)));
        $this->expectInvalidBranchName(fn() => $record->history(str_repeat('a', 51)));
        $this->expectInvalidBranchName(fn() => $record->diff('2026-02-01', '2026-02-02', 'bad name'));

        $this->assertTrue($record->branch(str_repeat('a', 50), '2026-02-01 10:00:00'));
        $this->assertTrue($record->branch('proposal', '2026-02-01 10:00:00'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $record->branch('proposal', '2026-02-01 10:00:00');
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

    public function testBranchOperationsRequireTheMigratedBranchColumn(): void
    {
        $this->recreateHistoryTable((new MockBranchableRecord())->historyTable(), false, false);
        $this->pdo->exec("INSERT INTO branchable_records (title, price) VALUES ('Widget', '100')");
        $record = MockBranchableRecord::find(1);
        TemporalManager::resetCache();

        $this->assertMigrationGuard(fn() => MockBranchableRecord::at('2026-02-01 10:00:00')->find(1));
        $this->assertMigrationGuard(fn() => MockBranchableRecord::at('2026-02-01 10:00:00', 'proposal')->get());
        $this->assertMigrationGuard(fn() => $record->branch('proposal', '2026-02-01 10:00:00'));
        $this->assertMigrationGuard(fn() => $record->history());
        $this->assertMigrationGuard(fn() => $record->diff('2026-02-01', '2026-02-02'));
        $this->assertMigrationGuard(function () use ($record): void {
            $record->title = 'Renamed';
            $record->save();
        });
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

    public function testDiffDetectsArrayAndBooleanCastChanges(): void
    {
        $this->travelTo('2026-02-01 10:00:00.000000');
        $created = MockBranchableRecord::create([
            'title' => 'Widget', 'price' => '100', 'internal_note' => null,
            'metadata' => ['color' => 'blue'], 'enabled' => false,
        ]);
        $record = MockBranchableRecord::find($created->id);

        $this->travelTo('2026-02-01 11:00:00.000000');
        $record->metadata = ['color' => 'red'];
        $record->enabled = true;
        $record->save();

        $diff = $record->diff('2026-02-01 10:30:00', '2026-02-01 11:30:00');

        $this->assertSame(['color' => 'blue'], $diff['changes']['metadata']['from']);
        $this->assertSame(['color' => 'red'], $diff['changes']['metadata']['to']);
        $this->assertFalse($diff['changes']['enabled']['from']);
        $this->assertTrue($diff['changes']['enabled']['to']);
        $this->assertArrayNotHasKey('title', $diff['changes']);
    }

    public function testBranchCanBeUpdatedDiffedAndRestoredByHistoryId(): void
    {
        $this->travelTo('2026-02-01 10:00:00.000000');
        $created = MockBranchableRecord::create(['title' => 'Widget', 'price' => '100', 'internal_note' => null]);
        $record = MockBranchableRecord::find($created->id);

        $this->travelTo('2026-02-01 11:00:00.000000');
        $record->branch('proposal', '2026-02-01 10:30:00');

        $proposal = MockBranchableRecord::at('2026-02-01 11:30:00', 'proposal')->find($record->id);
        $proposal->price = '140';
        $this->assertTrue($proposal->saveToBranch('proposal'));

        // Writing to the branch never touches the live row or main history.
        $this->assertSame(100, MockBranchableRecord::find($record->id)->price);
        $this->assertCount(1, $record->history());

        $diff = $record->diffAgainstBranch('proposal');
        $this->assertSame(100, $diff['changes']['price']['from']);
        $this->assertSame(140, $diff['changes']['price']['to']);

        $branchHistory = $record->history('proposal');
        $this->assertSame(['branched', 'updated'], $branchHistory->map->__action->all());

        $this->travelTo('2026-02-01 12:00:00.000000');
        $this->assertTrue($record->restoreVersion($branchHistory[1]->__history_id));
        $this->assertSame(140, MockBranchableRecord::find($record->id)->price);
        $this->assertSame(['created', 'updated'], $record->history()->map->__action->all());
    }

    public function testSaveToBranchRequiresAnExistingBranch(): void
    {
        $created = MockBranchableRecord::create(['title' => 'Widget', 'price' => '100', 'internal_note' => null]);
        $record = MockBranchableRecord::find($created->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not exist');
        $record->saveToBranch('never-forked');
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

    public function testRestoreVersionRejectsDeletedHistoryEntries(): void
    {
        $created = MockBranchableRecord::create(['title' => 'Widget', 'price' => '100', 'internal_note' => null]);
        $record = MockBranchableRecord::find($created->id);
        $record->delete();
        $deleted = $record->history()[1];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('deleted temporal version');
        $record->restoreVersion($deleted->__history_id);
    }

    public function testRestoreToRefusesAPointWhereTheRecordWasDeleted(): void
    {
        $this->travelTo('2026-02-01 10:00:00.000000');
        $created = MockBranchableRecord::create(['title' => 'Widget', 'price' => '100', 'internal_note' => null]);
        $record = MockBranchableRecord::find($created->id);

        $this->travelTo('2026-02-01 11:00:00.000000');
        $record->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('deleted temporal version');
        $record->restoreTo('2026-02-01 11:30:00');
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

    public function testSameMicrosecondVersionsResolveToTheLatestHistoryId(): void
    {
        $this->travelTo('2026-02-01 10:00:00.000000');
        $created = MockBranchableRecord::create(['title' => 'Widget', 'price' => '100', 'internal_note' => null]);
        $record = MockBranchableRecord::find($created->id);

        // Same frozen clock: both updates share one valid_from value.
        $record->price = '110';
        $record->save();
        $record->price = '120';
        $record->save();

        $this->assertSame(120, MockBranchableRecord::at('2026-02-01 10:00:00')->find($record->id)->price);
        $this->assertSame(120, MockBranchableRecord::at('2026-02-01 10:00:00')->get()->first()->price);
        $this->assertSame(120, $record->rewindTo('2026-02-01 10:00:00')->price);
    }

    public function testHasBranchColumnSeesAColumnAddedAfterANegativeCheck(): void
    {
        $table = 'migration_check_history';
        $driver = static::driverName();

        $this->recreateHistoryTable($table, false, false);

        $this->assertFalse(TemporalManager::hasBranchColumn($this->pdo, $table, $driver));

        // No resetCache(): a missing column must not be cached, so long-running
        // workers see the column as soon as migrate:temporal adds it.
        $this->pdo->exec(sprintf(
            "ALTER TABLE %s ADD COLUMN branch VARCHAR(50) NOT NULL DEFAULT 'main'",
            $this->quoteIdentifier($table)
        ));

        $this->assertTrue(TemporalManager::hasBranchColumn($this->pdo, $table, $driver));

        $this->dropTablesIfExist([$table]);
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

    private function recreateHistoryTable(string $table, bool $trackActor, bool $trackBranches): void
    {
        $this->dropTablesIfExist([$table]);

        foreach (TemporalManager::createHistoryTableSql($table, static::driverName(), $trackActor, $trackBranches) as $sql) {
            $this->pdo->exec($sql);
        }
    }

    private function travelTo(string $datetime): void
    {
        Carbon::setTestNow(Carbon::parse($datetime, 'UTC'));
    }

    private function assertMigrationGuard(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected missing branch column to require migrate:temporal.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('run migrate:temporal', $exception->getMessage());
        }
    }

    private function expectInvalidBranchName(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected an invalid branch name to be rejected.');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }
}
