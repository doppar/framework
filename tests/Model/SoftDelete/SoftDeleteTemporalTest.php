<?php

namespace Tests\Unit\Model\SoftDelete;

use Carbon\Carbon;
use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Hooks\HookHandler;
use Phaseolies\Database\Temporal\TemporalManager;
use Phaseolies\DI\Container;
use Tests\Support\MockContainer;
use Tests\Support\Model\MockSoftTemporalRecord;

class SoftDeleteTemporalTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE soft_temporal_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )'
        );

        foreach (TemporalManager::createHistoryTableSql('soft_temporal_records_history', 'sqlite', true) as $statement) {
            $this->pdo->exec($statement);
        }

        $this->setConnections(['default' => $this->pdo, 'sqlite' => $this->pdo]);

        // Hooks register once per class on first construction, so boot the
        // class first, then clear the registry and register them exactly once.
        $model = new MockSoftTemporalRecord();
        HookHandler::$hooks = [];
        TemporalManager::resetCache();
        \Closure::bind(fn() => $this->registerTemporalHooks(), $model, MockSoftTemporalRecord::class)();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        HookHandler::$hooks = [];
        TemporalManager::resetCache();
        $this->setConnections([]);
        Container::forgetInstance();
        $this->pdo = null;
    }

    public function testSoftDeleteAndRestoreAreRecordedInHistory(): void
    {
        $this->travelTo('2026-01-01 10:00:00');
        $record = MockSoftTemporalRecord::create(['title' => 'Draft']);

        $this->travelTo('2026-01-02 10:00:00');
        $record->delete();

        $this->travelTo('2026-01-03 10:00:00');
        $record->restore();

        $history = $record->history();
        $actions = [];
        foreach ($history as $entry) {
            $actions[] = $entry->__action;
        }

        $this->assertSame(['created', 'deleted', 'restored'], $actions);
        $this->assertNotNull($history[1]->deleted_at);
        $this->assertNull($history[2]->deleted_at);
    }

    public function testTimeTravelSeesTheSoftDeleteWindow(): void
    {
        $this->travelTo('2026-01-01 10:00:00');
        $record = MockSoftTemporalRecord::create(['title' => 'Draft']);

        $this->travelTo('2026-01-02 10:00:00');
        $record->delete();

        $this->travelTo('2026-01-03 10:00:00');
        $record->restore();

        $this->assertCount(1, MockSoftTemporalRecord::at('2026-01-01 12:00:00')->get());
        $this->assertCount(0, MockSoftTemporalRecord::at('2026-01-02 12:00:00')->get());
        $this->assertCount(1, MockSoftTemporalRecord::at('2026-01-03 12:00:00')->get());
    }

    public function testRestoreToBeforeTheSoftDeleteBringsTheRowBack(): void
    {
        $this->travelTo('2026-01-01 10:00:00');
        $record = MockSoftTemporalRecord::create(['title' => 'Draft']);

        $this->travelTo('2026-01-02 10:00:00');
        $record->delete();
        $this->assertNull(MockSoftTemporalRecord::find($record->id));

        $this->travelTo('2026-01-03 10:00:00');
        $trashed = MockSoftTemporalRecord::withTrashed()->find($record->id);

        $this->assertTrue($trashed->restoreTo('2026-01-01 12:00:00'));

        $restored = MockSoftTemporalRecord::find($record->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertSame('restored', $restored->history()->last()->__action);
    }

    public function testRestoreToALiveStateLeavesALiveRecordAlone(): void
    {
        $this->travelTo('2026-01-01 10:00:00');
        $record = MockSoftTemporalRecord::create(['title' => 'Draft']);

        $this->travelTo('2026-01-02 10:00:00');
        $live = MockSoftTemporalRecord::find($record->id);
        $live->title = 'Final';
        $live->save();

        $this->travelTo('2026-01-03 10:00:00');
        $this->assertTrue(MockSoftTemporalRecord::find($record->id)->restoreTo('2026-01-01 12:00:00'));

        $current = MockSoftTemporalRecord::find($record->id);
        $this->assertSame('Draft', $current->title);
        $this->assertNull($current->deleted_at);
    }

    private function travelTo(string $datetime): void
    {
        Carbon::setTestNow(Carbon::parse($datetime, 'UTC'));
    }

    private function setConnections(array $connections): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $reflection->getProperty('connections')->setValue(null, $connections);
        $reflection->getProperty('transactions')->setValue(null, []);
    }
}
