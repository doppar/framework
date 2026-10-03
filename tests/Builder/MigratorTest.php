<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/MigratorTestCase.php';

use Phaseolies\Database\Database;

/**
 * Runs the Migrator against a real SQLite file with real migration files.
 */
class MigratorTest extends MigratorTestCase
{
    public function testMigrationsRunTogetherShareOneBatch(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->createTable('2025_01_01_000002_create_b_table', 'b');

        $ran = $this->migrator->run(self::CONNECTION);

        $this->assertSame(['2025_01_01_000001_create_a_table.php', '2025_01_01_000002_create_b_table.php'], $ran);
        $this->assertSame([1, 1], array_column($this->migrator->status(self::CONNECTION), 'batch'));
        $this->assertTrue($this->hasTable('a') && $this->hasTable('b'));
    }

    public function testStepGivesEveryMigrationItsOwnBatch(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->createTable('2025_01_01_000002_create_b_table', 'b');

        $this->migrator->run(self::CONNECTION, null, ['step' => true]);

        $this->assertSame([1, 2], array_column($this->migrator->status(self::CONNECTION), 'batch'));
    }

    public function testRunIsIdempotent(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');

        $this->migrator->run(self::CONNECTION);

        $this->assertSame([], $this->migrator->run(self::CONNECTION));
    }

    public function testRollbackRevertsOnlyTheLastBatch(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->migrator->run(self::CONNECTION);

        $this->createTable('2025_01_01_000002_create_b_table', 'b');
        $this->createTable('2025_01_01_000003_create_c_table', 'c');
        $this->migrator->run(self::CONNECTION);

        $rolledBack = $this->migrator->rollback(self::CONNECTION);

        $this->assertSame(['2025_01_01_000003_create_c_table.php', '2025_01_01_000002_create_b_table.php'], $rolledBack);
        $this->assertTrue($this->hasTable('a'));
        $this->assertFalse($this->hasTable('b'));
        $this->assertFalse($this->hasTable('c'));
    }

    public function testRollbackStepIgnoresBatchBoundaries(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->createTable('2025_01_01_000002_create_b_table', 'b');
        $this->createTable('2025_01_01_000003_create_c_table', 'c');
        $this->migrator->run(self::CONNECTION);

        $rolledBack = $this->migrator->rollback(self::CONNECTION, ['step' => 2]);

        $this->assertSame(['2025_01_01_000003_create_c_table.php', '2025_01_01_000002_create_b_table.php'], $rolledBack);
        $this->assertTrue($this->hasTable('a'));
        $this->assertFalse($this->hasTable('b'));
    }

    public function testRollbackSpecificBatch(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->migrator->run(self::CONNECTION);
        $this->createTable('2025_01_01_000002_create_b_table', 'b');
        $this->migrator->run(self::CONNECTION);

        $rolledBack = $this->migrator->rollback(self::CONNECTION, ['batch' => 1]);

        $this->assertSame(['2025_01_01_000001_create_a_table.php'], $rolledBack);
        $this->assertFalse($this->hasTable('a'));
        $this->assertTrue($this->hasTable('b'));
    }

    public function testResetRollsBackEverythingAndRunCanStartOver(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->migrator->run(self::CONNECTION);
        $this->createTable('2025_01_01_000002_create_b_table', 'b');
        $this->migrator->run(self::CONNECTION);

        $this->assertCount(2, $this->migrator->reset(self::CONNECTION));
        $this->assertFalse($this->hasTable('a') || $this->hasTable('b'));

        $this->assertCount(2, $this->migrator->run(self::CONNECTION));
        $this->assertTrue($this->hasTable('a') && $this->hasTable('b'));
    }

    public function testRollbackWithNothingToRollbackIsEmpty(): void
    {
        $this->assertSame([], $this->migrator->rollback(self::CONNECTION));
    }

    public function testRollbackAbortsBeforeTouchingAnythingWhenAFileIsMissing(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->createTable('2025_01_01_000002_create_b_table', 'b');
        $this->migrator->run(self::CONNECTION);

        unlink($this->dir . '/migrations/2025_01_01_000001_create_a_table.php');

        try {
            $this->migrator->rollback(self::CONNECTION);
            $this->fail('Expected a RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('2025_01_01_000001_create_a_table.php', $e->getMessage());
        }

        $this->assertTrue($this->hasTable('a') && $this->hasTable('b'));
        $this->assertCount(2, $this->migrator->status(self::CONNECTION));
    }

    public function testPretendReportsSqlAndChangesNothing(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');

        $queries = [];
        $ran = $this->migrator->run(self::CONNECTION, null, [
            'pretend' => true,
            'progress' => function ($event, $name, $info) use (&$queries) {
                if ($event === 'pretend') {
                    $queries = array_merge($queries, array_column($info['queries'], 'sql'));
                }
            },
        ]);

        $this->assertSame(['2025_01_01_000001_create_a_table.php'], $ran);
        $this->assertNotEmpty($queries);
        $this->assertStringContainsString('CREATE TABLE', $queries[0]);
        $this->assertFalse($this->hasTable('a'));
        $this->assertFalse($this->hasTable('migrations'), 'pretend must not create the tracking table');
    }

    public function testPretendRollbackDoesNotRevert(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->migrator->run(self::CONNECTION);

        $queries = [];
        $this->migrator->rollback(self::CONNECTION, [
            'pretend' => true,
            'progress' => function ($event, $name, $info) use (&$queries) {
                $queries = array_merge($queries, array_column($info['queries'] ?? [], 'sql'));
            },
        ]);

        $this->assertStringContainsString('DROP TABLE', $queries[0]);
        $this->assertTrue($this->hasTable('a'));
        $this->assertCount(1, array_filter(array_column($this->migrator->status(self::CONNECTION), 'ran')));
    }

    public function testFailedMigrationLeavesNoPartialSchemaOnTransactionalDrivers(): void
    {
        $this->write('2025_01_01_000001_half_done', <<<'PHP'
            Schema::create('half', fn(Blueprint $t) => $t->id());
            throw new \RuntimeException('boom');
        PHP);

        try {
            $this->migrator->run(self::CONNECTION);
            $this->fail('Expected a RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('2025_01_01_000001_half_done.php', $e->getMessage());
            $this->assertStringContainsString('boom', $e->getMessage());
        }

        $this->assertFalse($this->hasTable('half'));
        $this->assertSame([], array_filter(array_column($this->migrator->status(self::CONNECTION), 'ran')));
    }

    public function testMigrationCanOptOutOfTheTransaction(): void
    {
        $this->write('2025_01_01_000001_half_done', <<<'PHP'
            Schema::create('half', fn(Blueprint $t) => $t->id());
            throw new \RuntimeException('boom');
        PHP, 'public bool $withinTransaction = false;');

        try {
            $this->migrator->run(self::CONNECTION);
        } catch (\RuntimeException) {
        }

        $this->assertTrue($this->hasTable('half'));
    }

    public function testStatusFlagsModifiedAndMissingMigrations(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->createTable('2025_01_01_000002_create_b_table', 'b');
        $this->migrator->run(self::CONNECTION);

        $this->createTable('2025_01_01_000003_create_c_table', 'c');
        file_put_contents($this->dir . '/migrations/2025_01_01_000001_create_a_table.php', "\n// edited", FILE_APPEND);
        unlink($this->dir . '/migrations/2025_01_01_000002_create_b_table.php');

        $status = array_column($this->migrator->status(self::CONNECTION), null, 'migration');

        $this->assertTrue($status['2025_01_01_000001_create_a_table.php']['modified']);
        $this->assertFalse($status['2025_01_01_000001_create_a_table.php']['missing']);
        $this->assertTrue($status['2025_01_01_000002_create_b_table.php']['missing']);
        $this->assertFalse($status['2025_01_01_000003_create_c_table.php']['ran']);
        $this->assertNull($status['2025_01_01_000003_create_c_table.php']['batch']);
    }

    public function testStatusRecordsExecutionTimeAndRunDate(): void
    {
        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->migrator->run(self::CONNECTION);

        $row = $this->migrator->status(self::CONNECTION)[0];

        $this->assertIsInt($row['execution_time']);
        $this->assertNotEmpty($row['ran_at']);
    }

    public function testExistingMigrationsTableIsUpgradedInPlace(): void
    {
        $pdo = Database::getPdoInstance(self::CONNECTION);
        $pdo->exec('CREATE TABLE migrations (migration VARCHAR(255) NOT NULL, batch INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('2025_01_01_000001_create_a_table.php', 1)");

        $this->createTable('2025_01_01_000001_create_a_table', 'a');
        $this->createTable('2025_01_01_000002_create_b_table', 'b');

        $this->assertSame(['2025_01_01_000002_create_b_table.php'], $this->migrator->run(self::CONNECTION));

        $status = array_column($this->migrator->status(self::CONNECTION), null, 'migration');
        $this->assertSame(1, $status['2025_01_01_000001_create_a_table.php']['batch']);
        $this->assertFalse($status['2025_01_01_000001_create_a_table.php']['modified'], 'legacy rows have no checksum to compare');
        $this->assertSame(2, $status['2025_01_01_000002_create_b_table.php']['batch']);
        $this->assertNotNull($status['2025_01_01_000002_create_b_table.php']['ran_at']);
    }
}
