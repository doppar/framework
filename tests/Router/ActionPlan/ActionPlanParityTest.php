<?php

namespace Tests\Router\ActionPlan;

use Phaseolies\Support\Router\Plan\ActionPlanStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Gateway;

/**
 * Behaviour lock for the router's argument resolution.
 *
 * golden.json holds, for each scenario, exactly what the router did when its
 * resolution was reflection-driven on every request: the result, the ordered
 * container calls, model lookups, transactions and exceptions. The compiled
 * action plans must reproduce it byte for byte.
 *
 * Regenerate only when behaviour is changed on purpose:
 *   UPDATE_GOLDEN=1 vendor/bin/phpunit tests/Router/ActionPlan/ActionPlanParityTest.php
 */
class ActionPlanParityTest extends TestCase
{
    private const GOLDEN = __DIR__ . '/golden.json';

    public static function scenarioNames(): array
    {
        return array_map(fn($name) => [$name], array_keys(ScenarioRunner::scenarios()));
    }

    #[DataProvider('scenarioNames')]
    public function testScenarioBehavesExactlyAsRecorded(string $name): void
    {
        $actual = $this->normalize($this->runScenario($name));

        if (getenv('UPDATE_GOLDEN') === '1') {
            $this->record($name, $actual);
            $this->markTestSkipped("Recorded golden output for [{$name}].");
        }

        $golden = json_decode((string) file_get_contents(self::GOLDEN), true);

        $this->assertIsArray($golden);
        $this->assertArrayHasKey($name, $golden, 'no golden output; run with UPDATE_GOLDEN=1 first');
        $this->assertSame($golden[$name], $actual);
    }

    #[DataProvider('scenarioNames')]
    public function testCompiledPlansBehaveExactlyAsRecordedWithoutAnyReflectionPlanning(string $name): void
    {
        if (getenv('UPDATE_GOLDEN') === '1') {
            $this->markTestSkipped('Recording.');
        }

        $dir = sys_get_temp_dir() . '/doppar-parity-' . bin2hex(random_bytes(5));

        try {
            // What route:cache does: compile every controller action of every scenario.
            $actions = [];
            foreach (ScenarioRunner::scenarios() as [$callback]) {
                if (is_array($callback)) {
                    $actions[] = $callback;
                } elseif (is_string($callback)) {
                    $actions[] = [$callback, '__invoke'];
                }
            }
            (new ActionPlanStore($dir . '/actions.php'))->compile($actions);

            $planner = new CountingPlanner();
            $store = new ActionPlanStore($dir . '/actions.php', $planner);
            $store->useCompiled();

            $actual = $this->normalize($this->runScenario($name, $store));
            $golden = json_decode((string) file_get_contents(self::GOLDEN), true);

            $this->assertSame($golden[$name], $actual);

            // Only an action that cannot be planned at all (missing class or method) may reach the planner.
            $unplannable = ['missing method', 'missing class'];
            $this->assertSame(
                in_array($name, $unplannable, true) ? 1 : 0,
                count($planner->actions),
                'planned by reflection although a compiled plan exists: ' . implode(', ', $planner->actions)
            );
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($dir);
        }
    }

    #[DataProvider('scenarioNames')]
    public function testARepeatedDispatchOnTheSameRouterBehavesExactlyAsRecorded(string $name): void
    {
        if (getenv('UPDATE_GOLDEN') === '1') {
            $this->markTestSkipped('Recording.');
        }

        $planner = new CountingPlanner();
        $store = new ActionPlanStore(sys_get_temp_dir() . '/doppar-parity-unused/actions.php', $planner);
        $golden = json_decode((string) file_get_contents(self::GOLDEN), true);

        // Three dispatches on one router, as in a persistent worker.
        for ($request = 1; $request <= 3; $request++) {
            $this->assertSame($golden[$name], $this->normalize($this->runScenario($name, $store)), "dispatch #{$request}");
        }

        // A plan is built once and remembered. One that cannot be built (missing class or
        // method) is retried, so its error is reported on every dispatch as it always was.
        $attempts = array_count_values($planner->actions);
        $allowed = in_array($name, ['missing method', 'missing class'], true) ? 3 : 1;

        $this->assertLessThanOrEqual($allowed, $attempts === [] ? 0 : max($attempts), 'planned more often than allowed');
    }

    public function testEveryGoldenScenarioStillExists(): void
    {
        if (getenv('UPDATE_GOLDEN') === '1') {
            $this->markTestSkipped('Recording.');
        }

        $golden = json_decode((string) file_get_contents(self::GOLDEN), true);

        $this->assertSame(array_keys(ScenarioRunner::scenarios()), array_keys($golden));
    }

    private function runScenario(string $name, ?ActionPlanStore $store = null): array
    {
        [$callback, $routeParams, $alreadyBound, $modelFound] = ScenarioRunner::scenarios()[$name] + [3 => true];

        $router = new RecordingRouter(new Gateway());

        if ($store !== null) {
            $router->useActionPlans($store);
        }

        return (new ScenarioRunner(fn(string $class) => $this->createStub($class)))
            ->run($callback, $routeParams, $alreadyBound, $router, $modelFound);
    }

    /**
     * JSON round trip: the comparison is on exactly what is stored on disk.
     */
    private function normalize(array $output): array
    {
        return json_decode(json_encode($output, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    private function record(string $name, array $actual): void
    {
        $golden = file_exists(self::GOLDEN) ? json_decode((string) file_get_contents(self::GOLDEN), true) : [];
        $golden[$name] = $actual;

        // Keep the scenario order stable.
        $ordered = [];
        foreach (array_keys(ScenarioRunner::scenarios()) as $key) {
            if (isset($golden[$key])) {
                $ordered[$key] = $golden[$key];
            }
        }

        file_put_contents(self::GOLDEN, json_encode($ordered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
