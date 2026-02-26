<?php

namespace Phaseolies\Http;

class InputBag
{
    /**
     * @var array The internal storage for input data.
     */
    private array $data;

    /**
     * InputBag constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Retrieves the value for a given key from the input data.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Sets a value for a given key in the input data.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Checks if a key exists in the input data.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Retrieves all input data as an associative array.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Replaces the entire input data with a new set of data.
     *
     * @param array $data
     * @return void
     */
    public function replace(array $data): void
    {
        $this->data = $data;
    }
}
