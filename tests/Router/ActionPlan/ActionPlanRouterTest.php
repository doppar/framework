<?php

namespace Tests\Router\ActionPlan;

use Phaseolies\DI\Container;
use Phaseolies\Support\Router;
use Phaseolies\Support\Router\Plan\ActionPlanner;
use Phaseolies\Support\Router\Plan\ActionPlanStore;
use PHPUnit\Framework\TestCase;
use Tests\Router\ActionPlan\Fixtures as F;
use Tests\Support\Gateway;
use Tests\Support\MockContainer;

require_once __DIR__ . '/ScenarioRunner.php';

/**
 * A router with its own cache location, so caching tests neither touch the
 * application's cache nor leak into other tests. Redeclaring the statics gives
 * this class storage of its own.
 */
final class CachingRouter extends Router
{
    protected static string $cachePath;

    protected static bool $cacheLoaded = false;

    public static function pointTo(string $path): void
    {
        static::$cachePath = $path;
        static::$cacheLoaded = false;
    }
}

class ActionPlanRouterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());

        $this->dir = sys_get_temp_dir() . '/doppar-router-plans-' . bin2hex(random_bytes(5));
        mkdir($this->dir, 0755, true);

        CachingRouter::pointTo($this->dir . '/routes.php');
        $this->resetRoutes();
    }

    protected function tearDown(): void
    {
        $this->resetRoutes();

        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);
        Container::forgetInstance();
    }

    private function resetRoutes(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $reflection->getProperty('routes')->setValue(null, []);
        $reflection->getProperty('namedRoutes')->setValue(null, []);
        $reflection->getProperty('routeMiddlewares')->setValue(null, [
            'GET' => [], 'POST' => [], 'PUT' => [], 'PATCH' => [], 'DELETE' => [], 'OPTIONS' => [], 'HEAD' => [], 'ANY' => [],
        ]);
    }

    private function router(): CachingRouter
    {
        return new CachingRouter(new Gateway());
    }

    private function plansFile(): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . 'actions.php';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function compiledPlans(): array
    {
        return (require $this->plansFile())['plans'];
    }

    private function resolve(Router $router, mixed $callback, array $routeParams = []): array
    {
        return (new ScenarioRunner(fn(string $class) => $this->createStub($class)))->run($callback, $routeParams, [], $router);
    }

    // ------------------------------------------------------------------
    // route:cache
    // ------------------------------------------------------------------

    public function testCachingRoutesCompilesAPlanForEveryCacheableRoute(): void
    {
        $router = $this->router();
        $router->get('/plain', [F\PlainController::class, 'index']);
        $router->get('/invokable', F\InvokableController::class);
        $router->get('/domain', [F\ParamsController::class, 'show'])->domain('api.example.test');
        $router->get('/closure', fn() => 'not cacheable');

        $router->cacheRoutes();

        $this->assertFileExists($this->dir . '/routes.php');
        $this->assertFileExists($this->plansFile(), 'plans are written next to the route cache');

        $this->assertSame([
            ActionPlanStore::key(F\InvokableController::class, '__invoke'),
            ActionPlanStore::key(F\ParamsController::class, 'show'),
            ActionPlanStore::key(F\PlainController::class, 'index'),
        ], array_keys($this->compiledPlans()));
    }

    public function testACachedPlanIsTheSamePlanReflectionWouldBuild(): void
    {
        $router = $this->router();
        $router->get('/rich', [F\RichController::class, 'run']);
        $router->cacheRoutes();

        $this->assertSame(
            (new ActionPlanner())->forAction(F\RichController::class, 'run'),
            $this->compiledPlans()[ActionPlanStore::key(F\RichController::class, 'run')]
        );
    }

    public function testAnActionSharedByManyRoutesHasOnePlan(): void
    {
        $router = $this->router();
        $router->get('/a', [F\PlainController::class, 'index']);
        $router->post('/b', [F\PlainController::class, 'index']);
        $router->get('/c', [F\PlainController::class, 'index'])->domain('c.example.test');

        $router->cacheRoutes();

        $this->assertCount(1, $this->compiledPlans());
    }

    public function testCachingRoutesWithNothingCacheableStillWritesAValidFile(): void
    {
        $router = $this->router();
        $router->get('/closure', fn() => 'x');

        $router->cacheRoutes();

        $this->assertSame([], $this->compiledPlans());
    }

    public function testARouteToAMissingMethodDoesNotBreakCaching(): void
    {
        $router = $this->router();
        $router->get('/ok', [F\PlainController::class, 'index']);

        // A route that got into the table pointing at a method that does not exist.
        $routes = new \ReflectionProperty(Router::class, 'routes');
        $table = $routes->getValue();
        $table['GET']['/broken'] = [F\PlainController::class, 'nope'];
        $routes->setValue(null, $table);

        $router->cacheRoutes();

        $this->assertSame([ActionPlanStore::key(F\PlainController::class, 'index')], array_keys($this->compiledPlans()));
    }

    public function testCachingAgainReplacesStalePlans(): void
    {
        $router = $this->router();
        $router->get('/first', [F\PlainController::class, 'index']);
        $router->cacheRoutes();

        $this->resetRoutes();

        $router->get('/second', [F\ParamsController::class, 'none']);
        $router->cacheRoutes();

        $this->assertSame([ActionPlanStore::key(F\ParamsController::class, 'none')], array_keys($this->compiledPlans()));
    }

    // ------------------------------------------------------------------
    // Using the cache
    // ------------------------------------------------------------------

    public function testLoadingTheRouteCacheMakesRequestsUseTheCompiledPlans(): void
    {
        $writer = $this->router();
        $writer->get('/params', [F\ParamsController::class, 'show']);
        $writer->cacheRoutes();

        $planner = new CountingPlanner();
        $reader = $this->router();
        $reader->useActionPlans(new ActionPlanStore($this->plansFile(), $planner));

        $this->assertTrue($reader->loadCachedRoutes());

        $result = $this->resolve($reader, [F\ParamsController::class, 'show'], ['id' => 4]);

        $this->assertNull($result['exception']);
        $this->assertSame([['value' => 4], ['value' => 'x'], ['value' => null]], $result['result']);
        $this->assertSame([], $planner->actions, 'a cached request must not reflect');
    }

    public function testWithoutTheRouteCachePlansAreStillOnlyBuiltOnce(): void
    {
        $planner = new CountingPlanner();
        $router = $this->router();
        $router->useActionPlans(new ActionPlanStore($this->plansFile(), $planner));

        for ($i = 0; $i < 25; $i++) {
            $this->assertNull($this->resolve($router, [F\ParamsController::class, 'show'], ['id' => $i])['exception']);
        }

        $this->assertCount(1, $planner->actions);
    }

    public function testAFileOfPlansIsNotUsedWhenTheRouteCacheIsNotLoaded(): void
    {
        $writer = $this->router();
        $writer->get('/params', [F\ParamsController::class, 'show']);
        $writer->cacheRoutes();

        // Same file on disk, but this router never loaded the cached routes.
        $planner = new CountingPlanner();
        $router = $this->router();
        $router->useActionPlans(new ActionPlanStore($this->plansFile(), $planner));

        $this->resolve($router, [F\ParamsController::class, 'show'], ['id' => 1]);

        $this->assertCount(1, $planner->actions, 'plans and routes must come from the same build');
    }

    public function testTheDefaultStoreLivesNextToTheRouteCache(): void
    {
        $this->assertSame($this->plansFile(), $this->router()->actionPlans()->path());
    }

    public function testUseActionPlansReplacesTheStore(): void
    {
        $router = $this->router();
        $custom = new ActionPlanStore($this->plansFile());

        $router->useActionPlans($custom);

        $this->assertSame($custom, $router->actionPlans());
    }

    // ------------------------------------------------------------------
    // route:clear
    // ------------------------------------------------------------------

    public function testClearingTheRouteCacheAlsoRemovesThePlans(): void
    {
        $router = $this->router();
        $router->get('/plain', [F\PlainController::class, 'index']);
        $router->cacheRoutes();

        $this->assertFileExists($this->plansFile());

        $this->assertTrue($router->clearRouteCache());

        $this->assertFileDoesNotExist($this->dir . '/routes.php');
        $this->assertFileDoesNotExist($this->plansFile());
    }

    public function testClearingWhenNothingIsCachedSucceeds(): void
    {
        $this->assertTrue($this->router()->clearRouteCache());
    }

    public function testAfterClearingRequestsFallBackToPlanningWithoutErrors(): void
    {
        $router = $this->router();
        $router->get('/params', [F\ParamsController::class, 'show']);
        $router->cacheRoutes();
        $router->clearRouteCache();

        $planner = new CountingPlanner();
        $fresh = $this->router();
        $fresh->useActionPlans(new ActionPlanStore($this->plansFile(), $planner));
        $fresh->actionPlans()->useCompiled();

        $result = $this->resolve($fresh, [F\ParamsController::class, 'show'], ['id' => 2]);

        $this->assertNull($result['exception']);
        $this->assertCount(1, $planner->actions);
    }

    // ------------------------------------------------------------------
    // Worker mode
    // ------------------------------------------------------------------

    public function testManyDispatchesOnOneRouterReflectOnceAndKeepReturningTheSameResult(): void
    {
        $planner = new CountingPlanner();
        $router = $this->router();
        $router->useActionPlans(new ActionPlanStore($this->plansFile(), $planner));

        $first = $this->resolve($router, [F\BindController::class, 'run'], ['id' => 9]);

        for ($i = 0; $i < 200; $i++) {
            $this->assertSame($first, $this->resolve($router, [F\BindController::class, 'run'], ['id' => 9]));
        }

        $this->assertNull($first['exception']);
        $this->assertCount(1, $planner->actions);
    }

    public function testClosuresAreReflectedOncePerClosureObject(): void
    {
        $planner = new CountingPlanner();
        $router = $this->router();
        $router->useActionPlans(new ActionPlanStore($this->plansFile(), $planner));

        $closure = fn(int $id = 1) => $id;

        for ($i = 0; $i < 10; $i++) {
            $this->resolve($router, $closure);
        }

        $this->assertSame(1, $planner->closures);
    }

    public function testInvalidCallbacksAreRejectedClearly(): void
    {
        $result = $this->resolve($this->router(), 12345);

        $this->assertSame(\InvalidArgumentException::class, $result['exception'][0]);
        $this->assertStringContainsString('int', $result['exception'][1]);
    }
}
