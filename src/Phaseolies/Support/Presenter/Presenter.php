<?php

namespace Phaseolies\Support\Presenter;

use JsonSerializable;

abstract class Presenter implements JsonSerializable
{
    /**
     * The underlying data or object being presented
     *
     * @var mixed
     */
    protected mixed $presenter;


    /**
     * List of fields to exclude from output
     *
     * @var array
     */
    protected array $except = [];

    /**
     * List of fields to include in output
     *
     * @var array
     */
    protected array $only = [];

    /**
     * Whether to use lazy evaluation mode
     *
     * @var bool
     */
    protected bool $lazy = false;

    /**
     * Initialize with the object or data to present
     *
     * @param mixed $presenter The raw data source (model, array, etc.)
     */
    public function __construct(mixed $presenter)
    {
        $this->presenter = $presenter;
    }

    /**
     * Specify fields to exclude from the output
     *
     * @param string|array ...$fields
     * @return self
     */
    public function except(array|string ...$fields): self
    {
        $fields = count($fields) === 1 && is_array($fields[0])
            ? $fields[0]
            : $fields;

        $this->except = [...$this->except, ...$fields];

        return $this;
    }

    /**
     * Specify fields to include in the output
     *
     * @param string|array ...$fields
     * @return self
     */
    public function only(array|string ...$fields): self
    {
        $fields = count($fields) === 1 && is_array($fields[0])
            ? $fields[0]
            : $fields;

        $this->only = [...$this->only, ...$fields];

        return $this;
    }

    /**
     * Enable or disable lazy mode
     *
     * @param bool $lazy
     * @return self
     */
    public function lazy(bool $lazy = true): self
    {
        $this->lazy = $lazy;

        return $this;
    }

    /**
     * Convert the underlying data into an array
     *
     * @return array
     */
    abstract protected function toArray(): array;

    /**
     * Convert to array for JSON serialization
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        $data = $this->toArray();

        if (!empty($this->only)) {
            $data = array_intersect_key($data, array_flip($this->only));
        }

        if (!empty($this->except)) {
            $data = array_diff_key($data, array_flip($this->except));
        }

        return $data;
    }


    /**
     * Return the default value of the given value
     *
     * @param mixed $value
     * @return mixed
     */
    protected function value($value)
    {
        return $value instanceof \Closure ? $value() : $value;
    }

    /**
     * Apply the callback if the given "condition" is true
     *
     * @param bool $condition
     * @param mixed $value
     * @param mixed $default
     * @return mixed
     */
    protected function when(bool $condition, $value, $default = null)
    {
        if ($condition) {
            return $this->value($value);
        }

        return func_num_args() === 3 ? $this->value($default) : null;
    }

    /**
     * Merge the given value if the given "condition" is true
     *
     * @param bool $condition
     * @param array $value
     * @return array
     */
    protected function mergeWhen(bool $condition, array $value): array
    {
        return $condition ? $value : [];
    }

    /**
     * Apply the callback unless the given "condition" is true
     *
     * @param bool $condition
     * @param mixed $value
     * @param mixed $default
     * @return mixed
     */
    protected function unless(bool $condition, $value, $default = null)
    {
        return $this->when(!$condition, $value, $default);
    }
}
