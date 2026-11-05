<?php

namespace Tests\Unit\Signals;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Signals\Attributes\DeliverOnSettle;
use Phaseolies\Signals\SignalBus;

#[DeliverOnSettle(connection: 'primary')]
class PrimaryPaid
{
    public function __construct(public string $id = 'p-1') {}
}

#[DeliverOnSettle(connection: 'analytics')]
class AnalyticsStored
{
    public function __construct(public string $id = 'a-1') {}
}

class PlainSignal
{
    public function __construct(public string $id = 'x-1') {}
}

final class SettledSignalTest extends TestCase
{
    private SignalBus $bus;
    private Database $primary;
    private Database $analytics;

    /** @var array<int, string> */
    private array $seen = [];

    protected function setUp(): void
    {
        $this->bus = new SignalBus();
        $this->seen = [];

        $this->setStatic('connections', [
            'primary' => new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]),
            'analytics' => new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]),
        ]);
        $this->setStatic('drivers', []);
        $this->setStatic('transactions', []);
        $this->setStatic('pendingSignals', []);

        $this->primary = new Database('primary');
        $this->analytics = new Database('analytics');

        $this->bus->on(PrimaryPaid::class, fn(PrimaryPaid $s) => $this->seen[] = "paid:{$s->id}");
        $this->bus->on(AnalyticsStored::class, fn(AnalyticsStored $s) => $this->seen[] = "stored:{$s->id}");
        $this->bus->on(PlainSignal::class, fn(PlainSignal $s) => $this->seen[] = "plain:{$s->id}");
    }

    protected function tearDown(): void
    {
        $this->setStatic('connections', []);
        $this->setStatic('drivers', []);
        $this->setStatic('transactions', []);
        $this->setStatic('pendingSignals', []);
    }

    private function setStatic(string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty(Database::class, $property);
        $reflection->setValue(null, $value);
    }

    private function pending(): array
    {
        return (new \ReflectionProperty(Database::class, 'pendingSignals'))->getValue();
    }

    public function testSignalRunsOnceAfterTheOutermostCommit(): void
    {
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid());

        $this->assertSame([], $this->seen);

        $this->primary->commit();

        $this->assertSame(['paid:p-1'], $this->seen);
        $this->assertSame([], $this->pending());
    }

    public function testSignalIsNotDeliveredAgainByLaterTransactions(): void
    {
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid());
        $this->primary->commit();

        $this->primary->beginTransaction();
        $this->primary->commit();

        $this->assertSame(['paid:p-1'], $this->seen);
    }

    public function testRollbackDiscardsPendingSignals(): void
    {
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid());
        $this->primary->rollBack();

        $this->assertSame([], $this->seen);
        $this->assertSame([], $this->pending());

        $this->primary->beginTransaction();
        $this->primary->commit();

        $this->assertSame([], $this->seen);
    }

    public function testTransactionClosureCommitsAndDeliversAfterTheResultIsKnown(): void
    {
        $result = $this->primary->transaction(function () {
            $this->bus->emit(new PrimaryPaid());
            $this->assertSame([], $this->seen);

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame(['paid:p-1'], $this->seen);
    }

    public function testTransactionClosureThatThrowsDiscardsSignals(): void
    {
        try {
            $this->primary->transaction(function () {
                $this->bus->emit(new PrimaryPaid());

                throw new \RuntimeException('boom');
            });
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException) {
        }

        $this->assertSame([], $this->seen);
        $this->assertSame([], $this->pending());
        $this->assertSame(0, Database::transactionLevel('primary'));
    }

    public function testRetriedAttemptsOnlyDeliverTheSignalsOfTheSuccessfulAttempt(): void
    {
        $attempt = 0;

        $this->primary->transaction(function () use (&$attempt) {
            $attempt++;
            $this->bus->emit(new PrimaryPaid("attempt-{$attempt}"));

            if ($attempt < 3) {
                throw new \RuntimeException('retry');
            }
        }, 3);

        $this->assertSame(['paid:attempt-3'], $this->seen);
    }

    public function testNestedCommitsWaitForTheOutermostCommit(): void
    {
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('outer'));

        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('inner'));
        $this->primary->commit();

        $this->assertSame([], $this->seen);

        $this->primary->commit();

        $this->assertSame(['paid:outer', 'paid:inner'], $this->seen);
    }

    public function testSignalsKeepTheirChronologicalOrderAcrossNestedLevels(): void
    {
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('1'));

        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('2'));
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('3'));
        $this->primary->commit();
        $this->bus->emit(new PrimaryPaid('4'));
        $this->primary->commit();

        $this->bus->emit(new PrimaryPaid('5'));
        $this->primary->commit();

        $this->assertSame(['paid:1', 'paid:2', 'paid:3', 'paid:4', 'paid:5'], $this->seen);
    }

    public function testInnerRollbackDiscardsOnlyTheInnerSignals(): void
    {
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('outer-before'));

        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('inner'));
        $this->primary->rollBack();

        $this->bus->emit(new PrimaryPaid('outer-after'));
        $this->primary->commit();

        $this->assertSame(['paid:outer-before', 'paid:outer-after'], $this->seen);
    }

    public function testInnerRollbackDiscardsSignalsOfCommittedLevelsInsideIt(): void
    {
        $this->primary->beginTransaction();
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('level-2'));
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('level-3'));
        $this->primary->commit();
        $this->primary->rollBack();
        $this->primary->commit();

        $this->assertSame([], $this->seen);
        $this->assertSame([], $this->pending());
    }

    public function testOuterRollbackDiscardsSignalsOfCommittedInnerLevels(): void
    {
        $this->primary->beginTransaction();
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('inner'));
        $this->primary->commit();
        $this->primary->rollBack();

        $this->assertSame([], $this->seen);
        $this->assertSame([], $this->pending());
    }

    public function testNestedTransactionClosureThatFailsIsCaughtByTheOuterOne(): void
    {
        $this->primary->transaction(function () {
            $this->bus->emit(new PrimaryPaid('outer'));

            try {
                $this->primary->transaction(function () {
                    $this->bus->emit(new PrimaryPaid('inner'));

                    throw new \RuntimeException('inner failed');
                });
            } catch (\RuntimeException) {
            }
        });

        $this->assertSame(['paid:outer'], $this->seen);
    }

    public function testConnectionsSettleIndependently(): void
    {
        $this->primary->beginTransaction();
        $this->analytics->beginTransaction();

        $this->bus->emit(new PrimaryPaid());
        $this->bus->emit(new AnalyticsStored());

        $this->analytics->commit();
        $this->assertSame(['stored:a-1'], $this->seen);

        $this->primary->rollBack();
        $this->assertSame(['stored:a-1'], $this->seen);
        $this->assertSame([], $this->pending());
    }

    public function testSignalBoundToAConnectionIgnoresTransactionsOnOthers(): void
    {
        $this->analytics->beginTransaction();
        $this->bus->emit(new PrimaryPaid());

        // primary has no open transaction, so nothing waits.
        $this->assertSame(['paid:p-1'], $this->seen);

        $this->analytics->rollBack();
        $this->assertSame(['paid:p-1'], $this->seen);
    }

    public function testEmitOnSettleBindsAnyPlainSignalToAConnection(): void
    {
        $this->primary->beginTransaction();

        $this->assertTrue($this->bus->emitOnSettle(new PlainSignal(), 'primary'));
        $this->assertSame([], $this->seen);

        $this->primary->commit();

        $this->assertSame(['plain:x-1'], $this->seen);
    }

    public function testPlainEmitIsNeverHeldByATransaction(): void
    {
        $this->primary->beginTransaction();
        $this->bus->emit(new PlainSignal());

        $this->assertSame(['plain:x-1'], $this->seen);

        $this->primary->rollBack();
        $this->assertSame(['plain:x-1'], $this->seen);
    }

    public function testEmitNowIgnoresSettleRulesAndOpenTransactions(): void
    {
        $this->primary->beginTransaction();
        $this->bus->emitNow(new PrimaryPaid());

        $this->assertSame(['paid:p-1'], $this->seen);

        $this->primary->rollBack();
    }

    public function testSignalOutsideATransactionIsDeliveredImmediately(): void
    {
        $this->bus->emit(new PrimaryPaid());

        $this->assertSame(['paid:p-1'], $this->seen);
    }

    public function testListenerFailureNeverBreaksTheCommitAndLaterListenersStillRun(): void
    {
        $reported = [];
        $this->bus->reportFailuresUsing(function (\Throwable $failure, object $signal) use (&$reported) {
            $reported[] = [$failure->getMessage(), $signal::class];
        });
        $this->bus->on(PrimaryPaid::class, function () { throw new \RuntimeException('listener failed'); }, 10);

        $result = $this->primary->transaction(function () {
            $this->bus->emit(new PrimaryPaid('a'));
            $this->bus->emit(new PrimaryPaid('b'));

            return 'committed';
        });

        $this->assertSame('committed', $result);
        $this->assertSame(0, Database::transactionLevel('primary'));
        $this->assertSame(['paid:a', 'paid:b'], $this->seen);
        $this->assertSame([['listener failed', PrimaryPaid::class], ['listener failed', PrimaryPaid::class]], $reported);
    }

    public function testListenerFailureFallsBackToErrorLogWhenNoReporterOrLogIsAvailable(): void
    {
        $this->bus->on(PrimaryPaid::class, function () { throw new \RuntimeException('listener failed'); }, 10);

        $file = tempnam(sys_get_temp_dir(), 'signal-log');
        $previous = ini_set('error_log', $file);

        try {
            $this->primary->beginTransaction();
            $this->bus->emit(new PrimaryPaid());
            $this->primary->commit();

            $output = (string) file_get_contents($file);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
            @unlink($file);
        }

        $this->assertStringContainsString('Signal listener failed for ' . PrimaryPaid::class . ': RuntimeException: listener failed', $output);
        $this->assertSame(['paid:p-1'], $this->seen);
    }

    public function testThrowingReporterFallsBackToErrorLogAndDoesNotBreakTheCommit(): void
    {
        $this->bus->reportFailuresUsing(function () { throw new \LogicException('reporter broke'); });
        $this->bus->on(PrimaryPaid::class, function () { throw new \RuntimeException('listener failed'); }, 10);

        $file = tempnam(sys_get_temp_dir(), 'signal-log');
        $previous = ini_set('error_log', $file);

        try {
            $this->primary->beginTransaction();
            $this->bus->emit(new PrimaryPaid());
            $this->primary->commit();

            $output = (string) file_get_contents($file);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
            @unlink($file);
        }

        $this->assertStringContainsString('listener failed', $output);
        $this->assertSame(['paid:p-1'], $this->seen);
        $this->assertSame(0, Database::transactionLevel('primary'));
    }

    public function testListenersMayOpenTheirOwnTransactionsWhileSettling(): void
    {
        $this->bus->on(PrimaryPaid::class, function (PrimaryPaid $signal) {
            if ($signal->id !== 'first') {
                return;
            }

            // The outer transaction is already closed, so this is a fresh one.
            $this->assertSame(0, Database::transactionLevel('primary'));

            $this->primary->transaction(function () {
                $this->bus->emit(new PrimaryPaid('from-listener'));
                $this->seen[] = 'listener-inside-tx';
            });
        }, 10);

        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('first'));
        $this->primary->commit();

        $this->assertSame(['listener-inside-tx', 'paid:from-listener', 'paid:first'], $this->seen);
        $this->assertSame([], $this->pending());
    }

    public function testDisconnectingDropsPendingSignals(): void
    {
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid());

        $this->primary->disconnect('primary');

        $this->assertSame([], $this->pending());
        $this->assertSame([], $this->seen);
    }

    public function testSignalsOfTwoBusesAreDeliveredByTheirOwnBus(): void
    {
        $other = new SignalBus();
        $otherSeen = [];
        $other->on(PrimaryPaid::class, function () use (&$otherSeen) { $otherSeen[] = 'other'; });

        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid());
        $other->emit(new PrimaryPaid());
        $this->primary->commit();

        $this->assertSame(['paid:p-1'], $this->seen);
        $this->assertSame(['other'], $otherSeen);
    }

    public function testSignalClassRuleIsReadOnce(): void
    {
        $this->primary->beginTransaction();
        $this->bus->emit(new PrimaryPaid('1'));
        $this->bus->emit(new PrimaryPaid('2'));
        $this->primary->commit();

        $this->assertSame(['paid:1', 'paid:2'], $this->seen);
    }
}
