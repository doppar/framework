<?php

namespace Phaseolies\DI;

use ArrayAccess;
use ReflectionClass;
use ReflectionParameter;
use RuntimeException;

class Container implements ArrayAccess
{
    /**
     * Array to hold service definitions.
     *
     * @var array<string, mixed>
     */
    private static array $bindings = [];

    /**
     * Array to hold singleton instances.
     *
     * @var array<string, mixed>
     */
    private static array $instances = [];

    /**
     * Array to track currently resolving classes (for circular dependency detection)
     *
     * @var array<string, bool>
     */
    private array $resolving = [];

    /**
     * The container instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    public function __construct()
    {
        $this->resolving = [];
    }

    /**
     * Prevent cloning of the container instance
     */
    public function __clone() {}

    /**
     * Prevent unserialization of the container instance
     */
    public function __wakeup() {}

    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->bind($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        unset(self::$bindings[$offset], self::$instances[$offset]);
    }

    /**
     * Bind a service to the container.
     *
     * @param string $abstract The abstract type or service name.
     * @param callable|string|null $concrete The concrete implementation or class name.
     * @param bool $singleton Whether the binding should be a singleton.
     * @return void
     */
    public function bind(string $abstract, callable|string|null $concrete = null, bool $singleton = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        self::$bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];

        if ($singleton) {
            self::$instances[$abstract] = null;
        }
    }

    /**
     * Bind a singleton service to the container.
     *
     * @param string $abstract The abstract type or service name.
     * @param callable|string|null $concrete The concrete implementation or class name.
     * @return void
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Bind an instance as a singleton.
     *
     * @param string $abstract The abstract type or service name.
     * @param mixed $instance The instance to bind.
     * @return void
     */
    public function instance(string $abstract, mixed $instance): void
    {
        self::$instances[$abstract] = $instance;

        self::$bindings[$abstract] = [
            'concrete' => fn() => $instance,
            'singleton' => true
        ];
    }

    /**
     * Resolve a service from the container.
     *
     * @template T of object
     * @param class-string<T> $abstract The service name or class name
     * @param array $parameters Additional parameters for the constructor
     * @return T
     * @throws RuntimeException
     */
    public function get(string $abstract, array $parameters = []): mixed
    {
        if (isset($this->resolving[$abstract])) {
            throw new RuntimeException("Circular dependency detected while resolving [{$abstract}]");
        }

        $this->resolving[$abstract] = true;

        try {
            if (class_exists($abstract)) {
                foreach (self::$instances as $instance) {
                    if ($instance instanceof $abstract) {
                        return $instance;
                    }
                }
            }

            if (isset(self::$instances[$abstract]) && self::$instances[$abstract] !== null) {
                return self::$instances[$abstract];
            }

            if (isset(self::$bindings[$abstract])) {
                $binding = self::$bindings[$abstract];
                $resolved = $this->resolveBinding($abstract, $binding, $parameters);

                if ($binding['singleton']) {
                    self::$instances[$abstract] = $resolved;
                }

                return $resolved;
            }

            if (class_exists($abstract)) {
                $resolved = $this->build($abstract, $parameters);
                return $resolved;
            }

            throw new RuntimeException("Target [{$abstract}] is not bound in container and is not a class");
        } finally {
            unset($this->resolving[$abstract]);
        }
    }

    /**
     * Resolve a binding from the container.
     *
     * @param string $abstract
     * @param array $binding
     * @param array $parameters
     * @return mixed
     */
    private function resolveBinding(string $abstract, array $binding, array $parameters): mixed
    {
        $concrete = $binding['concrete'];

        if (is_callable($concrete)) {
            return $concrete($this, $parameters);
        }

        if (is_string($concrete) && class_exists($concrete)) {
            return $this->build($concrete, $parameters);
        }

        return $concrete;
    }

    /**
     * Resolve a class with its dependencies (alias for get)
     *
     * @template T of object
     * @param class-string<T> $abstract The class or interface name
     * @param array $parameters Constructor parameters
     * @return T
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->get($abstract, $parameters);
    }

    /**
     * Build a concrete instance with dependency injection
     *
     * @template T of object
     * @param class-string<T> $concrete The class name
     * @param array $parameters Constructor parameters
     * @return T
     * @throws RuntimeException
     */
    public function build(string $concrete, array $parameters = []): object
    {
        if (is_subclass_of($concrete, \Phaseolies\Database\Eloquent\Model::class)) {
            return new $concrete(...$parameters);
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Target [{$concrete}] is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete(...$parameters);
        }

        $dependencies = $this->resolveDependencies(
            $constructor->getParameters(),
            $parameters,
            $concrete
        );

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies
     *
     * @param ReflectionParameter[] $parameters
     * @param array $primitives
     * @param string $className
     * @return array
     */
    protected function resolveDependencies(array $parameters, array $primitives = [], string $className = ''): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveDependency($parameter, $primitives, $className);
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Resolve a single dependency
     *
     * @param ReflectionParameter $parameter
     * @param array $primitives
     * @param string $className
     * @return mixed
     */
    protected function resolveDependency(ReflectionParameter $parameter, array &$primitives, string $className = ''): mixed
    {
        $paramName = $parameter->getName();
        $paramType = $parameter->getType();

        // Check if we have a primitive value for this parameter
        if (!empty($primitives) && array_key_exists($paramName, $primitives)) {
            return $primitives[$paramName];
        }

        // If it's a class dependency, resolve it
        if ($paramType && !$paramType->isBuiltin()) {
            $typeName = $paramType->getName();
            return $this->get($typeName);
        }

        // Check for variadic parameters
        if ($parameter->isVariadic()) {
            return $primitives;
        }

        // Check if parameter has a default value
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // Check if we can use positional primitives
        if (!empty($primitives)) {
            return array_shift($primitives);
        }

        throw new RuntimeException(
            "Unresolvable dependency resolving [{$parameter}] in class " .
                ($className ?: $parameter->getDeclaringClass()->getName())
        );
    }

    /**
     * Conditionally execute bindings.
     *
     * @param callable|bool $condition A boolean or a function returning a boolean.
     * @return self|null Returns self if condition is true, otherwise null.
     */
    public function when(callable|bool $condition): ?self
    {
        if (is_callable($condition)) {
            $condition = $condition();
        }

        return $condition ? $this : null;
    }

    /**
     * Check if the container has a binding for the given service.
     *
     * @param string $key The service name or class name.
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset(self::$bindings[$key]) || class_exists($key);
    }

    /**
     * Check if the container has a resolved instance.
     *
     * @param string $key
     * @return bool
     */
    public function hasInstance(string $key): bool
    {
        return isset(self::$instances[$key]) && self::$instances[$key] !== null;
    }

    /**
     * Flush the container of all instances and bindings.
     *
     * @return void
     */
    public function flush(): void
    {
        self::$bindings = [];
        self::$instances = [];
        $this->resolving = [];
    }

    /**
     * Get all bindings.
     *
     * @return array
     */
    public function getBindings(): array
    {
        return self::$bindings;
    }

    /**
     * Get all instances.
     *
     * @return array
     */
    public function getInstances(): array
    {
        return self::$instances;
    }

    /**
     * Get the container instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Set the container instance
     *
     * @param self $instance
     * @return void
     */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Forget the container instance
     *
     * @return void
     */
    public static function forgetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Register an existing instance as shared in the container
     *
     * @param string $abstract
     * @param mixed $instance
     * @return void
     */
    public function share(string $abstract, mixed $instance): void
    {
        $this->instance($abstract, $instance);
    }

    /**
     * Extend a binding in the container
     *
     * @param string $abstract
     * @param callable $extender
     * @return void
     */
    public function extend(string $abstract, callable $extender): void
    {
        if (!$this->has($abstract)) {
            throw new RuntimeException("Cannot extend unbound abstract [{$abstract}]");
        }

        $previous = self::$bindings[$abstract];

        self::$bindings[$abstract] = [
            'concrete' => fn(Container $container, array $parameters = []) => $extender($container->resolveBinding($abstract, $previous, $parameters), $container),
            'singleton' => $previous['singleton']
        ];
    }

    /**
     * Alias a type to a different name
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     */
    public function alias(string $abstract, string $alias): void
    {
        self::$bindings[$alias] = [
            'concrete' => fn(Container $container) => $container->get($abstract),
            'singleton' => false
        ];
    }

    /**
     * Call the given callback with dependency injection
     *
     * @param callable $callback
     * @param array $parameters
     * @return mixed
     */
    public function call(callable $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
        } else {
            $reflection = new \ReflectionFunction($callback);
        }

        $dependencies = $this->resolveDependencies(
            $reflection->getParameters(),
            $parameters
        );

        return call_user_func_array($callback, $dependencies);
    }

    /**
     * Check if a binding is a singleton
     *
     * @param string $abstract
     * @return bool
     */
    public function isSingleton(string $abstract): bool
    {
        return isset(self::$bindings[$abstract]) && self::$bindings[$abstract]['singleton'];
    }

    /**
     * Get all registered aliases
     *
     * @return array
     */
    public function getAliases(): array
    {
        return array_filter(self::$bindings, function ($binding) {
            return is_callable($binding['concrete']) && !class_exists($binding['concrete']);
        });
    }

    /**
     * Resolve all dependencies for a given class method
     *
     * @param string $class
     * @param string $method
     * @param array $parameters
     * @return array
     */
    public function resolveMethodDependencies(string $class, string $method, array $parameters = []): array
    {
        $reflection = new \ReflectionMethod($class, $method);

        return $this->resolveDependencies($reflection->getParameters(), $parameters, $class);
    }

    /**
     * Check if the container is currently resolving the given abstract
     *
     * @param string $abstract
     * @return bool
     */
    public function isResolving(string $abstract): bool
    {
        return isset($this->resolving[$abstract]);
    }

    /**
     * Register a service provider
     *
     * @param mixed $provider
     * @return void
     */
    public function register($provider): void
    {
        if (is_string($provider) && class_exists($provider)) {
            $provider = $this->make($provider);
        }

        if (method_exists($provider, 'register')) {
            $provider->register($this);
        }

        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }
    }

    /**
     * Flush all container bindings and instances.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->flush();
    }

    /**
     * Check if the given abstract has been resolved at least once
     *
     * @param string $abstract
     * @return bool
     */
    public function resolved(string $abstract): bool
    {
        return $this->hasInstance($abstract) ||
            (isset(self::$bindings[$abstract]) &&
                self::$bindings[$abstract]['singleton'] &&
                $this->hasInstance($abstract));
    }
}
