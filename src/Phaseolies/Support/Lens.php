<?php

namespace Phaseolies\Support;

use Phaseolies\Utilities\RealLens;
use Phaseolies\Support\Contracts\Lensable;

/**
 * Static proxy class for array manipulation operations.
 *
 * Provides a fluent, static interface to the RealLens implementation while handling
 * reference parameters properly. This class serves as a facade that:
 *
 * 1. Manages a singleton instance of RealLens
 * 2. Proxies method calls to the implementation
 * 3. Special-cases reference-based methods (put/zap)
 *
 * @method mixed grab(array $array, string|int|null $key, mixed $default = null) Get array value using dot notation
 * @method array put(array &$array, string $key, mixed $value) Set array value using dot notation (modifies by reference)
 * @method bool got(array $array, string|array $keys) Check if key(s) exist in array
 * @method bool some(array $array, string|array $keys) Check if any key exists in array
 * @method void zap(array &$array, string|array $keys) Remove array items by key (modifies by reference)
 * @method array pick(array $array, string $value, string|null $key = null) Pluck values from array of arrays
 * @method array flat(array $array, int $depth = PHP_INT_MAX) Flatten multi-dimensional array
 * @method mixed head(array $array, ?callable $callback = null, mixed $default = null) Get first array element
 * @method mixed tail(array $array, ?callable $callback = null, mixed $default = null) Get last array element
 * @method array squash(array $array) Flatten array of arrays (single level)
 * @method array keep(array $array, array $keys) Keep only specified keys
 * @method array drop(array $array, array $keys) Remove specified keys
 * @method bool assoc(array $array) Check if array is associative
 * @method array wher(array $array, callable $callback) Filter array using callback
 * @method array wrap(mixed $value) Ensure value is wrapped in array
 * @method array dot(array $array, string $prepend = '') Flatten array to dot notation
 * @method array undot(array $array) Convert dot notation to nested array
 * @method array rand(array $array) Shuffle array values
 *
 * @see \Phaseolies\Utilities\RealLens The underlying implementation
 * @package Phaseolies\Support
 */

class Lens
{
    /**
     * Singleton instance of the Lensable implementation
     * @var Lensable|null
     */
    private static ?Lensable $instance = null;

    /**
     * Get or create the Lensable instance
     *
     * @return Lensable
     */
    private static function make(): Lensable
    {
        if (self::$instance === null) self::$instance = new RealLens();

        return self::$instance;
    }

    /**
     * Handle static method calls
     *
     * Proxies calls to the implementation instance with special handling for:
     * - put(): Modifies array by reference
     * - zap(): Modifies array by reference
     *
     * @param string $method Method name
     * @param array $args Method arguments
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::make();

        if (!method_exists($instance, $method)) {
            throw new \BadMethodCallException(
                sprintf('Method %s::%s does not exist', get_class(new static), $method),
                500
            );
        }

        if (in_array($method, ['put', 'zap'])) {
            if (!empty($args) && is_array($args[0])) {
                $array = &$args[0];

                $instance->$method($array, ...array_slice($args, 1));

                return $array;
            }
        }

        return $instance->$method(...$args);
    }
}
