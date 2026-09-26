<?php

namespace Tests\Router\ActionPlan;

use Phaseolies\Support\Router\Plan\ActionPlanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Router\ActionPlan\Fixtures as F;
use Phaseolies\DI\Attributes\Bind;

require_once __DIR__ . '/Fixtures.php';

class PlannerProbeController
{
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }

    public function run(): void
    {
    }
}

class ActionPlannerTest extends TestCase
{
    private ActionPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new ActionPlanner();
    }

    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function actions(): array
    {
        return [
            'plain' => [F\PlainController::class, 'index'],
            'rich' => [F\RichController::class, 'run'],
            'type shapes' => [F\TypeShapesController::class, 'run'],
            'storable defaults' => [F\StorableDefaultsController::class, 'run'],
            'lazy defaults' => [F\LazyDefaultController::class, 'run'],
            'inherited' => [F\ChildController::class, 'inherited'],
            'invokable' => [F\InvokableController::class, '__invoke'],
            'transaction' => [F\TransactionController::class, 'tracked'],
        ];
    }

    #[DataProvider('actions')]
    public function testAPlanSurvivesAPhpFileRoundTripUnchanged(string $class, string $method): void
    {
        $plan = $this->planner->forAction($class, $method);

        // This is exactly what the compiled cache does: var_export it, load it as PHP.
        $loaded = eval('return ' . var_export($plan, true) . ';');

        $this->assertSame($plan, $loaded);
    }

    #[DataProvider('actions')]
    public function testAPlanHoldsOnlyPlainData(string $class, string $method): void
    {
        $this->assertNoObjects($this->planner->forAction($class, $method));
    }

    public function testThePlanRecordsTheFormatVersion(): void
    {
        $this->assertSame(ActionPlanner::FORMAT, $this->planner->forAction(F\PlainController::class, 'index')['format']);
    }

    public function testResolversComeFromTheClassThenTheMethodInDeclarationOrder(): void
    {
        $plan = $this->planner->forAction(F\RichController::class, 'run');

        $this->assertSame([
            [F\Marker::class, F\Audit::class, false],
            [F\OtherMarker::class, F\Audit::class, true],
            [F\GreeterContract::class, F\Greeter::class, false],
        ], $plan['resolvers']);
    }

    public function testATransactionAttributeIsCaptured(): void
    {
        $this->assertSame(['reports', 4], $this->planner->forAction(F\RichController::class, 'run')['transaction']);
        $this->assertSame(['analytics', 3], $this->planner->forAction(F\TransactionController::class, 'tracked')['transaction']);
        $this->assertSame([null, 1], $this->planner->forAction(F\TransactionController::class, 'defaults')['transaction']);
        $this->assertNull($this->planner->forAction(F\PlainController::class, 'index')['transaction']);
    }

    public function testParameterAttributesAreCaptured(): void
    {
        $parameters = $this->planner->forAction(F\RichController::class, 'run')['action']['parameters'];

        $this->assertSame(['slug', true], $parameters[0]['model']);
        $this->assertSame([true, true], $parameters[1]['payload']);
        $this->assertSame([F\Greeter::class, true], $parameters[2]['bind']);
        $this->assertNull($parameters[3]['model']);
        $this->assertNull($parameters[3]['payload']);
        $this->assertNull($parameters[3]['bind']);
        $this->assertSame(['model', 'dto', 'greeter', 'page'], $this->planner->forAction(F\RichController::class, 'run')['action']['names']);
    }

    public function testAttributeNamesAreMatchedWithoutRegardToCase(): void
    {
        // Class names are case-insensitive in PHP, so an attribute written in
        // another case is the same attribute and must be found.
        $plan = $this->planner->forAction(F\CaseInsensitiveAttributeController::class, 'run');

        $this->assertSame([[F\GreeterContract::class, F\Greeter::class, false]], $plan['resolvers']);
        $this->assertSame(['shouting', 2], $plan['transaction']);

        [$greeter, $dto, $model] = $plan['action']['parameters'];

        $this->assertSame([F\Greeter::class, true], $greeter['bind']);
        $this->assertSame([true, false], $dto['payload']);
        $this->assertSame(['email', false], $model['model']);
    }

    public function testTheConstructorIsPlannedSeparately(): void
    {
        $plan = $this->planner->forAction(F\RichController::class, 'run');

        $this->assertSame('__construct', $plan['constructor']['name']);
        $this->assertSame(F\RichController::class, $plan['constructor']['declaring']);
        $this->assertSame(['audit', 'limit'], $plan['constructor']['names']);
        $this->assertSame(F\Audit::class, $plan['constructor']['parameters'][0]['type']);
        $this->assertSame(3, $plan['constructor']['parameters'][1]['default']);

        $this->assertNull($this->planner->forAction(F\PlainController::class, 'index')['constructor']);
    }

    public function testTheTypeOfEveryKindOfParameterIsClassified(): void
    {
        $p = $this->planner->forAction(F\TypeShapesController::class, 'run')['action']['parameters'];
        $byName = array_column($p, null, 'name');

        $shape = fn(string $name) => [$byName[$name]['type'], $byName[$name]['typed'], $byName[$name]['builtin']];

        $this->assertSame([null, false, false], $shape('untyped'));
        $this->assertSame([null, true, true], $shape('number'));
        $this->assertSame([F\Audit::class, true, false], $shape('nullableClass'), 'a nullable class is still a class');
        $this->assertSame([null, true, true], $shape('union'), 'a union is not one class the container can build');
        $this->assertSame([null, true, true], $shape('builtinUnion'));
        $this->assertSame([null, true, true], $shape('intersection'));
        $this->assertSame([F\TypeShapesController::class, true, false], [$byName['self']['type'] === 'self' ? F\TypeShapesController::class : $byName['self']['type'], true, false]);
        $this->assertSame([null, true, true], $shape('items'));
    }

    public function testDefaultsThatAreDataAreStoredAndSurviveExactly(): void
    {
        $parameters = $this->planner->forAction(F\StorableDefaultsController::class, 'run')['action']['parameters'];
        $byName = array_column($parameters, null, 'name');

        $expected = [
            'a' => null,
            'b' => false,
            'c' => -5,
            'd' => 1.5,
            'e' => "quote ' and \\ backslash",
            'f' => ['x' => [1, 2, ['deep' => true]], 3 => null],
            'g' => PHP_INT_MAX,
            'h' => F\DefaultsController::LABEL,
        ];

        foreach ($expected as $name => $value) {
            $this->assertTrue($byName[$name]['optional'], $name);
            $this->assertFalse($byName[$name]['lazyDefault'], $name);
            $this->assertSame($value, $byName[$name]['default'], $name);
        }
    }

    public function testADefaultThatCannotBeStoredIsFlaggedToBeReadLazily(): void
    {
        $parameters = $this->planner->forAction(F\LazyDefaultController::class, 'run')['action']['parameters'];

        $this->assertTrue($parameters[0]['optional']);
        $this->assertTrue($parameters[0]['lazyDefault'], 'holds enum cases');
        $this->assertArrayNotHasKey('default', $parameters[0]);
        $this->assertFalse($parameters[1]['lazyDefault']);
        $this->assertSame(1, $parameters[1]['default']);
    }

    public function testAVariadicParameterIsLazyBecauseItHasNoDefault(): void
    {
        $rest = array_column($this->planner->forAction(F\TypeShapesController::class, 'run')['action']['parameters'], null, 'name')['rest'];

        $this->assertTrue($rest['optional']);
        $this->assertTrue($rest['lazyDefault']);
    }

    public function testAnInheritedActionReportsItsDeclaringClass(): void
    {
        $plan = $this->planner->forAction(F\ChildController::class, 'inherited');

        $this->assertSame(F\ChildController::class, $plan['class']);
        $this->assertSame(F\BaseController::class, $plan['action']['declaring']);
    }

    public function testTheCanonicalClassNameIsUsedWhateverCaseWasGiven(): void
    {
        $plan = $this->planner->forAction('tests\\router\\actionplan\\fixtures\\plaincontroller', 'INDEX');

        $this->assertSame(F\PlainController::class, $plan['class']);
    }

    public function testAClosureIsPlannedWithoutAClass(): void
    {
        $plan = $this->planner->forClosure(
            fn(#[Bind(concrete: F\Greeter::class)] F\GreeterContract $greeter, int $id = 5) => null
        );

        $this->assertNull($plan['class']);
        $this->assertSame('{closure}', $plan['method']);
        $this->assertSame([], $plan['resolvers']);
        $this->assertNull($plan['transaction']);
        $this->assertNull($plan['constructor']);
        $this->assertSame(['greeter', 'id'], $plan['action']['names']);
        $this->assertSame([F\Greeter::class, false], $plan['action']['parameters'][0]['bind']);
        $this->assertSame(5, $plan['action']['parameters'][1]['default']);
        $this->assertNoObjects($plan);
    }

    public function testPlanningNeverInstantiatesTheController(): void
    {
        PlannerProbeController::$constructed = 0;

        $this->planner->forAction(PlannerProbeController::class, 'run');

        $this->assertSame(0, PlannerProbeController::$constructed);
    }

    public function testAMissingClassIsReportedByReflection(): void
    {
        $this->expectException(\ReflectionException::class);

        $this->planner->forAction('Tests\\Router\\ActionPlan\\Fixtures\\Nope', 'index');
    }

    public function testAMissingMethodIsReportedWithTheSameMessageAsBefore(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Method ' . F\PlainController::class . '::nope() does not exist');

        $this->planner->forAction(F\PlainController::class, 'nope');
    }

    private function assertNoObjects(mixed $value): void
    {
        $this->assertFalse(is_object($value), 'a plan must be plain data');

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertNoObjects($item);
            }
        }
    }
}
