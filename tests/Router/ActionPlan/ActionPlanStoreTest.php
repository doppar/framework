<?php

namespace Tests\Router\ActionPlan;

use Phaseolies\Support\Router\Plan\ActionPlanner;
use Phaseolies\Support\Router\Plan\ActionPlanStore;
use PHPUnit\Framework\TestCase;
use Tests\Router\ActionPlan\Fixtures as F;

require_once __DIR__ . '/Fixtures.php';

class ActionPlanStoreTest extends TestCase
{
    private string $dir;

    private string $file;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/doppar-plans-' . bin2hex(random_bytes(5));
        $this->file = $this->dir . '/actions.php';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($this->dir);
    }

    private function store(?CountingPlanner $planner = null): ActionPlanStore
    {
        return new ActionPlanStore($this->file, $planner ?? new CountingPlanner());
    }

    private function compiled(?array $actions = null): void
    {
        $this->store()->compile($actions ?? [
            [F\PlainController::class, 'index'],
            [F\RichController::class, 'run'],
            [F\InvokableController::class, '__invoke'],
        ]);
    }

    // ------------------------------------------------------------------
    // Memo
    // ------------------------------------------------------------------

    public function testAnActionIsPlannedOnceNoMatterHowOftenItIsRequested(): void
    {
        $planner = new CountingPlanner();
        $store = $this->store($planner);

        $first = $store->forAction(F\RichController::class, 'run');

        for ($i = 0; $i < 50; $i++) {
            $this->assertSame($first, $store->forAction(F\RichController::class, 'run'));
        }

        $this->assertSame([F\RichController::class . '::run'], $planner->actions);
    }

    public function testDifferentActionsAreKeptApart(): void
    {
        $store = $this->store();

        $this->assertSame('index', $store->forAction(F\PlainController::class, 'index')['method']);
        $this->assertSame('run', $store->forAction(F\RichController::class, 'run')['method']);
    }

    public function testTheKeyIgnoresCaseAndALeadingBackslash(): void
    {
        $planner = new CountingPlanner();
        $store = $this->store($planner);

        $store->forAction(F\PlainController::class, 'index');
        $store->forAction('\\' . strtoupper(F\PlainController::class), 'INDEX');

        $this->assertCount(1, $planner->actions);
        $this->assertSame(
            ActionPlanStore::key(F\PlainController::class, 'index'),
            ActionPlanStore::key('\\' . strtolower(F\PlainController::class), 'Index')
        );
    }

    public function testAFailedPlanIsNotRemembered(): void
    {
        $planner = new CountingPlanner();
        $store = $this->store($planner);

        for ($i = 0; $i < 2; $i++) {
            try {
                $store->forAction(F\PlainController::class, 'nope');
                $this->fail('expected an exception');
            } catch (\BadMethodCallException) {
            }
        }

        $this->assertCount(2, $planner->actions, 'the error is reported every time, as without a cache');
    }

    public function testFlushForgetsEverything(): void
    {
        $planner = new CountingPlanner();
        $store = $this->store($planner);

        $store->forAction(F\PlainController::class, 'index');
        $store->flush();
        $store->forAction(F\PlainController::class, 'index');

        $this->assertCount(2, $planner->actions);
    }

    public function testAClosureIsPlannedOnceAndReleasedWithTheClosure(): void
    {
        $planner = new CountingPlanner();
        $store = $this->store($planner);
        $closure = fn(int $id) => $id;

        $first = $store->forClosure($closure);
        $this->assertSame($first, $store->forClosure($closure));
        $this->assertSame(1, $planner->closures);

        $other = fn(int $id) => $id;
        $store->forClosure($other);
        $this->assertSame(2, $planner->closures, 'a different closure is planned separately');

        $weak = (new \ReflectionProperty($store, 'closures'))->getValue($store);
        $this->assertCount(2, $weak);

        unset($closure, $other);
        gc_collect_cycles();

        $this->assertCount(0, $weak, 'plans of closures that no longer exist must not pile up in a worker');
    }

    // ------------------------------------------------------------------
    // Compiled file
    // ------------------------------------------------------------------

    public function testCompilingWritesAPhpFileThatReturnsTheFormatAndThePlans(): void
    {
        $count = $this->store()->compile([
            [F\PlainController::class, 'index'],
            [F\RichController::class, 'run'],
        ]);

        $this->assertSame(2, $count);
        $this->assertFileExists($this->file);

        $data = require $this->file;

        $this->assertSame(ActionPlanner::FORMAT, $data['format']);
        $this->assertSame(
            [ActionPlanStore::key(F\PlainController::class, 'index'), ActionPlanStore::key(F\RichController::class, 'run')],
            array_keys($data['plans'])
        );
    }

    public function testTheCompiledFileIsDeterministic(): void
    {
        $this->compiled();
        $first = file_get_contents($this->file);

        $this->compiled(array_reverse([
            [F\PlainController::class, 'index'],
            [F\RichController::class, 'run'],
            [F\InvokableController::class, '__invoke'],
        ]));

        $this->assertSame($first, file_get_contents($this->file), 'the same actions in another order must give the same file');
    }

    public function testCompilingCreatesTheDirectoryAndLeavesNoTemporaryFiles(): void
    {
        $this->assertDirectoryDoesNotExist($this->dir);

        $this->compiled();

        $this->assertSame([$this->file], glob($this->dir . '/*'));
    }

    public function testActionsThatCannotBePlannedAreSkippedNotFatal(): void
    {
        $count = $this->store()->compile([
            [F\PlainController::class, 'index'],
            ['Tests\\Router\\ActionPlan\\Fixtures\\Missing', 'index'],
            [F\PlainController::class, 'nope'],
        ]);

        $this->assertSame(1, $count);
    }

    public function testCompiledPlansAreServedWithoutTheReflectionPlanner(): void
    {
        $this->compiled();

        $planner = new CountingPlanner();
        $store = $this->store($planner);
        $store->useCompiled();

        $fromFile = $store->forAction(F\RichController::class, 'run');

        $this->assertSame([], $planner->actions, 'nothing may be reflected');
        $this->assertSame((new ActionPlanner())->forAction(F\RichController::class, 'run'), $fromFile);
    }

    public function testCompiledPlansAreIgnoredUnlessRequested(): void
    {
        $this->compiled();

        $planner = new CountingPlanner();
        $this->store($planner)->forAction(F\RichController::class, 'run');

        $this->assertCount(1, $planner->actions);
    }

    public function testAnActionMissingFromTheCompiledFileFallsBackToThePlanner(): void
    {
        $this->compiled([[F\PlainController::class, 'index']]);

        $planner = new CountingPlanner();
        $store = $this->store($planner);
        $store->useCompiled();

        $store->forAction(F\PlainController::class, 'index');
        $store->forAction(F\RichController::class, 'run');

        $this->assertSame([F\RichController::class . '::run'], $planner->actions);
    }

    public function testAMissingCompiledFileIsNotAnError(): void
    {
        $planner = new CountingPlanner();
        $store = $this->store($planner);
        $store->useCompiled();

        $this->assertSame('index', $store->forAction(F\PlainController::class, 'index')['method']);
        $this->assertCount(1, $planner->actions);
    }

    private static function poisonKey(): string
    {
        return ActionPlanStore::key(F\PlainController::class, 'index');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableFiles(): array
    {
        return [
            'not php' => ["this is not php <?php"],
            'returns a string' => ['<?php return "nope";'],
            'returns null' => ['<?php return null;'],
            'no plans key' => ['<?php return ["format" => ' . ActionPlanner::FORMAT . '];'],
            'plans is not an array' => ['<?php return ["format" => ' . ActionPlanner::FORMAT . ', "plans" => "x"];'],
            // These hold a poisoned plan for the very action the test asks for: it must be
            // ignored, not returned.
            'another release format' => ['<?php return ["format" => ' . (ActionPlanner::FORMAT + 1) . ', "plans" => [' . var_export(self::poisonKey(), true) . ' => ["poison" => true]]];'],
            'no format' => ['<?php return ["plans" => [' . var_export(self::poisonKey(), true) . ' => ["poison" => true]]];'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusableFiles')]
    public function testAnUnusableCompiledFileIsIgnoredInsteadOfMisread(string $contents): void
    {
        mkdir($this->dir, 0755, true);
        file_put_contents($this->file, $contents);

        $planner = new CountingPlanner();
        $store = $this->store($planner);
        $store->useCompiled();

        // A file that is not PHP at all prints itself when loaded; that is not what is under test.
        ob_start();
        $plan = @$store->forAction(F\PlainController::class, 'index');
        ob_end_clean();

        $this->assertArrayNotHasKey('poison', $plan, 'a plan from an unusable file must never be served');
        $this->assertSame(ActionPlanner::FORMAT, $plan['format']);
        $this->assertCount(1, $planner->actions, 'planned normally');
    }

    public function testClearDeletesTheFileAndForgetsWhatWasLoaded(): void
    {
        $this->compiled();

        $store = $this->store();
        $store->useCompiled();
        $store->forAction(F\PlainController::class, 'index');

        $this->assertTrue($store->clear());
        $this->assertFileDoesNotExist($this->file);

        $planner = new CountingPlanner();
        $again = $this->store($planner);
        $again->useCompiled();
        $again->forAction(F\PlainController::class, 'index');

        $this->assertCount(1, $planner->actions);
    }

    public function testClearingWhenThereIsNothingToClearSucceeds(): void
    {
        $this->assertTrue($this->store()->clear());
    }

    public function testCompilingAgainReplacesThePreviousFile(): void
    {
        $this->compiled([[F\PlainController::class, 'index']]);
        $this->compiled([[F\RichController::class, 'run']]);

        $data = require $this->file;

        $this->assertSame([ActionPlanStore::key(F\RichController::class, 'run')], array_keys($data['plans']));
    }

    public function testACompileThatCannotWriteFailsLoudly(): void
    {
        $blocker = sys_get_temp_dir() . '/doppar-plans-blocker-' . bin2hex(random_bytes(4));
        file_put_contents($blocker, 'a file where a directory is needed');

        try {
            $store = new ActionPlanStore($blocker . '/sub/actions.php', new CountingPlanner());

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Cannot create the cache directory');

            $store->compile([[F\PlainController::class, 'index']]);
        } finally {
            unlink($blocker);
        }
    }

    public function testThePathCanBeResolvedLazily(): void
    {
        $resolved = 0;
        $store = new ActionPlanStore(function () use (&$resolved) {
            $resolved++;

            return $this->file;
        }, new CountingPlanner());

        $store->forAction(F\PlainController::class, 'index');
        $this->assertSame(0, $resolved, 'a request that never reads the file must not resolve its path');

        $store->useCompiled();
        $store->forAction(F\RichController::class, 'run');
        $store->forAction(F\InvokableController::class, '__invoke');

        $this->assertSame(1, $resolved);
        $this->assertSame($this->file, $store->path());
    }

    public function testCompiledAndFreshPlansAreIdentical(): void
    {
        $actions = [
            [F\PlainController::class, 'index'],
            [F\RichController::class, 'run'],
            [F\TypeShapesController::class, 'run'],
            [F\StorableDefaultsController::class, 'run'],
            [F\LazyDefaultController::class, 'run'],
            [F\ChildController::class, 'inherited'],
            [F\TransactionController::class, 'tracked'],
        ];

        $this->compiled($actions);

        $store = $this->store();
        $store->useCompiled();

        foreach ($actions as [$class, $method]) {
            $this->assertSame((new ActionPlanner())->forAction($class, $method), $store->forAction($class, $method), "{$class}::{$method}");
        }
    }
}
