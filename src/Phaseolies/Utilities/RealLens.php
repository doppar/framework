<?php

namespace Phaseolies\Utilities;

use Phaseolies\Support\Contracts\Lensable;

class RealLens implements Lensable
{
    /**
     * Retrieve an item from an array using "dot" notation.
     *
     * @param array $array The array to search in
     * @param string|int|null $key The key to search for (can use dot notation for nested arrays)
     * @param mixed $default Default value to return if key not found
     * @return mixed The found value or default if not found
     */
    public function grab(array $array, string|int|null $key, $default = null)
    {
        if ($key === null) return $array;

        $keys = explode('.', (string) $key);

        foreach ($keys as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * @param array $array The array to modify (passed by reference)
     * @param string $key The key to set (can use dot notation for nested arrays)
     * @param mixed $value The value to set
     */
    public function put(array &$array, string $key, $value)
    {
        $keys = explode('.', $key);
        $ref = &$array;

        foreach ($keys as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    /**
     * Check if one or more items exist in an array using "dot" notation.
     *
     * @param array $array The array to check
     * @param string|array $keys The key(s) to check for existence
     * @return bool True if all keys exist, false otherwise
     */
    public function got(array $array, string|array $keys): bool
    {
        foreach ((array) $keys as $key) {
            $ref = $array;
            foreach (explode('.', $key) as $segment) {
                if (!is_array($ref) || !array_key_exists($segment, $ref)) {
                    return false;
                }
                $ref = $ref[$segment];
            }
        }
        return true;
    }

    /**
     * Check if at least one of the given items exists in an array using "dot" notation.
     *
     * @param array $array The array to check
     * @param string|array $keys The key(s) to check for existence
     * @return bool True if at least one key exists, false otherwise
     */
    public function some(array $array, string|array $keys): bool
    {
        foreach ((array) $keys as $key) {
            if ($this->got($array, $key)) return true;
        }
        return false;
    }

    /**
     * Remove one or more array items from a given array using "dot" notation.
     *
     * @param array $array The array to modify (passed by reference)
     * @param string|array $keys The key(s) to remove
     */
    public function zap(array &$array, string|array $keys)
    {
        foreach ((array) $keys as $key) {
            $segments = explode('.', $key);
            $ref = &$array;

            while (count($segments) > 1) {
                $segment = array_shift($segments);
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    continue 2;
                }
                $ref = &$ref[$segment];
            }

            unset($ref[array_shift($segments)]);
        }
    }

    /**
     * Pluck an array of values from an array of arrays or objects.
     *
     * @param array $array The array to pluck from
     * @param string $value The value to pluck (can use dot notation)
     * @param string|null $key Optional key to use as array keys in result
     * @return array The plucked values
     */
    public function pick(array $array, string $value, ?string $key = null): array
    {
        $results = [];
        foreach ($array as $item) {
            $val = $this->grab($item, $value);
            if ($key !== null) {
                $k = $this->grab($item, $key);
                $results[$k] = $val;
            } else {
                $results[] = $val;
            }
        }
        return $results;
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param array $array The array to flatten
     * @param int $depth Maximum depth to flatten (PHP_INT_MAX for unlimited)
     * @return array The flattened array
     */
    public function flat(array $array, int $depth = \PHP_INT_MAX): array
    {
        $result = [];
        $stack = [['array' => $array, 'depth' => $depth]];

        while (!empty($stack)) {
            $current = array_pop($stack);
            foreach ($current['array'] as $item) {
                if (!is_array($item) || $current['depth'] === 0) {
                    $result[] = $item;
                } elseif ($current['depth'] === 1) {
                    $result = array_merge($result, $item);
                } else {
                    $stack[] = ['array' => $item, 'depth' => $current['depth'] - 1];
                }
            }
        }

        return $result;
    }

    /**
     * Get the first element of an array, optionally filtered by a callback.
     *
     * @param array $array The array to search
     * @param callable|null $callback Optional filter callback
     * @param mixed $default Default value if no element found
     * @return mixed The first element or default
     */
    public function head(array $array, ?callable $callback = null, $default = null)
    {
        if ($callback === null) {
            return reset($array) ?: $default;
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) return $value;
        }

        return $default;
    }

    /**
     * Get the last element of an array, optionally filtered by a callback.
     *
     * @param array $array The array to search
     * @param callable|null $callback Optional filter callback
     * @param mixed $default Default value if no element found
     * @return mixed The last element or default
     */
    public function tail(array $array, ?callable $callback = null, $default = null)
    {
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }

        foreach (array_reverse($array, true) as $key => $value) {
            if ($callback($value, $key)) return $value;
        }

        return $default;
    }

    /**
     * Flatten a multi-dimensional array into a single level (shallow flatten).
     *
     * @param array $array The array to flatten
     * @return array The flattened array
     */
    public function squash(array $array): array
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item)) {
                foreach ($item as $v) {
                    $result[] = $v;
                }
            }
        }
        return $result;
    }

    /**
     * Return only the specified keys from the array.
     *
     * @param array $array The source array
     * @param array $keys The keys to keep
     * @return array The filtered array
     */
    public function keep(array $array, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                $result[$key] = $array[$key];
            }
        }
        return $result;
    }

    /**
     * Remove the specified keys from the array.
     *
     * @param array $array The source array
     * @param array $keys The keys to remove
     * @return array The filtered array
     */
    public function drop(array $array, array $keys): array
    {
        foreach ($keys as $key) {
            unset($array[$key]);
        }
        return $array;
    }

    /**
     * Determine if the array is associative (non-sequential).
     *
     * @param array $array The array to check
     * @return bool True if associative, false if sequential
     */
    public function assoc(array $array): bool
    {
        if ([] === $array) return false;
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Filter the array using the given callback.
     *
     * @param array $array The array to filter
     * @param callable $callback The filter callback
     * @return array The filtered array
     */
    public function whr(array $array, callable $callback): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * If the given value is not an array, wrap it in one.
     *
     * @param mixed $value The value to wrap
     * @return array The wrapped value
     */
    public function wrap($value): array
    {
        if (is_null($value)) return [];
        return is_array($value) ? $value : [$value];
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param array $array The array to flatten
     * @param string $prepend String to prepend to flattened keys
     * @return array The flattened array
     */
    public function dot(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            $fullKey = $prepend . $key;

            if (is_array($value) && $this->assoc($value)) {
                $results += $this->dot($value, $fullKey . '.');
            } else {
                $results[$fullKey] = $value;
            }
        }

        return $results;
    }

    /**
     * Convert a flattened "dot" notation array into an expanded array.
     *
     * @param array $array The array to expand
     * @return array The expanded array
     */
    public function undot(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $this->put($result, $key, $value);
        }

        return $result;
    }

    /**
     * Randomly shuffle the values of the given array.
     *
     * @param array $array The array to shuffle
     * @return array The shuffled array
     */
    public function rand(array $array): array
    {
        $array = array_values($array);
        for ($i = count($array) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
        }
        return $array;
    }
}
