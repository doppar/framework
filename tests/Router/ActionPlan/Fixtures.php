<?php

namespace Tests\Router\ActionPlan\Fixtures;

use Phaseolies\Database\Attributes\Transaction;
use Phaseolies\Database\Entity\Attributes\Model as BindModel;
use Phaseolies\Database\Entity\Model;
use Phaseolies\DI\Attributes\Bind;
use Phaseolies\DI\Attributes\Resolver;
use Phaseolies\Http\Requests\Attributes\BindPayload;
use Phaseolies\Http\Validation\Contracts\ValidatesWhenResolved;

/**
 * Controllers, services and fakes that exercise every branch of the router's
 * argument resolution. Actions return a description of what they were given,
 * so a test can compare complete behaviour, not just "did not throw".
 */
final class Describe
{
    /**
     * @param array<int, mixed> $args
     * @return array<int, mixed>
     */
    public static function args(array $args): array
    {
        return array_map(
            fn($arg) => is_object($arg)
                ? ['object' => $arg::class]
                : ['value' => is_array($arg) ? $arg : (is_scalar($arg) || $arg === null ? $arg : gettype($arg))],
            $args
        );
    }
}

interface GreeterContract
{
}

class Greeter implements GreeterContract
{
}

class Audit
{
}

class OtherAudit extends Audit
{
}

class Dto
{
    public string $name = '';
}

enum Mode
{
    case Fast;
    case Slow;
}

class FakeForm implements ValidatesWhenResolved
{
    public static int $validated = 0;

    public function __construct(public mixed $request = null)
    {
    }

    public function resolvedFormRequestValidation()
    {
        self::$validated++;
    }
}

/**
 * A model that never touches a database.
 */
class FakeModel extends Model
{
    /** @var array<int, array<int, mixed>> */
    public static array $log = [];

    public static ?FakeModel $found = null;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public static function find($id, ...$rest)
    {
        self::$log[] = ['find', $id];

        return self::$found;
    }

    public static function where($column = null, $operator = null, $value = null, $boolean = 'and')
    {
        self::$log[] = ['where', $column, $operator];

        return new class {
            public function first()
            {
                FakeModel::$log[] = ['first'];

                return FakeModel::$found;
            }
        };
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getPrimaryKey(): string
    {
        return 'id';
    }
}

class PlainController
{
    public function index(): array
    {
        return ['plain'];
    }
}

class ParamsController
{
    public function show(int $id, string $slug = 'x', ?string $opt = null): array
    {
        return Describe::args(func_get_args());
    }

    public function needsId(int $id): array
    {
        return Describe::args(func_get_args());
    }

    public function none(): array
    {
        return Describe::args(func_get_args());
    }
}

#[Resolver(abstract: GreeterContract::class, concrete: Greeter::class, singleton: true)]
class ClassResolverController
{
    public function run(GreeterContract $greeter): array
    {
        return Describe::args(func_get_args());
    }
}

class MethodResolverController
{
    #[Resolver(abstract: Audit::class, concrete: OtherAudit::class)]
    #[Resolver(abstract: GreeterContract::class, concrete: Greeter::class, singleton: true)]
    public function run(Audit $audit, GreeterContract $greeter): array
    {
        return Describe::args(func_get_args());
    }
}

#[Resolver(abstract: Audit::class, concrete: Audit::class)]
class BothLevelsController
{
    #[Resolver(abstract: GreeterContract::class, concrete: Greeter::class)]
    public function run(Audit $audit, GreeterContract $greeter): array
    {
        return Describe::args(func_get_args());
    }
}

class BindController
{
    public function run(
        #[Bind(concrete: Greeter::class, singleton: true)] GreeterContract $greeter,
        #[Bind(concrete: OtherAudit::class)] Audit $audit,
        int $id = 0
    ): array {
        return Describe::args(func_get_args());
    }
}

class BindBuiltinController
{
    public function run(#[Bind(concrete: Greeter::class)] string $value): array
    {
        return Describe::args(func_get_args());
    }
}

class BindUntypedController
{
    public function run(#[Bind(concrete: Greeter::class)] $value): array
    {
        return Describe::args(func_get_args());
    }
}

class PayloadController
{
    public function loose(#[BindPayload] Dto $dto): array
    {
        return Describe::args(func_get_args());
    }

    public function strict(#[BindPayload(strict: true)] Dto $dto): array
    {
        return Describe::args(func_get_args());
    }

    public function validated(#[BindPayload(strict: true, validate: true)] Dto $dto): array
    {
        return Describe::args(func_get_args());
    }
}

class PayloadBuiltinController
{
    public function run(#[BindPayload] string $dto): array
    {
        return Describe::args(func_get_args());
    }
}

class PayloadMissingClassController
{
    public function run(#[BindPayload] \Tests\Router\ActionPlan\Fixtures\DoesNotExistDto $dto): array
    {
        return Describe::args(func_get_args());
    }
}

class ModelController
{
    public function byKey(#[BindModel] FakeModel $model): array
    {
        return Describe::args(func_get_args());
    }

    public function byColumn(#[BindModel(column: 'email', exception: false)] FakeModel $user): array
    {
        return Describe::args(func_get_args());
    }

    public function byColumnNullable(#[BindModel(column: 'email', exception: false)] ?FakeModel $user): array
    {
        return Describe::args(func_get_args());
    }

    public function byPrimary(#[BindModel(column: 'id')] FakeModel $model): array
    {
        return Describe::args(func_get_args());
    }

    public function orFail(#[BindModel(exception: true)] FakeModel $model): array
    {
        return Describe::args(func_get_args());
    }

    public function builtin(#[BindModel] int $model): array
    {
        return Describe::args(func_get_args());
    }
}

class ConstructorController
{
    public array $seen = [];

    public function __construct(Audit $audit, public int $limit = 5)
    {
        $this->seen = Describe::args(func_get_args());
    }

    public function run(): array
    {
        return ['ctor' => $this->seen, 'args' => Describe::args(func_get_args())];
    }
}

class InvokableController
{
    public function __invoke(int $id): array
    {
        return Describe::args(func_get_args());
    }
}

class TransactionController
{
    #[Transaction(connection: 'analytics', attempts: 3)]
    public function tracked(int $id = 1): array
    {
        return Describe::args(func_get_args());
    }

    #[Transaction]
    public function defaults(): array
    {
        return Describe::args(func_get_args());
    }
}

class FormController
{
    public function run(FakeForm $form): array
    {
        return Describe::args(func_get_args());
    }
}

class UnresolvableController
{
    public function run(\Tests\Router\ActionPlan\Fixtures\DoesNotExistService $service): array
    {
        return Describe::args(func_get_args());
    }
}

class DefaultsController
{
    public const LABEL = 'label';

    public function run(
        array $options = ['a' => 1, 'b' => [true, null]],
        int $size = PHP_INT_SIZE,
        string $label = self::LABEL,
        float $ratio = 0.5,
        ?Audit $audit = null
    ): array {
        return Describe::args(func_get_args());
    }
}

/**
 * A default that cannot be stored as plain data (it holds enum cases), so a
 * compiled plan has to read it from reflection when it is needed.
 */
class LazyDefaultController
{
    public function run(array $modes = [Mode::Fast, Mode::Slow], int $n = 1): array
    {
        return array_map(fn($m) => $m instanceof Mode ? $m->name : $m, func_get_arg(0)) + [1 => func_get_arg(1)];
    }
}

class NullableClassController
{
    public function run(?Audit $audit): array
    {
        return Describe::args(func_get_args());
    }
}

class MixedOrderController
{
    public function run(int $a, Audit $service, string $b = 'B', int $c = 3): array
    {
        return Describe::args(func_get_args());
    }
}

// ---------------------------------------------------------------------------
// Fixtures for planner-level tests
// ---------------------------------------------------------------------------

interface Marker
{
}

interface OtherMarker
{
}

class BaseController
{
    public function inherited(int $id = 1): array
    {
        return Describe::args(func_get_args());
    }
}

class ChildController extends BaseController
{
}

class TypeShapesController
{
    public function run(
        $untyped,
        int $number,
        ?Audit $nullableClass,
        Audit|OtherAudit $union,
        int|string $builtinUnion,
        Marker&OtherMarker $intersection,
        self $self,
        array $items = [],
        ...$rest
    ): void {
    }
}

class StorableDefaultsController
{
    public function run(
        $a = null,
        bool $b = false,
        int $c = -5,
        float $d = 1.5,
        string $e = "quote ' and \\ backslash",
        array $f = ['x' => [1, 2, ['deep' => true]], 3 => null],
        int $g = PHP_INT_MAX,
        string $h = DefaultsController::LABEL
    ): void {
    }
}

#[Resolver(abstract: Marker::class, concrete: Audit::class)]
#[Resolver(abstract: OtherMarker::class, concrete: Audit::class, singleton: true)]
class RichController
{
    public function __construct(private Audit $audit, private int $limit = 3)
    {
    }

    #[Resolver(abstract: GreeterContract::class, concrete: Greeter::class)]
    #[Transaction(connection: 'reports', attempts: 4)]
    public function run(
        #[BindModel(column: 'slug', exception: true)] FakeModel $model,
        #[BindPayload(strict: true, validate: true)] Dto $dto,
        #[Bind(concrete: Greeter::class, singleton: true)] GreeterContract $greeter,
        int $page = 1
    ): void {
    }
}

class CaseInsensitiveAttributeController
{
    #[\phaseolies\di\attributes\resolver(abstract: GreeterContract::class, concrete: Greeter::class)]
    #[\PHASEOLIES\DATABASE\ATTRIBUTES\TRANSACTION(connection: 'shouting', attempts: 2)]
    public function run(
        #[\phaseolies\di\attributes\BIND(concrete: Greeter::class, singleton: true)] GreeterContract $greeter,
        #[\Phaseolies\Http\Requests\Attributes\bindpayload(strict: true)] Dto $dto,
        #[\PHASEOLIES\database\entity\attributes\MODEL(column: 'email')] FakeModel $model
    ): void {
    }
}
