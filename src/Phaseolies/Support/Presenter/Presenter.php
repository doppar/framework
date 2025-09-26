<?php

namespace Phaseolies\Support\Presenter;

use Phaseolies\Database\Eloquent\Model;
use Phaseolies\Database\Eloquent\Builder;
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
     * Access the presenter's attributes.
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->presenter->{$key};
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

        foreach ($data as $key => $value) {
            $data[$key] = $this->processValue($value);
        }

        if (!empty($this->only)) {
            $data = array_intersect_key($data, array_flip($this->only));
        }

        if (!empty($this->except)) {
            $data = array_diff_key($data, array_flip($this->except));
        }

        return $data;
    }


    /**
     * Process individual values to handle collections, builders, etc.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function processValue($value)
    {
        if ($value instanceof \Phaseolies\Support\Collection) {
            return $value->toArray();
        }

        if ($value instanceof \Phaseolies\Database\Eloquent\Builder) {
            return $value->get()->toArray();
        }

        if ($value instanceof \Phaseolies\Database\Eloquent\Model) {
            return $value->toArray();
        }

        if ($value instanceof self || $value instanceof \JsonSerializable) {
            return $value->jsonSerialize();
        }

        if ($value instanceof \stdClass || $value instanceof \ArrayObject) {
            return (array) $value;
        }

        return $value;
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

    /**
     * Create a PresenterBundle from a single model
     *
     * @param Model $model
     * @return mixed
     */
    public static function make(Model $model)
    {
        $presenter = new static($model);

        return $presenter->jsonSerialize();
    }

    /**
     * Create a PresenterBundle for a collection using this presenter
     *
     * @param mixed $collection
     * @return PresenterBundle
     */
    public static function bundle($collection): PresenterBundle
    {
        return new PresenterBundle($collection, static::class);
    }

    /**
     * Create a PresenterBundle from a paginated query
     *
     * @param Builder $query
     * @param int $perPage
     * @return array
     */
    public static function paginate(Builder $query, int $perPage = 15): array
    {
        return static::bundle($query->paginate($perPage))->paginate();
    }
}
