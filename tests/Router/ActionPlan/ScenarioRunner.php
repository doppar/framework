<?php

namespace Tests\Router\ActionPlan;

use Phaseolies\Application;
use Phaseolies\DI\Attributes\Bind;
use Phaseolies\DI\Container;
use Phaseolies\Http\Requests\Attributes\BindPayload;
use Phaseolies\Support\Router;
use Tests\Router\ActionPlan\Fixtures as F;
use Tests\Support\Gateway;

require_once __DIR__ . '/Fixtures.php';

/**
 * A Router whose transaction wrapper is recorded instead of opening a database
 * connection. The signature matches the real protected method.
 */
final class RecordingRouter extends Router
{
    /** @var array<int, array<int, mixed>> */
    public array $transactions = [];

    protected function executeInTransaction(
        object $controllerInstance,
        string $actionMethod,
        array $actionDependencies,
        ?string $connection,
        int $attempts
    ): mixed {
        $this->transactions[] = [$actionMethod, $connection, $attempts];

        return call_user_func([$controllerInstance, $actionMethod], ...$actionDependencies);
    }
}

/**
 * A request stand-in that records DTO binding.
 */
final class RecordingRequest
{
    public function __construct(private ScenarioRunner $runner)
    {
    }

    public function bindTo(object $object, bool $strict = true): object
    {
        $this->runner->calls[] = ['request.bindTo', $object::class, $strict];

        return $object;
    }

    public function validateDto(object $dto, bool $strict = true): object
    {
        $this->runner->calls[] = ['request.validateDto', $dto::class, $strict];

        return $dto;
    }
}

/**
 * Runs one action through Router::resolveAction against a container that
 * records every interaction, and returns everything that happened: the result,
 * the ordered container calls, model lookups, transactions and any exception.
 * Two implementations of the router are equivalent when this output is equal.
 */
final class ScenarioRunner
{
    /** @var array<int, array<int, mixed>> */
    public array $calls = [];

    /** @var array<string, bool> */
    private array $bound = [];

    /** @var array<string, mixed> */
    private array $concretes = [];

    private RecordingRequest $request;

    /**
     * @param \Closure(class-string): object $mockFactory Creates a PHPUnit test double (TestCase::createStub)
     */
    public function __construct(private \Closure $mockFactory)
    {
        $this->request = new RecordingRequest($this);
    }

    /**
     * @param mixed $callback
     * @param array<string, mixed> $routeParams
     * @param array<int, string> $alreadyBound
     * @param Router|null $router
     * @return array<string, mixed>
     */
    public function run(
        mixed $callback,
        array $routeParams = [],
        array $alreadyBound = [],
        ?Router $router = null,
        bool $modelFound = true
    ): array {
        $this->calls = [];
        $this->bound = array_fill_keys($alreadyBound, true);
        $this->concretes = [];
        F\FakeModel::$log = [];
        F\FakeModel::$found = $modelFound ? new F\FakeModel(['id' => 1]) : null;
        F\FakeForm::$validated = 0;

        $router ??= new RecordingRouter(new Gateway());
        $app = $this->application();

        $previous = null;

        try {
            $previous = Container::getInstance();
        } catch (\Throwable) {
        }

        Container::setInstance($app);

        $output = ['result' => null, 'exception' => null];

        try {
            $method = new \ReflectionMethod($router, 'resolveAction');
            $output['result'] = $method->invoke($router, $callback, $app, $routeParams);
        } catch (\Throwable $e) {
            $output['exception'] = [$e::class, $e->getMessage()];
        } finally {
            $previous !== null ? Container::setInstance($previous) : Container::forgetInstance();
        }

        $output['calls'] = $this->calls;
        $output['models'] = F\FakeModel::$log;
        $output['formsValidated'] = F\FakeForm::$validated;
        $output['transactions'] = $router instanceof RecordingRouter ? $router->transactions : [];

        return $output;
    }

    private function application(): Application
    {
        /** @var Application&\PHPUnit\Framework\MockObject\MockObject $app */
        $app = ($this->mockFactory)(Application::class);

        $app->method('has')->willReturnCallback(function ($key) {
            $this->calls[] = ['has', $key];

            return (bool) ($this->bound[$key] ?? false);
        });

        $app->method('bind')->willReturnCallback(function ($abstract, $concrete = null, $singleton = false) {
            $this->remember('bind', $abstract, $concrete);
        });

        $app->method('singleton')->willReturnCallback(function ($abstract, $concrete = null) {
            $this->remember('singleton', $abstract, $concrete);
        });

        $app->method('make')->willReturnCallback(fn($abstract, array $parameters = []) => $this->build('make', $abstract, $app));
        $app->method('get')->willReturnCallback(fn($abstract, array $parameters = []) => $this->build('get', $abstract, $app));

        return $app;
    }

    private function remember(string $kind, string $abstract, mixed $concrete): void
    {
        $this->calls[] = [$kind, $abstract, $concrete instanceof \Closure ? 'Closure' : $concrete];
        $this->bound[$abstract] = true;
        $this->concretes[$abstract] = $concrete ?? $abstract;
    }

    private function build(string $kind, string $abstract, Application $app): mixed
    {
        $this->calls[] = [$kind, $abstract];

        if ($abstract === 'request') {
            return $this->request;
        }

        if ($abstract === 'abort') {
            return new class {
                public function abort($code, $message = '', array $headers = []): void
                {
                    throw new \RuntimeException("aborted {$code}: {$message}");
                }
            };
        }

        $concrete = $this->concretes[$abstract] ?? $abstract;

        if ($concrete instanceof \Closure) {
            return $concrete($app);
        }

        if (is_string($concrete) && class_exists($concrete)) {
            return new $concrete();
        }

        throw new \RuntimeException("Cannot make [{$abstract}] in the scenario container.");
    }

    /**
     * Every scenario: name => [callback, route params, already bound abstracts].
     *
     * @return array<string, array{0: mixed, 1: array<string, mixed>, 2: array<int, string>, 3?: bool}>
     */
    public static function scenarios(): array
    {
        $c = fn(string $class, string $method) => [$class, $method];

        return [
            'plain action' => [$c(F\PlainController::class, 'index'), [], []],
            'route params typed and defaulted' => [$c(F\ParamsController::class, 'show'), ['id' => 7], []],
            'route params all supplied' => [$c(F\ParamsController::class, 'show'), ['id' => '9', 'slug' => 'abc', 'opt' => 'z'], []],
            'no params' => [$c(F\ParamsController::class, 'none'), [], []],
            'required param missing' => [$c(F\ParamsController::class, 'needsId'), [], []],
            'unmatched route param' => [$c(F\ParamsController::class, 'none'), ['id' => 5, 'extra' => 1], []],
            'class level resolver' => [$c(F\ClassResolverController::class, 'run'), [], []],
            'method level resolvers repeatable' => [$c(F\MethodResolverController::class, 'run'), [], []],
            'class and method resolvers' => [$c(F\BothLevelsController::class, 'run'), [], []],
            'service already bound' => [$c(F\MixedOrderController::class, 'run'), ['a' => 1], [F\Audit::class]],
            'service not bound is auto registered' => [$c(F\MixedOrderController::class, 'run'), ['a' => 1, 'b' => 'given'], []],
            'bind attribute' => [$c(F\BindController::class, 'run'), ['id' => 4], []],
            'bind attribute default' => [$c(F\BindController::class, 'run'), [], []],
            'bind on builtin type' => [$c(F\BindBuiltinController::class, 'run'), ['value' => 'x'], []],
            'bind on untyped param' => [$c(F\BindUntypedController::class, 'run'), [], []],
            'payload loose' => [$c(F\PayloadController::class, 'loose'), [], []],
            'payload strict' => [$c(F\PayloadController::class, 'strict'), [], []],
            'payload validated' => [$c(F\PayloadController::class, 'validated'), [], []],
            'payload on builtin' => [$c(F\PayloadBuiltinController::class, 'run'), ['dto' => 'x'], []],
            'payload class missing' => [$c(F\PayloadMissingClassController::class, 'run'), [], []],
            'model by route key' => [$c(F\ModelController::class, 'byKey'), ['model' => 'my-slug'], []],
            'model by custom column' => [$c(F\ModelController::class, 'byColumn'), ['user' => 'a@b.c'], []],
            'model by primary key' => [$c(F\ModelController::class, 'byPrimary'), ['model' => 12], []],
            'model not found aborts' => [$c(F\ModelController::class, 'orFail'), ['model' => 'gone'], [], false],
            'model not found without exception passes null' => [$c(F\ModelController::class, 'byColumnNullable'), ['user' => 'x'], [], false],
            'model param missing from url' => [$c(F\ModelController::class, 'byKey'), [], []],
            'model on builtin type' => [$c(F\ModelController::class, 'builtin'), ['model' => 1], []],
            'constructor dependencies' => [$c(F\ConstructorController::class, 'run'), [], []],
            'invokable controller' => [F\InvokableController::class, ['id' => 3], []],
            'transaction with options' => [$c(F\TransactionController::class, 'tracked'), ['id' => 2], []],
            'transaction defaults' => [$c(F\TransactionController::class, 'defaults'), [], []],
            'form request' => [$c(F\FormController::class, 'run'), [], []],
            'form request already bound' => [$c(F\FormController::class, 'run'), [], [F\FakeForm::class]],
            'unresolvable class' => [$c(F\UnresolvableController::class, 'run'), [], []],
            'defaults of every kind' => [$c(F\DefaultsController::class, 'run'), [], []],
            'defaults that need reflection' => [$c(F\LazyDefaultController::class, 'run'), [], []],
            'nullable class param' => [$c(F\NullableClassController::class, 'run'), [], []],
            'missing method' => [$c(F\PlainController::class, 'nope'), [], []],
            'missing class' => [$c('Tests\\Router\\ActionPlan\\Fixtures\\NoSuchController', 'index'), [], []],

            'closure with route params' => [fn(int $id, string $name = 'n') => [$id, $name], ['id' => 5], []],
            'closure with service' => [fn(F\Audit $audit, int $id) => [$audit::class, $id], ['id' => 1], []],
            'closure with default' => [fn(int $page = 2) => $page, [], []],
            'closure unresolvable' => [fn(int $id) => $id, [], []],
            'closure with bind' => [fn(#[Bind(concrete: F\Greeter::class, singleton: true)] F\GreeterContract $g) => $g::class, [], []],
            'closure with payload' => [fn(#[BindPayload(strict: true)] F\Dto $dto) => $dto::class, [], []],
            'closure with form request' => [fn(F\FakeForm $form) => $form::class, [], []],
        ];
    }
}
