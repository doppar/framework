<?php

namespace Tests\Unit\Model;

require_once __DIR__ . '/../Support/Model/MockTemporalRecord.php';
require_once __DIR__ . '/../Support/Model/MockTemporalDefaultRecord.php';
require_once __DIR__ . '/../Support/Model/MockUser.php';
require_once __DIR__ . '/../Support/MockContainer.php';

use Carbon\Carbon;
use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Auth\ActorManager;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Hooks\HookHandler;
use Phaseolies\Database\Temporal\TemporalManager;
use Phaseolies\DI\Container;
use RuntimeException;
use Tests\Support\MockContainer;
use Tests\Support\Model\MockTemporalDefaultRecord;
use Tests\Support\Model\MockTemporalRecord;
use Tests\Support\Model\MockUser;

class TemporalModelTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?MockContainer $container = null;

    protected function setUp(): void
    {
        $this->container = new MockContainer();
        Container::setInstance($this->container);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTemporalTables();
        $this->setupDatabaseConnections();

        HookHandler::$hooks = [];
        TemporalManager::resetCache();
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

    public function testTemporalModelUsesConfiguredMetadataAndStoresCreatedSnapshot(): void
    {
        $this->travelTo('2026-01-01 10:00:00.100000');

        $created = MockTemporalRecord::create([
            'title' => 'Contract A',
            'status' => 'draft',
            'body' => 'Original body',
        ]);

        $record = MockTemporalRecord::find($created->id);
        $history = $record->history();

        $this->assertTrue($record->isTemporal());
        $this->assertSame('temporal_records_audit', $record->historyTable());
        $this->assertCount(1, $history);
        $this->assertSame('created', $history[0]->__action);
        $this->assertSame($record->id, $history[0]->id);
        $this->assertSame('draft', $history[0]->status);
        $this->assertNull($history[0]->__changed_cols);
        $this->assertNull($history[0]->__actor);
    }

    public function testTemporalHistoryTracksUpdatesAndDeletesChronologically(): void
    {
        $this->travelTo('2026-01-01 10:00:00.100000');
        $created = MockTemporalRecord::create([
            'title' => 'Contract A',
            'status' => 'draft',
            'body' => 'Original body',
        ]);

        $record = MockTemporalRecord::find($created->id);

        $this->travelTo('2026-01-01 11:00:00.200000');
        $record->status = 'published';
        $record->body = 'Updated body';
        $record->save();

        $this->travelTo('2026-01-01 12:00:00.300000');
        $record->delete();

        $history = $record->history();

        $this->assertCount(3, $history);
        $this->assertSame(['created', 'updated', 'deleted'], $history->map->__action->all());
        $this->assertSame(['status', 'body'], $history[1]->__changed_cols);
        $this->assertSame('published', $history[1]->status);
        $this->assertSame('Updated body', $history[2]->body);
    }

    public function testTemporalHistoryStoresActorWhenAuthenticatedActorExists(): void
    {
        $actorManager = new class extends ActorManager {
            public function user(): object
            {
                return new class {
                    public function getKey(): int
                    {
                        return 77;
                    }
                };
            }
        };

        $this->container->instance(ActorManager::class, $actorManager);

        $this->travelTo('2026-01-01 10:00:00.100000');
        $created = MockTemporalRecord::create([
            'title' => 'Contract A',
            'status' => 'draft',
            'body' => 'Original body',
        ]);

        $record = MockTemporalRecord::find($created->id);
        $history = $record->history();

        $this->assertCount(1, $history);
        $this->assertSame('77', (string) $history[0]->__actor);
    }

    public function testTemporalAtQueriesReturnPointInTimeStateAndExcludeDeletedRecords(): void
    {
        $this->travelTo('2026-01-01 10:00:00.100000');
        $first = MockTemporalRecord::create([
            'title' => 'Contract A',
            'status' => 'draft',
            'body' => 'Body A',
        ]);

        $this->travelTo('2026-01-01 11:00:00.200000');
        $second = MockTemporalRecord::create([
            'title' => 'Contract B',
            'status' => 'archived',
            'body' => 'Body B',
        ]);

        $firstRecord = MockTemporalRecord::find($first->id);
        $secondRecord = MockTemporalRecord::find($second->id);

        $this->travelTo('2026-01-01 12:00:00.300000');
        $firstRecord->status = 'published';
        $firstRecord->save();

        $this->travelTo('2026-01-01 13:00:00.400000');
        $secondRecord->delete();

        $beforeDelete = MockTemporalRecord::at('2026-01-01 11:30:00')->orderBy('id')->get();
        $duringPublish = MockTemporalRecord::at('2026-01-01 12:30:00')
            ->where('status', 'published')
            ->first();
        $afterDelete = MockTemporalRecord::at('2026-01-01 14:00:00')->orderBy('id')->get();

        $this->assertCount(2, $beforeDelete);
        $this->assertSame(['draft', 'archived'], $beforeDelete->map->status->all());
        $this->assertNotNull($duringPublish);
        $this->assertSame($first->id, $duringPublish->id);
        $this->assertSame('published', $duringPublish->status);
        $this->assertCount(1, $afterDelete);
        $this->assertSame($first->id, $afterDelete[0]->id);

        // A record deleted before the requested time did not exist at that time
        $this->assertNull(MockTemporalRecord::at('2026-01-01 14:00:00')->find($second->id));

        // ...but it did exist before it was deleted
        $beforeDeleteSnapshot = MockTemporalRecord::at('2026-01-01 12:30:00')->find($second->id);

        $this->assertNotNull($beforeDeleteSnapshot);
        $this->assertSame($second->id, $beforeDeleteSnapshot->id);
        $this->assertSame('archived', $beforeDeleteSnapshot->status);
    }

    public function testTemporalLookupsAfterDeletionDoNotReturnTheOldSnapshot(): void
    {
        $this->travelTo('2026-01-01 10:00:00.100000');
        $created = MockTemporalRecord::create(['title' => 'Contract A', 'status' => 'draft', 'body' => 'Body']);
        $record = MockTemporalRecord::find($created->id);

        $this->travelTo('2026-01-01 11:00:00.200000');
        $record->status = 'published';
        $record->save();

        $this->travelTo('2026-01-01 12:00:00.300000');
        $record->delete();

        $afterDelete = '2026-01-01 13:00:00';
        $beforeDelete = '2026-01-01 11:30:00';

        $this->assertNull(MockTemporalRecord::at($afterDelete)->find($record->id));
        $this->assertNull($record->rewindTo($afterDelete));
        $this->assertFalse($record->restoreTo($afterDelete));
        $this->assertNull($record->diff($beforeDelete, $afterDelete));
        $this->assertNull($record->diff($afterDelete, $afterDelete));

        // Times before the deletion still resolve
        $this->assertSame('published', $record->rewindTo($beforeDelete)->status);
        $this->assertSame('published', MockTemporalRecord::at($beforeDelete)->find($record->id)->status);
        $this->assertSame('draft', MockTemporalRecord::at('2026-01-01 10:30:00')->find($record->id)->status);
    }

    public function testTemporalLookupsSeeTheRecordAgainAfterARestoredEntry(): void
    {
        $this->travelTo('2026-01-01 10:00:00.100000');
        $created = MockTemporalRecord::create(['title' => 'Contract A', 'status' => 'draft', 'body' => 'Body']);
        $record = MockTemporalRecord::find($created->id);

        $this->travelTo('2026-01-01 12:00:00.300000');
        $record->delete();

        // Add a 'restored' entry the way a soft-delete restore would
        $history = $record->historyTable();
        $row = $this->pdo->query("SELECT * FROM {$history} WHERE action = 'deleted'")->fetch(PDO::FETCH_ASSOC);
        $columns = $this->pdo->query("PRAGMA table_info({$history})")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            if ((int) $column['pk'] === 1 && $column['type'] === 'INTEGER' && array_key_exists($column['name'], $row)) {
                // autoincrement key of the history table, not the record id
                if ($column['name'] !== 'record_id') {
                    unset($row[$column['name']]);
                }
            }
        }

        $row['action'] = 'restored';
        $row['valid_from'] = '2026-01-01 14:00:00.000000';

        $names = array_keys($row);
        $this->pdo->prepare(
            "INSERT INTO {$history} (" . implode(',', $names) . ') VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')'
        )->execute(array_values($row));

        $this->assertNull(MockTemporalRecord::at('2026-01-01 13:00:00')->find($record->id));
        $this->assertNotNull(MockTemporalRecord::at('2026-01-01 15:00:00')->find($record->id));
        $this->assertNotNull($record->rewindTo('2026-01-01 15:00:00'));
    }

    public function testTemporalDiffRewindAndRestoreUseStoredSnapshots(): void
    {
        $this->travelTo('2026-01-01 10:00:00.100000');
        $created = MockTemporalRecord::create([
            'title' => 'Contract A',
            'status' => 'draft',
            'body' => 'Original body',
        ]);

        $record = MockTemporalRecord::find($created->id);

        $this->travelTo('2026-01-01 11:00:00.200000');
        $record->status = 'published';
        $record->body = 'Updated body';
        $record->save();

        $diff = $record->diff('2026-01-01 10:30:00', '2026-01-01 11:30:00');
        $rewound = $record->rewindTo('2026-01-01 10:30:00');

        $this->assertNotNull($diff);
        $this->assertSame('draft', $diff['changes']['status']['from']);
        $this->assertSame('published', $diff['changes']['status']['to']);
        $this->assertSame('Original body', $diff['changes']['body']['from']);
        $this->assertSame('Updated body', $diff['changes']['body']['to']);
        $this->assertNotNull($rewound);
        $this->assertSame($record->id, $rewound->id);
        $this->assertSame('draft', $rewound->status);
        $this->assertSame('Original body', $rewound->body);

        $this->travelTo('2026-01-01 12:00:00.300000');
        $this->assertTrue($record->restoreTo('2026-01-01 10:30:00'));

        $fresh = MockTemporalRecord::find($record->id);

        $this->assertSame('draft', $fresh->status);
        $this->assertSame('Original body', $fresh->body);
        $this->assertCount(3, $fresh->history());
    }

    public function testTemporalDiffRewindAndRestoreHandleMissingSnapshots(): void
    {
        $this->travelTo('2026-01-01 10:00:00.100000');
        $created = MockTemporalRecord::create([
            'title' => 'Contract A',
            'status' => 'draft',
            'body' => 'Original body',
        ]);

        $record = MockTemporalRecord::find($created->id);

        $this->assertNull($record->diff('2025-12-31 23:59:59', '2026-01-01 10:30:00'));
        $this->assertNull($record->rewindTo('2025-12-31 23:59:59'));
        $this->assertFalse($record->restoreTo('2025-12-31 23:59:59'));
    }

    public function testTemporalAtNormalizesDateOnlyInputToEndOfDay(): void
    {
        $this->travelTo('2026-01-01 10:00:00.100000');
        $created = MockTemporalRecord::create([
            'title' => 'Contract A',
            'status' => 'draft',
            'body' => 'Original body',
        ]);

        $record = MockTemporalRecord::find($created->id);

        $this->travelTo('2026-01-01 18:00:00.200000');
        $record->status = 'approved';
        $record->save();

        $snapshot = MockTemporalRecord::at('2026-01-01')->find($record->id);

        $this->assertNotNull($snapshot);
        $this->assertSame('approved', $snapshot->status);
    }

    public function testHistoryThrowsForUnsavedTemporalModels(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot call temporal methods on an unsaved');

        (new MockTemporalRecord())->history();
    }

    public function testAtThrowsForNonTemporalModels(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not marked #[Temporal]');

        MockUser::at('2026-01-01 10:00:00');
    }

    public function testNonTemporalInstanceMethodsThrowAcrossTemporalApi(): void
    {
        $model = new MockUser(['id' => 1, 'name' => 'John']);

        foreach ([
            fn() => $model->history(),
            fn() => $model->diff('2026-01-01 10:00:00', '2026-01-01 11:00:00'),
            fn() => $model->rewindTo('2026-01-01 10:00:00'),
            fn() => $model->restoreTo('2026-01-01 10:00:00'),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected a RuntimeException for non-temporal model usage.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('is not marked #[Temporal]', $e->getMessage());
            }
        }
    }

    public function testTemporalBuilderSupportsAdditionalOperatorsAndPagination(): void
    {
        $this->travelTo('2026-01-01 10:00:00.100000');
        MockTemporalRecord::create([
            'title' => 'Alpha Contract',
            'status' => 'draft',
            'body' => 'A body',
        ]);

        $this->travelTo('2026-01-01 10:01:00.100000');
        MockTemporalRecord::create([
            'title' => 'Beta Contract',
            'status' => 'approved',
            'body' => 'B body',
        ]);

        $this->travelTo('2026-01-01 10:02:00.100000');
        MockTemporalRecord::create([
            'title' => 'Gamma Memo',
            'status' => 'archived',
            'body' => 'C body',
        ]);

        $filtered = MockTemporalRecord::at('2026-01-01 10:05:00')
            ->whereIn('status', ['draft', 'approved'])
            ->whereBetween('id', [1, 2])
            ->whereLike('title', '%Contract%')
            ->orderBy('id')
            ->get();

        $paged = MockTemporalRecord::at('2026-01-01 10:05:00')
            ->orderBy('id')
            ->offset(1)
            ->limit(1)
            ->get();

        $orMatch = MockTemporalRecord::at('2026-01-01 10:05:00')
            ->where('status', 'draft')
            ->orWhere('status', 'archived')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $filtered);
        $this->assertSame(['Alpha Contract', 'Beta Contract'], $filtered->map->title->all());
        $this->assertCount(1, $paged);
        $this->assertSame('Beta Contract', $paged[0]->title);
        $this->assertCount(2, $orMatch);
        $this->assertSame(['Alpha Contract', 'Gamma Memo'], $orMatch->map->title->all());
    }

    public function testTemporalBuilderNormalizesMinuteAndSecondPrecisionInputs(): void
    {
        $minute = MockTemporalRecord::at('2026-01-01 12:34');
        $second = MockTemporalRecord::at('2026-01-01 12:34:56');

        $this->assertSame('2026-01-01 12:34:59.999999', $minute->getDatetime());
        $this->assertSame('2026-01-01 12:34:56.999999', $second->getDatetime());
    }

    public function testTemporalDefaultSuffixUsesHistoryTableName(): void
    {
        $record = new MockTemporalDefaultRecord();

        $this->assertTrue($record->isTemporal());
        $this->assertSame('temporal_default_records_history', $record->historyTable());
    }

    public function testTemporalManagerCreatesExpectedDdlForEachDriver(): void
    {
        $mysql = TemporalManager::createHistoryTableSql('contracts_history', 'mysql', true);
        $pgsql = TemporalManager::createHistoryTableSql('contracts_history', 'pgsql', true);
        $sqlite = TemporalManager::createHistoryTableSql('contracts_history', 'sqlite', false);

        $this->assertCount(1, $mysql);
        $this->assertStringContainsString('`snapshot`     JSON', $mysql[0]);
        $this->assertStringContainsString('`actor` VARCHAR(255) NULL', $mysql[0]);

        $this->assertCount(3, $pgsql);
        $this->assertStringContainsString('snapshot     JSONB', $pgsql[0]);
        $this->assertStringContainsString('actor VARCHAR(255) NULL', $pgsql[0]);
        $this->assertStringContainsString('CREATE INDEX IF NOT EXISTS', $pgsql[1]);

        $this->assertCount(3, $sqlite);
        $this->assertStringContainsString('snapshot     TEXT', $sqlite[0]);
        $this->assertStringNotContainsString('actor TEXT NULL', $sqlite[0]);
        $this->assertStringContainsString('CREATE INDEX IF NOT EXISTS', $sqlite[1]);
    }

    private function createTemporalTables(): void
    {
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

        $historyTable = (new MockTemporalRecord())->historyTable();
        $statements = TemporalManager::createHistoryTableSql($historyTable, 'sqlite', true);

        foreach ($statements as $statement) {
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

    public function testDiffDetectsChangesInArrayValues(): void
    {
        $changes = $this->diffBetween(['tags' => ['a']], ['tags' => ['b']]);

        $this->assertSame(['tags'], array_keys($changes));
        $this->assertSame(['a'], $changes['tags']['from']);
        $this->assertSame(['b'], $changes['tags']['to']);
    }

    public function testDiffDetectsAddedAndRemovedArrayItemsAndNestedChanges(): void
    {
        $this->assertArrayHasKey('t', $this->diffBetween(['t' => ['a']], ['t' => ['a', 'b']]));
        $this->assertArrayHasKey('t', $this->diffBetween(['t' => ['a', 'b']], ['t' => ['a']]));
        $this->assertArrayHasKey('t', $this->diffBetween(['t' => ['k' => ['x' => 1]]], ['t' => ['k' => ['x' => 2]]]));
        $this->assertArrayHasKey('t', $this->diffBetween(['t' => []], ['t' => ['a']]));
        $this->assertArrayHasKey('t', $this->diffBetween(['t' => ['a']], ['t' => 'a']));
    }

    public function testDiffIgnoresEqualArraysWithoutWarnings(): void
    {
        $warnings = [];
        set_error_handler(function (int $no, string $message) use (&$warnings) {
            $warnings[] = $message;

            return true;
        });

        try {
            $changes = $this->diffBetween(
                ['t' => ['a', 'b'], 'o' => ['x' => 1, 'y' => ['z' => true]]],
                ['t' => ['a', 'b'], 'o' => ['y' => ['z' => true], 'x' => 1]]
            );
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $changes);
        $this->assertSame([], $warnings);
    }

    public function testDiffKeepsNullFalseAndEmptyStringApart(): void
    {
        $this->assertArrayHasKey('v', $this->diffBetween(['v' => null], ['v' => '']));
        $this->assertArrayHasKey('v', $this->diffBetween(['v' => false], ['v' => null]));
        $this->assertArrayHasKey('v', $this->diffBetween(['v' => false], ['v' => '']));
        $this->assertArrayHasKey('v', $this->diffBetween(['v' => null], ['v' => 0]));
        $this->assertArrayHasKey('v', $this->diffBetween(['v' => true], ['v' => false]));
        $this->assertSame([], $this->diffBetween(['v' => null], ['v' => null]));
    }

    public function testDiffTreatsDatabaseStyleScalarsAsEqual(): void
    {
        $this->assertSame([], $this->diffBetween(['n' => 1], ['n' => '1']));
        $this->assertSame([], $this->diffBetween(['n' => true], ['n' => 1]));
        $this->assertSame([], $this->diffBetween(['n' => false], ['n' => '0']));
        $this->assertSame([], $this->diffBetween(['n' => 1.0], ['n' => 1]));
        $this->assertArrayHasKey('n', $this->diffBetween(['n' => 1], ['n' => 2]));
    }

    public function testDiffStillReportsAddedAndRemovedColumns(): void
    {
        $diff = $this->diffReport(['a' => 1], ['a' => 1, 'b' => ['x']]);

        $this->assertSame(['b' => ['x']], $diff['added']);
        $this->assertSame([], $diff['removed']);

        $diff = $this->diffReport(['a' => 1, 'b' => 2], ['a' => 1]);

        $this->assertSame(['b' => 2], $diff['removed']);
    }

    private function diffBetween(array $from, array $to): array
    {
        return $this->diffReport($from, $to)['changes'];
    }

    /**
     * Store two hand-written snapshots for one record and diff them.
     */
    private function diffReport(array $from, array $to): array
    {
        $this->travelTo('2026-02-01 10:00:00.100000');
        $created = MockTemporalRecord::create(['title' => 'T', 'status' => 'a', 'body' => 'b']);
        $record = MockTemporalRecord::find($created->id);

        $history = $record->historyTable();
        $this->pdo->exec("DELETE FROM {$history}");

        foreach ([[$from, '2026-02-01 10:00:00.000000'], [$to, '2026-02-01 11:00:00.000000']] as [$snapshot, $at]) {
            $this->pdo
                ->prepare("INSERT INTO {$history} (record_id, action, valid_from, snapshot) VALUES (?, 'updated', ?, ?)")
                ->execute([$record->id, $at, json_encode($snapshot)]);
        }

        $report = $record->diff('2026-02-01 10:30:00', '2026-02-01 12:00:00');
        $this->pdo->exec('DELETE FROM temporal_records');

        return $report;
    }

    public function testTemporalOrWhereAsFirstConditionOnlyReturnsMatches(): void
    {
        $at = $this->seedForBooleanTests();

        $this->assertSame(['B', 'D'], $this->titlesOf(MockTemporalRecord::at($at)->orWhere('status', 'closed')));
        $this->assertSame(['C'], $this->titlesOf(MockTemporalRecord::at($at)->orWhereIn('status', ['draft'])));
        $this->assertSame([], $this->titlesOf(MockTemporalRecord::at($at)->orWhere('status', 'nothing')));
    }

    public function testTemporalWhereAndOrWhereMatchSqlSemantics(): void
    {
        $at = $this->seedForBooleanTests();

        $this->assertSame(
            ['A', 'B', 'D'],
            $this->titlesOf(MockTemporalRecord::at($at)->where('status', 'open')->orWhere('status', 'closed'))
        );
        $this->assertSame(
            ['A', 'D'],
            $this->titlesOf(MockTemporalRecord::at($at)->where('status', 'closed')->where('body', '1')->orWhere('status', 'open'))
        );
    }

    public function testTemporalAndBindsTighterThanOr(): void
    {
        $at = $this->seedForBooleanTests();

        // open OR (closed AND body = 2), not (open OR closed) AND body = 2
        $this->assertSame(
            ['A', 'B'],
            $this->titlesOf(
                MockTemporalRecord::at($at)->where('status', 'open')->orWhere('status', 'closed')->where('body', '2')
            )
        );
    }

    public function testTemporalIgnoresTheBooleanOfTheFirstCondition(): void
    {
        $at = $this->seedForBooleanTests();

        // orWhere(closed) AND body = 1
        $this->assertSame(
            ['D'],
            $this->titlesOf(MockTemporalRecord::at($at)->orWhere('status', 'closed')->where('body', '1'))
        );
    }

    public function testTemporalNestedWhereGroups(): void
    {
        $at = $this->seedForBooleanTests();

        $this->assertSame(
            ['D'],
            $this->titlesOf(
                MockTemporalRecord::at($at)
                    ->where(function ($query) {
                        $query->where('status', 'closed')->orWhere('status', 'draft');
                    })
                    ->where('body', '1')
            )
        );

        // body = 3 OR (status = closed AND body = 2)
        $this->assertSame(
            ['B', 'C'],
            $this->titlesOf(
                MockTemporalRecord::at($at)
                    ->where('body', '3')
                    ->orWhere(function ($query) {
                        $query->where('status', 'closed')->where('body', '2');
                    })
            )
        );
    }

    public function testTemporalRawConditionsAreRejectedInsteadOfIgnored(): void
    {
        $at = $this->seedForBooleanTests();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('not supported');

        MockTemporalRecord::at($at)->whereRaw("status = 'open'")->get();
    }

    /**
     * A open/1, B closed/2, C draft/3, D closed/1. Returns a time after all of them.
     */
    private function seedForBooleanTests(): string
    {
        $this->travelTo('2026-01-01 10:00:00.100000');

        foreach ([['A', 'open', '1'], ['B', 'closed', '2'], ['C', 'draft', '3'], ['D', 'closed', '1']] as [$title, $status, $body]) {
            MockTemporalRecord::create(['title' => $title, 'status' => $status, 'body' => $body]);
        }

        return '2026-01-01 11:00:00';
    }

    private function titlesOf($query): array
    {
        $titles = $query->orderBy('id')->get()->map->title->all();

        return array_values($titles);
    }

    private function travelTo(string $datetime): void
    {
        Carbon::setTestNow(Carbon::parse($datetime, 'UTC'));
    }
}
