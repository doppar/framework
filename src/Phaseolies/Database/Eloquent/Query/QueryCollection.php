<?php

namespace Phaseolies\Database\Eloquent\Query;

use Phaseolies\Support\Collection;
use Phaseolies\Database\Eloquent\Model;
use Phaseolies\Database\Eloquent\Builder;
use Phaseolies\Database\Database;
use Phaseolies\Database\Contracts\Support\Jsonable;
use Carbon\Carbon;

/**
 * The QueryCollection trait provides methods for querying the database
 * and interacting with model collections. It is designed to be used
 * within Eloquent models to enable fluent query building and data retrieval.
 */
trait QueryCollection
{
    /**
     * Creates and returns a new query builder instance for the model.
     *
     * @return \Phaseolies\Database\Eloquent\Builder
     */
    public static function query(): Builder
    {
        $model = new static();

        return new Builder(
            Database::getPdoInstance(),
            $model->table,
            get_class($model),
            $model->pageSize
        );
    }

    /**
     * Retrieves all records from the model's table.
     *
     * @return Collection A collection of all model records.
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * Alias for the `all` method. Retrieves all records from the model's table.
     *
     * @return Collection A collection of all model records.
     */
    public static function get(): Collection
    {
        return static::all();
    }

    /**
     * Finds a model record by its primary key.
     *
     * @param mixed $primaryKey The value of the primary key to search for.
     * @return mixed The model instance or null if no record is found.
     */
    public static function find(mixed $primaryKey)
    {
        return static::query()
            ->where((new static)->primaryKey, '=', $primaryKey)
            ->first();
    }

    /**
     * Returns the total number of records in the model's table.
     *
     * @return int The count of records.
     */
    public static function count(): int
    {
        return static::query()->count();
    }

    /**
     * Converts the model's attributes to an array.
     *
     * @return array The array representation of the model's visible attributes.
     */
    public function toArray(): array
    {
        $attributes = $this->makeVisible();

        // Include loaded relationships
        foreach ($this->relations as $key => $relation) {
            if ($relation === null) {
                $attributes[$key] = null;
            } elseif ($relation instanceof Model) {
                $attributes[$key] = $relation->toArray();
            } elseif ($relation instanceof Collection) {
                $attributes[$key] = $relation->all();
            } else {
                $attributes[$key] = $relation;
            }
        }

        return $attributes;
    }

    /**
     * Pluck an array of values from a single column.
     *
     * @param string $column
     * @return Collection
     */
    public function pluck(string $column): Collection
    {
        $values = [];
        foreach ($this->get() as $item) {
            $values[] = $item->{$column};
        }
        return new Collection($this->modelClass, $values);
    }

    /**
     * Converts the model's attributes to a JSON string.
     *
     * @param int $options Bitmask of JSON encoding options.
     * @return string The JSON representation of the model.
     * @throws \Exception If JSON encoding fails.
     */
    public function toJson($options = 0): string
    {
        try {
            $json = json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $json;
    }

    /**
     * Save the model to the database.
     *
     * @return bool
     */
    public function save(): bool
    {
        $attributes = $this->getCreatableAttributes();

        if (isset($this->attributes[$this->primaryKey])) {
            if ($this->timeStamps) {
                $attributes['updated_at'] = Carbon::now();
            }

            $primaryKeyValue = $this->attributes[$this->primaryKey];
            return $this->query()
                ->where($this->primaryKey, '=', $primaryKeyValue)
                ->update($attributes);
        }

        if ($this->timeStamps) {
            $attributes['created_at'] = Carbon::now();
            $attributes['updated_at'] = Carbon::now();
        }

        $id = $this->query()->insert($attributes);
        if ($id) {
            $this->attributes[$this->primaryKey] = $id;
            return true;
        }

        return false;
    }

    /**
     * Insert multiple records into the database
     *
     * @param array $rows Array of attribute sets
     * @return int Number of inserted rows
     */
    public static function saveMany(array $rows, int $chunkSize = 100): int
    {
        $filteredRows = array_map(function ($row) {
            $model = new static();
            $creatable = $model->creatable;

            if (empty($creatable)) {
                $creatable = array_keys($row);
                if (($key = array_search($model->primaryKey, $creatable)) !== false) {
                    unset($creatable[$key]);
                }
            }

            return array_intersect_key($row, array_flip($creatable));
        }, $rows);

        return static::query()->insertMany($filteredRows, $chunkSize);
    }

    /**
     * Update an existing record or create a new one if it doesn't exist.
     *
     * @param array $attributes The attributes to match against (for finding existing record)
     * @param array $values The values to update or insert
     * @return Model The updated or newly created model instance
     */
    public static function updateOrCreate(array $attributes, array $values = []): Model
    {
        $query = static::query();

        foreach ($attributes as $field => $value) {
            $query->where($field, '=', $value);
        }

        $model = $query->first();

        if ($model) {
            $model->fill($values);
            $model->save();
        } else {
            $model = static::create(array_merge($attributes, $values));
        }

        return $model;
    }

    /**
     * Create a new model instance and save it to the database.
     *
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes): static
    {
        $model = new static();
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    /**
     * Get the attributes that are allowed to be mass-assigned.
     *
     * @return array
     */
    protected function getCreatableAttributes(): array
    {
        $creatableAttributes = [];
        foreach ($this->creatable as $attribute) {
            if (isset($this->attributes[$attribute])) {
                $creatableAttributes[$attribute] = $this->attributes[$attribute];
            }
        }
        return $creatableAttributes;
    }

    /**
     * Filter models based on dynamic conditions
     *
     * @param array|callable $filters Array of field => value pairs or a callback function
     * @return \Phaseolies\Database\Eloquent\Builder
     */
    public static function match(array|callable $filters): Builder
    {
        $query = static::query();

        if (is_callable($filters)) {
            // Handle callback filter
            $filters($query);
        } else {
            // Handle array of filters
            foreach ($filters as $field => $value) {
                if (is_array($value)) {
                    // Handle array conditions (whereIn, etc.)
                    $query->whereIn($field, $value);
                } elseif ($value === null) {
                    // Handle NULL values
                    $query->whereNull($field);
                } elseif ($value instanceof \Closure) {
                    // Handle closure conditions
                    $value($query);
                } else {
                    // Standard equality check
                    $query->where($field, '=', $value);
                }
            }
        }

        return $query;
    }
}
