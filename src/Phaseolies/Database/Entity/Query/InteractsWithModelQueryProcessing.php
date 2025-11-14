<?php

namespace Phaseolies\Database\Entity\Query;

use Phaseolies\Utilities\Casts\CastToDate;
use Phaseolies\Support\Collection;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Database;

trait InteractsWithModelQueryProcessing
{
    /**
     * @var bool
     */
    protected static bool $isHookShouldBeCalled = true;

    /**
     * Creates and returns a new query builder instance for the model.
     *
     * @param $connection = 'mysql'
     * @return \Phaseolies\Database\Entity\Builder
     */
    public static function query(?string $connection = null): Builder
    {
        $model = new static();

        $connection = $connection ?? $model->connection;

        return new Builder(
            Database::getPdoInstance($connection),
            $model->getTable(),
            static::class,
            $model->pageSize
        );
    }

    /**
     * Disable the execution of model hooks for the current instance.
     *
     * @return self
     */
    public static function withoutHook(): self
    {
        self::$isHookShouldBeCalled = false;

        return app(static::class);
    }

    /**
     * Retrieves all records from the model's table.
     *
     * @return Collection
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * Alias for the `all` method. Retrieves all records from the model's table.
     *
     * @return Collection
     */
    public static function get(): Collection
    {
        return static::all();
    }

    /**
     * Finds a model record by its primary key.
     *
     * @param string|array $primaryKey
     * @return mixed
     */
    public static function find(string|int|array $primaryKey)
    {
        $query = static::query();

        $key = (new static())->getKeyName();

        if (is_array($primaryKey)) {
            $models = $query->whereIn($key, $primaryKey)->get();
            foreach ($models as $model) {
                $model->originalAttributes = $model->attributes;
            }
            return $models;
        }

        $model = $query->where($key, $primaryKey)->first();
        if ($model) {
            $model->originalAttributes = $model->attributes;
        }

        return $model;
    }

    /**
     * Returns the total number of records in the model's table.
     *
     * @return int
     */
    public static function count(): int
    {
        return static::query()->count();
    }

    /**
     * Converts the model's attributes to an array.
     *
     * @return array.
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
     * @param string $value
     * @param string|null $key
     * @return Collection
     */
    public function pluck(string $value, ?string $key = null): Collection
    {
        $results = [];

        foreach ($this->get() as $item) {
            $itemValue = $item->{$value} ?? null;

            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = $item->{$key} ?? null;
                if (!is_null($itemKey)) {
                    $results[$itemKey] = $itemValue;
                } else {
                    $results[] = $itemValue;
                }
            }
        }

        return new Collection($this->modelClass, $results);
    }

    /**
     * Converts the model's attributes to a JSON string.
     *
     * @param int $options
     * @return string
     * @throws \Exception
     */
    public function toJson($options = 0): string
    {
        try {
            $json = json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
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
        static $attributeCache = [];
        $class = static::class;

        if (!array_key_exists($class, $attributeCache)) {
            $attributeCache[$class] = $this->propertyHasAttribute(new static(), 'timeStamps', CastToDate::class);
        }

        $dateTime = $attributeCache[$class]
            ? now()->startOfDay()
            : now();

        $isUpdatable = isset($this->attributes[$this->primaryKey]);

        if ($isUpdatable) {
            $dirtyAttributes = $this->getDirtyAttributes();
            if (!empty($this->creatable)) {
                $dirtyAttributes = array_intersect_key($dirtyAttributes, array_flip($this->creatable));
            }

            if (empty($dirtyAttributes)) {
                return true;
            }

            if ($this->timeStamps) {
                $dirtyAttributes['updated_at'] = $dateTime;
            }

            $primaryKeyValue = $this->attributes[$this->primaryKey];

            if (self::$isHookShouldBeCalled && $this->fireBeforeHooks('updated') === false) {
                return false;
            }

            $response = $this->query()
                ->where($this->primaryKey, $primaryKeyValue)
                ->update($dirtyAttributes);

            if (self::$isHookShouldBeCalled && $response) {
                $this->fireAfterHooks('updated');
                $this->originalAttributes = $this->attributes;
            }

            return $response;
        }

        $attributes = $this->getCreatableAttributes();

        if ($this->timeStamps) {
            $attributes['created_at'] = $dateTime;
            $attributes['updated_at'] = $dateTime;
        }

        if (self::$isHookShouldBeCalled && $this->fireBeforeHooks('created') === false) {
            return false;
        }

        $id = $this->query()->insert($attributes);

        if ($id && self::$isHookShouldBeCalled) {
            $this->fireAfterHooks('created');
        }

        if ($id) {
            $this->attributes[$this->primaryKey] = $id;
            return true;
        }

        return false;
    }

    /**
     * Get model dirty attributes
     *
     * @return array
     */
    public function getDirtyAttributes(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (
                !array_key_exists($key, $this->originalAttributes) ||
                $this->originalAttributes[$key] != $value
            ) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Insert multiple records into the database
     *
     * @param array $rows
     * @return int
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
     * @param array $attributes
     * @param array $values
     * @return Model
     */
    public static function updateOrCreate(array $attributes, array $values = []): Model
    {
        $query = static::query();

        foreach ($attributes as $field => $value) {
            $query->where($field, $value);
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
     * Retrieve the first model matching the attributes, or create it if not found.
     *
     * @param array $attributes
     * @param array $values
     * @return Model
     */
    public static function firstOrCreate(array $attributes, array $values = []): Model
    {
        $query = static::query();

        foreach ($attributes as $field => $value) {
            $query->where($field, $value);
        }

        $model = $query->first();

        if (! $model) {
            $model = static::create(array_merge($attributes, $values));
        }

        return $model;
    }

    /**
     * Update an existing record or ignore
     *
     * @param array $attributes
     * @param array $values
     * @return Model|null
     */
    public static function updateOrIgnore(array $attributes, array $values = []): ?Model
    {
        $query = static::query();

        foreach ($attributes as $field => $value) {
            $query->where($field, $value);
        }

        $model = $query->first();

        if ($model) {
            $model->fill($values);
            $model->save();
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
     * Create a new model instance from another model and save it to the database.
     *
     * @param Model $model
     * @return static
     */
    public static function createFromModel(Model $model): static
    {
        return self::create($model->getAttributes());
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
     * @param array|callable $filters
     * @return \Phaseolies\Database\Entity\Builder
     */
    public static function match(array|callable $filters): Builder
    {
        $query = static::query();

        if (is_callable($filters)) {
            $filters($query);
        } else {
            foreach ($filters as $field => $value) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } elseif ($value === null) {
                    $query->whereNull($field);
                } elseif ($value instanceof \Closure) {
                    $value($query);
                } else {
                    $query->where($field, $value);
                }
            }
        }

        return $query;
    }

    /**
     * Update the model in the database.
     *
     * @param array $attributes
     * @return bool
     */
    public function update(array $attributes): bool
    {
        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }

        if (self::$isHookShouldBeCalled && $this->fireBeforeHooks('updated') === false) {
            return false;
        }

        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        $dirty = $this->getDirtyAttributes();

        if (empty($dirty)) {
            return true;
        }

        if (!empty($this->creatable)) {
            $dirty = array_intersect_key($dirty, array_flip($this->creatable));
        }

        if ($this->usesTimestamps()) {
            $hasCastToDate = $this->propertyHasAttribute(static::class, 'timeStamps', CastToDate::class);
            $dirty['updated_at'] = $hasCastToDate
                ? now()->startOfDay()
                : now();
        }

        $result = static::query()
            ->where($this->primaryKey, $this->attributes[$this->primaryKey])
            ->update($dirty);

        if ($result) {
            if (self::$isHookShouldBeCalled) {
                $this->fireAfterHooks('updated');
            }
            $this->originalAttributes = $this->attributes;
        }

        return $result;
    }

    /**
     * Accesses a private or protected property of a class using reflection.
     *
     * @param string $class
     * @param string $attribute
     * @return mixed
     * @throws \Exception
     */
    protected function getClassProperty(string $class, string $attribute): mixed
    {
        $reflection = new \ReflectionClass($class);

        if ($reflection->hasProperty($attribute)) {
            $property = $reflection->getProperty($attribute);
            $property->setAccessible(true);

            return $property->isStatic()
                ? $property->getValue()
                : $property->getValue(new $this->modelClass());
        }

        throw new \Exception("Property '{$attribute}' does not exist in class '{$class}'.");
    }

    /**
     * Checks whether a class property has a specific attribute.
     *
     * @param object|string $class
     * @param string $attribute
     * @param string $attributeClass
     * @return bool
     * @throws \Exception
     */
    protected function propertyHasAttribute(object|string $class, string $attribute, string $attributeClass): bool
    {
        $reflection = new \ReflectionClass($class);

        if (! $reflection->hasProperty($attribute)) {
            throw new \Exception("Property '{$attribute}' does not exist in class '{$class}'.");
        }

        $property = $reflection->getProperty($attribute);
        $attributes = $property->getAttributes($attributeClass);

        return !empty($attributes);
    }

    /**
     * Handle dynamic static method calls into the model.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        if (method_exists(static::class, $bindMethod = '__' . $method)) {
            return (new static())->$bindMethod(static::query(), ...$parameters);
        }

        return static::query()->$method(...$parameters);
    }

    /**
     * Create a copy of the model instance without the primary key
     *
     * @param array|null $except
     * @return static
     */
    public function fork(?array $except = null): static
    {
        $defaults = [$this->primaryKey];

        $except = array_merge($defaults, (array) $except);

        $attributes = array_diff_key($this->attributes, array_flip($except));

        $replica = new static();
        $replica->fill($attributes);

        foreach ($this->relations as $key => $relation) {
            $replica->setRelation($key, $relation);
        }

        $replica->originalAttributes = array_diff_key($this->originalAttributes, array_flip($except));

        return $replica;
    }
}
