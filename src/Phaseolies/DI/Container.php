<?php

namespace Phaseolies\DI;

use ArrayAccess;

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
     * The container instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Prevent cloning of the container instance (Singleton pattern).
     */
    public function __clone() {}

    /**
     * Prevent unserialization of the container instance (Singleton pattern).
     */
    public function __wakeup() {}

    /**
     * Checks if an entry exists in the container (ArrayAccess implementation).
     *
     * @param string $offset The identifier to check
     * @return bool True if the container has the entry, false otherwise
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Gets an entry from the container (ArrayAccess implementation).
     *
     * @param string $offset The identifier to retrieve
     * @return mixed The resolved entry
     */
    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Sets an entry in the container (ArrayAccess implementation).
     *
     * @param string $offset The identifier to store
     * @param mixed $value The value or definition to store
     */
    public function offsetSet($offset, $value): void
    {
        $this->bind($offset, $value);
    }

    /**
     * Removes an entry from the container (ArrayAccess implementation).
     *
     * @param string $offset The identifier to remove
     */
    public function offsetUnset($offset): void
    {
        unset(self::$bindings[$offset], self::$instances[$offset]);
    }

    /**
     * Bind a service to the container.
     *
     * @param string $abstract The abstract type or service name.
     * @param callable|string $concrete The concrete implementation or class name.
     * @param bool $singleton Whether the binding should be a singleton.
     * @return void
     */
    public function bind(string $abstract, callable|string $concrete, bool $singleton = false): void
    {
        if (is_string($concrete) && class_exists($concrete)) {
            $concrete = fn() => new $concrete();
        }
        self::$bindings[$abstract] = $concrete;

        if ($singleton) {
            self::$instances[$abstract] = null;
        }
    }

    /**
     * Bind a singleton service to the container.
     *
     * @param string $abstract The abstract type or service name.
     * @param callable|string $concrete The concrete implementation or class name.
     * @return void
     */
    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->bind($abstract, $concrete, true);
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
     * Resolve a service from the container.
     *
     * @param string $abstract The service name or class name.
     * @param array $parameters Additional parameters for the constructor.
     * @return mixed
     */
    public function get(string $abstract, array $parameters = [])
    {
        // If it's a class, check if any instances are registered with class name
        if (class_exists($abstract)) {
            foreach (self::$instances as $key => $instance) {
                if ($instance instanceof $abstract) {
                    return $instance;
                }
            }
        }

        if (isset(self::$instances[$abstract]) && self::$instances[$abstract] !== null) {
            return self::$instances[$abstract];
        }

        if (isset(self::$bindings[$abstract])) {
            $concrete = self::$bindings[$abstract];
            if (is_callable($concrete)) {
                $resolved = $concrete($this, ...$parameters);
                if (array_key_exists($abstract, self::$instances)) {
                    self::$instances[$abstract] = $resolved;
                }
                return $resolved;
            }
            return $concrete;
        }

        if (class_exists($abstract) && !(new \ReflectionClass($abstract))->isAbstract()) {
            return new $abstract(...$parameters);
        }

        throw new \RuntimeException("Target [$abstract] is not bound in container");
    }


    /**
     * Check if the container has a binding for the given service.
     *
     * @param string $key The service name or class name.
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, self::$bindings);
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
     * Set the instance
     * @param self $instance
     * @return void
     */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Normalize the given service name.
     *
     * @param string $service
     * @return string
     */
    protected function normalize(string $service): string
    {
        return ltrim($service, '\\');
    }
}
