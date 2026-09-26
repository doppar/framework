<?php

namespace Phaseolies\Support\Router\Plan;

use Phaseolies\Database\Attributes\Transaction;
use Phaseolies\Database\Entity\Attributes\Model as BindModel;
use Phaseolies\DI\Attributes\Bind;
use Phaseolies\DI\Attributes\Resolver;
use Phaseolies\Http\Requests\Attributes\BindPayload;

/**
 * Compiles a controller action, or a closure, into an "action plan": plain
 * data describing everything the router needs to resolve its arguments.
 *
 * Reading this with reflection is the expensive part of dispatching a request.
 * A plan is built once (per process, or ahead of time by route:cache) and the
 * router then works from the data alone, without touching the Reflection API
 * or instantiating any attribute.
 *
 * A plan is an array so it can be stored as a PHP file that opcache keeps in
 * shared memory. Its shape:
 *
 *  format       int                    ActionPlanner::FORMAT
 *  class        string|null            Controller class, null for a closure
 *  method       string                 Action method, "{closure}" for a closure
 *  resolvers    list<[abstract, concrete, singleton]>
 *                                      #[Resolver] of the class, then of the method
 *  transaction  null|[connection, attempts]
 *  constructor  function|null          The controller constructor, if any
 *  action       function               The action (or closure)
 *
 * and a "function" is:
 *
 *  declaring    string|null            Class that declares it (for error messages)
 *  name         string                 Function name (for error messages)
 *  names        list<string>           Parameter names
 *  parameters   list<parameter>
 *
 * and a "parameter" is:
 *
 *  name         string
 *  index        int
 *  type         string|null            Class name of a named, non-builtin type
 *  typed        bool                   Has any declared type
 *  builtin      bool                   Builtin, union or intersection type
 *  optional     bool
 *  default      mixed                  Present when optional and storable as data
 *  lazyDefault  bool                   Optional but the default must be read from reflection
 *  model        null|[column, exception]      #[Model]
 *  payload      null|[strict, validate]       #[BindPayload]
 *  bind         null|[concrete, singleton]    #[Bind]
 */
class ActionPlanner
{
    /**
     * Version of the plan layout. Bump it when the shape above changes so plans
     * compiled by an older release are ignored instead of misread.
     */
    public const FORMAT = 1;

    /**
     * Plan a controller action
     *
     * @param string $class
     * @param string $method
     * @return array<string, mixed>
     * @throws \ReflectionException
     * @throws \BadMethodCallException
     */
    public function forAction(string $class, string $method): array
    {
        $reflector = new \ReflectionClass($class);

        if (!$reflector->hasMethod($method)) {
            throw new \BadMethodCallException(
                "Method {$reflector->getName()}::{$method}() does not exist"
            );
        }

        $action = $reflector->getMethod($method);
        $constructor = $reflector->getConstructor();

        [$methodResolvers, $transaction] = $this->methodAttributes($action);

        return [
            'format' => self::FORMAT,
            'class' => $reflector->getName(),
            'method' => $method,
            'resolvers' => array_merge($this->resolvers($reflector), $methodResolvers),
            'transaction' => $transaction,
            'constructor' => $constructor ? $this->describe($constructor) : null,
            'action' => $this->describe($action),
        ];
    }

    /**
     * Plan a closure route
     *
     * @param \Closure $closure
     * @return array<string, mixed>
     */
    public function forClosure(\Closure $closure): array
    {
        return [
            'format' => self::FORMAT,
            'class' => null,
            'method' => '{closure}',
            'resolvers' => [],
            'transaction' => null,
            'constructor' => null,
            'action' => $this->describe(new \ReflectionFunction($closure)),
        ];
    }

    /**
     * @param \ReflectionFunctionAbstract $function
     * @return array<string, mixed>
     */
    private function describe(\ReflectionFunctionAbstract $function): array
    {
        $parameters = [];

        foreach ($function->getParameters() as $parameter) {
            $parameters[] = $this->parameter($parameter);
        }

        return [
            // ReflectionMethod::$class already is the declaring class: no ReflectionClass needed.
            'declaring' => $function instanceof \ReflectionMethod ? $function->class : null,
            'name' => $function->getName(),
            'names' => array_column($parameters, 'name'),
            'parameters' => $parameters,
        ];
    }

    /**
     * @param \ReflectionParameter $parameter
     * @return array<string, mixed>
     */
    private function parameter(\ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();
        $named = $type instanceof \ReflectionNamedType;

        $plan = [
            'name' => $parameter->getName(),
            'index' => $parameter->getPosition(),
            'type' => $named && !$type->isBuiltin() ? $type->getName() : null,
            'typed' => $type !== null,
            // A union or intersection type is not a single class the container can
            // build, so it is treated like a builtin one.
            'builtin' => $type !== null && (!$named || $type->isBuiltin()),
            'optional' => $parameter->isOptional(),
            'lazyDefault' => false,
            'model' => null,
            'payload' => null,
            'bind' => null,
        ];

        if ($plan['optional']) {
            [$default, $plan['lazyDefault']] = $this->defaultOf($parameter);

            if (!$plan['lazyDefault']) {
                $plan['default'] = $default;
            }
        }

        // One lookup for all of the parameter's attributes, matched by name. Most
        // parameters have none, and a filtered lookup per attribute class would
        // repeat the work three times for every one of them.
        foreach ($parameter->getAttributes() as $attribute) {
            $name = $attribute->getName();

            if ($this->is($name, BindModel::class)) {
                $plan['model'] ??= [($instance = $attribute->newInstance())->column, $instance->exception];
            } elseif ($this->is($name, BindPayload::class)) {
                $instance = $attribute->newInstance();
                $plan['payload'] ??= [(bool) ($instance->strict ?? true), (bool) $instance->validate];
            } elseif ($this->is($name, Bind::class)) {
                $plan['bind'] ??= [($instance = $attribute->newInstance())->concrete, $instance->singleton];
            }
        }

        return $plan;
    }

    /**
     * @param \ReflectionParameter $parameter
     * @return array{0: mixed, 1: bool}
     */
    private function defaultOf(\ReflectionParameter $parameter): array
    {
        try {
            $default = $parameter->getDefaultValue();
        } catch (\Throwable) {
            // Unresolvable constant expression: fail when (and only if) the value is needed.
            return [null, true];
        }

        return $this->storable($default) ? [$default, false] : [null, true];
    }

    /**
     * Whether a value survives var_export() and comes back identical
     *
     * @param mixed $value
     * @return bool
     */
    private function storable(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!$this->storable($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value);
    }

    /**
     * @param \ReflectionClass<object> $class
     * @return array<int, array{0: string, 1: string, 2: bool}>
     */
    private function resolvers(\ReflectionClass $class): array
    {
        $resolvers = [];

        foreach ($class->getAttributes(Resolver::class) as $attribute) {
            $resolver = $attribute->newInstance();
            $resolvers[] = [$resolver->abstract, $resolver->concrete, $resolver->singleton];
        }

        return $resolvers;
    }

    /**
     * Read the #[Resolver] and #[Transaction] attributes of an action in one lookup
     *
     * @param \ReflectionMethod $method
     * @return array{0: array<int, array{0: string, 1: string, 2: bool}>, 1: array{0: string|null, 1: int}|null}
     */
    private function methodAttributes(\ReflectionMethod $method): array
    {
        $resolvers = [];
        $transaction = null;

        foreach ($method->getAttributes() as $attribute) {
            $name = $attribute->getName();

            if ($this->is($name, Resolver::class)) {
                $resolver = $attribute->newInstance();
                $resolvers[] = [$resolver->abstract, $resolver->concrete, $resolver->singleton];
            } elseif ($this->is($name, Transaction::class)) {
                $instance = $attribute->newInstance();
                $transaction ??= [$instance->connection, $instance->attempts];
            }
        }

        return [$resolvers, $transaction];
    }

    /**
     * Whether an attribute is the given class. Class names are case-insensitive,
     * and a filtered getAttributes() lookup honours that, so this must too.
     *
     * @param string $attributeName
     * @param class-string $class
     * @return bool
     */
    private function is(string $attributeName, string $class): bool
    {
        return strcasecmp($attributeName, $class) === 0;
    }
}
