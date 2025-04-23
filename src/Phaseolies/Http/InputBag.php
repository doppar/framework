<?php

namespace Phaseolies\Http;

/**
 * Class InputBag
 *
 * A simple container for managing input data (e.g., query parameters, form data).
 * Provides methods to retrieve, set, check, and replace input data.
 */
class InputBag
{
    /**
     * @var array The internal storage for input data.
     */
    private array $data;

    /**
     * InputBag constructor.
     *
     * @param array $data Initial data to populate the bag.
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Retrieves the value for a given key from the input data.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value to return if the key does not exist.
     * @return mixed The value associated with the key, or the default value if the key is not found.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Sets a value for a given key in the input data.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to associate with the key.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Checks if a key exists in the input data.
     *
     * @param string $key The key to check.
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Retrieves all input data as an associative array.
     *
     * @return array The entire input data.
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Replaces the entire input data with a new set of data.
     *
     * @param array $data The new data to replace the existing input data.
     */
    public function replace(array $data): void
    {
        $this->data = $data;
    }
}
