<?php

namespace Phaseolies\Database\Entity;

use Stringable;
use Phaseolies\Support\Collection;
use Phaseolies\Database\Entity\Query\InteractsWithModelQueryProcessing;
use Phaseolies\Database\Entity\Hooks\HookHandler;
use Phaseolies\Database\Database;
use Phaseolies\Database\Contracts\Support\Jsonable;
use PDO;
use JsonSerializable;
use ArrayAccess;

abstract class Model implements ArrayAccess, JsonSerializable, Stringable, Jsonable
{
    use InteractsWithModelQueryProcessing;

    /**
     * The name of the database table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the model. Defaults to 'id'.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The model's attributes (key-value pairs).
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Attributes that are allowed to be mass-assigned.
     *
     * @var array
     */
    protected $creatable = [];

    /**
     * Attributes that should not be exposed when serializing the model.
     *
     * @var array
     */
    protected $unexposable = [];

    /**
     * The number of items to show per page for pagination.
     *
     * @var int
     */
    protected $pageSize = 15;

    /**
     * Indicates whether the model should maintain timestamps
     *
     * @var bool
     */
    protected $timeStamps = true;

    /**
     * Holds the loaded relationships
     *
     * @var array
     */
    protected $relations = [];

    /**
     * The last relation type that was set
     *
     * @var string
     */
    protected $lastRelationType;

    /**
     * The last related model that was set
     *
     * @var string
     */
    protected $lastRelatedModel;

    /**
     * The last foreign key that was set
     *
     * @var string
     */
    protected $lastForeignKey;

    /**
     * The last local key that was set
     *
     * @var string
     */
    protected $lastLocalKey;

    /**
     * The last related key that was set (for many-to-many)
     *
     * @var string
     */
    protected $lastRelatedKey;

    /**
     * The last pivot table that was set (for many-to-many)
     *
     * @var string
     */
    protected $lastPivotTable;

    /**
     * Array of registered hooks for the model
     *
     * @var array
     */
    protected $hooks = [];

    /**
     * Stores the original attribute values before any modifications
     *
     * @var array
     */
    protected $originalAttributes = [];

    /**
     * The database connection name for the model.
     *
     * @var string|null
     */
    protected $connection = null;

    /**
     * Model constructor.
     *
     * @param array $attributes Initial attributes to populate the model.
     */
    public function __construct(array $attributes = [])
    {
        static $bootInit = [];
        static $bootComplete = [];

        $class = static::class;

        if (!isset($bootInit[$class])) {
            $this->registerHooks();
            HookHandler::execute('booting', $this);
            $bootInit[$class] = true;
        }

        $this->fill($attributes);
        $this->originalAttributes = $this->attributes;

        if (!isset($this->table)) {
            $this->table = $this->getTable();
        }

        if (!isset($bootComplete[$class])) {
            HookHandler::execute('booted', $this);
            $bootComplete[$class] = true;
        }
    }

    /**
     * Register hooks defined in the model
     *
     * @return void
     */
    protected function registerHooks(): void
    {
        if (!empty($this->hooks)) {
            HookHandler::register(static::class, $this->hooks);
        }
    }

    /**
     * Execute before hooks
     *
     * @param string $event
     * @return bool
     */
    protected function fireBeforeHooks(string $event): bool
    {
        HookHandler::execute('before_' . $event, $this);

        return true;
    }

    /**
     * Execute after hooks
     *
     * @param string $event
     * @return void
     */
    protected function fireAfterHooks(string $event): void
    {
        HookHandler::execute('after_' . $event, $this);
    }

    /**
     * Sets the original attribute values for the model
     *
     * @param array $attributes
     * @return void
     */
    public function setOriginalAttributes(array $attributes): void
    {
        $this->originalAttributes = $attributes;
    }

    /**
     * Gets all original attribute values
     *
     * @return array
     */
    public function getOriginalAttributes(): array
    {
        return $this->originalAttributes;
    }

    /**
     * Gets a single original attribute value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getOriginal(string $key, $default = null)
    {
        return $this->originalAttributes[$key] ?? $default;
    }

    /**
     * Get all model attributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the route key name
     *
     * @return int|string
     */
    public function getRouteKeyName(): int|string
    {
        return $this->primaryKey;
    }

    /**
     * Check the attribute is dirty or not
     *
     * @param string $key
     * @return bool
     */
    public function isDirtyAttr(string $key): bool
    {
        return array_key_exists($key, $this->getDirtyAttributes());
    }

    /**
     * Dynamically sets the table name for the model.
     *
     * @param string $table
     * @return void
     */
    public function setTable(string $table)
    {
        $this->table = $table;
    }

    /**
     * Infers the table name from the class name.
     *
     * @return string
     */
    public function getTable()
    {
        if (isset($this->table)) {
            return strtolower($this->table);
        };

        $className = get_class($this);
        $className = substr($className, strrpos($className, '\\') + 1);

        return strtolower($className);
    }

    /**
     * Get the database connection for the model.
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return Database::getPdoInstance($this->connection);
    }

    /**
     * Begin querying the model on a given connection.
     *
     * @param string|null $connection
     * @return Builder
     */
    public static function connection(?string $connection = null): Builder
    {
        $instance = new static();

        $instance->connection = $connection;

        return $instance->newQuery();
    }

    /**
     * Get a new query builder for the model's table.
     *
     * @return Builder
     */
    public function newQuery(): Builder
    {
        return new Builder(
            $this->getConnection(),
            $this->getTable(),
            static::class,
            $this->pageSize
        );
    }

    /**
     * Mass-assign attributes to the model.
     *
     * @param array $attributes
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    /**
     * Set a single attribute value on the model.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        $value = $this->sanitize($value);

        // Always track original value, when first setting
        if (!array_key_exists($key, $this->originalAttributes)) {
            $this->originalAttributes[$key] = $this->attributes[$key] ?? null;
        }

        $this->attributes[$key] = $value;
    }

    /**
     * The sanitize method should be used for data normalization
     * Override this method to implement custom normalization logic.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function sanitize($value)
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                $value = null;
            }
        }

        return $value;
    }

    /**
     * Magic setter for assigning values to model attributes.
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->setAttribute($name, $this->sanitize($value));
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->{$this->getKeyName()};
    }

    /**
     * Returns an array of attributes that are not marked as unexposable.
     *
     * @return array
     */
    public function makeVisible(): array
    {
        $visibleAttributes = [];

        foreach ($this->attributes as $key => $value) {
            if (!in_array($key, $this->unexposable)) {
                $visibleAttributes[$key] = $value;
            }
        }

        return $visibleAttributes;
    }

    /**
     * Get the data except unexposed attributes
     *
     * @param array $attributes
     * @return self
     */
    public function makeHidden(array $attributes): self
    {
        $this->unexposable = array_merge($this->unexposable, $attributes);

        return $this;
    }

    /**
     * Serializes the model to an array for JSON representation.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Converts the model to a JSON string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Checks if an attribute exists (ArrayAccess implementation).
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Retrieves an attribute value (ArrayAccess implementation).
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    /**
     * Sets an attribute value (ArrayAccess implementation).
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unsets an attribute (ArrayAccess implementation).
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Delete the model from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (self::$isHookShouldBeCalled && $this->fireBeforeHooks('deleted') === false) {
            return false;
        }

        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }

        $result = static::query()
            ->where($this->primaryKey, $this->attributes[$this->primaryKey])
            ->delete();

        if ($result && self::$isHookShouldBeCalled) {
            $this->fireAfterHooks('deleted');
        }

        return $result;
    }

    /**
     * Get the last related key
     *
     * @return string
     */
    public function getLastRelatedKey(): ?string
    {
        return $this->lastRelatedKey;
    }

    /**
     * Get the last pivot table
     *
     * @return string
     */
    public function getLastPivotTable(): ?string
    {
        return $this->lastPivotTable;
    }

    /**
     * Define a one-to-one relationship
     *
     * @param string $related
     * @param string $foreignKey
     * @param string $localKey
     * @return \Phaseolies\Database\Entity\Builder
     */
    public function linkOne(string $related, string $foreignKey, string $localKey)
    {
        $this->lastRelationType = 'linkOne';
        $this->lastRelatedModel = $related;
        $this->lastForeignKey = $foreignKey;
        $this->lastLocalKey = $localKey;

        $relatedInstance = app($related);

        return $relatedInstance->query()->where($foreignKey, '=', $this->$localKey);
    }

    /**
     * Define a one-to-one relationship
     *
     * @param string $related
     * @param string $foreignKey
     * @param string $localKey
     * @return \Phaseolies\Database\Entity\Builder
     */
    public function bindTo(string $related, string $foreignKey, string $localKey)
    {
        $this->lastRelationType = 'bindTo';
        $this->lastRelatedModel = $related;
        $this->lastForeignKey = $foreignKey;
        $this->lastLocalKey = $localKey;

        $relatedInstance = app($related);

        return $relatedInstance->query()->where($foreignKey, '=', $this->$localKey);
    }

    /**
     * Define a one-to-many relationship
     *
     * @param string $related
     * @param string $foreignKey
     * @param string $localKey
     * @return \Phaseolies\Database\Entity\Builder
     */
    public function linkMany(string $related, string $foreignKey, string $localKey)
    {
        $this->lastRelationType = 'linkMany';
        $this->lastRelatedModel = $related;
        $this->lastForeignKey = $foreignKey;
        $this->lastLocalKey = $localKey;

        $relatedInstance = app($related);

        return $relatedInstance->query()->where($foreignKey, '=', $this->$localKey);
    }

    /**
     * Define a many-to-many relationship
     *
     * @param string $related
     * @param string $foreignKey
     * @param string $relatedKey
     * @param string $pivotTable
     * @return \Phaseolies\Database\Entity\Builder
     */
    public function bindToMany(string $related, string $foreignKey, string $relatedKey, string $pivotTable)
    {
        $this->lastRelationType = 'bindToMany';
        $this->lastRelatedModel = $related;
        $this->lastForeignKey = $foreignKey;
        $this->lastRelatedKey = $relatedKey;
        $this->lastPivotTable = $pivotTable;

        $relatedModel = app($related);
        $query = $relatedModel->query();

        $query->setRelationInfo([
            'type' => 'bindToMany',
            'foreignKey' => $foreignKey,
            'relatedKey' => $relatedKey,
            'pivotTable' => $pivotTable,
            'parentKey' => $this->getKey()
        ]);

        return $query;
    }

    /**
     * Get the last relation type
     *
     * @return string
     */
    public function getLastRelationType(): string
    {
        return $this->lastRelationType;
    }

    /**
     * Get the last related model
     *
     * @return string
     */
    public function getLastRelatedModel(): string
    {
        return $this->lastRelatedModel;
    }

    /**
     * Get the last foreign key
     *
     * @return string
     */
    public function getLastForeignKey(): string
    {
        return $this->lastForeignKey;
    }

    /**
     * Get the parent key value for relationships
     *
     * @return string
     */
    public function getParentKey(): string
    {
        return $this->{$this->getLastLocalKey()};
    }

    /**
     * Get the local key for relationships
     *
     * @return string
     */
    public function getLastLocalKey(): string
    {
        return $this->lastLocalKey ?? $this->primaryKey;
    }

    /**
     * Set a relationship value
     *
     * @param string $relation
     * @param mixed $value
     */
    public function setRelation(string $relation, $value): self
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Get a relationship value
     *
     * @param string $relation
     * @return mixed
     */
    public function getRelation(string $relation)
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * Magic getter for accessing model attributes and relationships
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        try {
            if (array_key_exists($name, $this->attributes)) {
                return $this->attributes[$name];
            }

            if (array_key_exists($name, $this->relations)) {
                return $this->relations[$name];
            }

            if (method_exists($this, $name)) {
                $relation = $this->$name();

                if ($relation instanceof Builder) {
                    $relationType = $this->getLastRelationType();

                    switch ($relationType) {
                        case 'linkOne':
                            $result = $relation->first();
                            $this->setRelation($name, $result);
                            return $result;

                        case 'bindTo':
                            $result = $relation->first();
                            $this->setRelation($name, $result);
                            return $result;

                        case 'linkMany':
                            $results = $relation->get();
                            $this->setRelation($name, $results);
                            return $results;

                        case 'bindToMany':
                            $relatedModel = app($this->getLastRelatedModel());
                            $pivotColumns = app('db')->getTableColumns($this->getLastPivotTable());
                            $pivotTable = $this->getLastPivotTable();
                            $pivotSelects = array_map(function ($column) use ($pivotTable) {
                                return "{$pivotTable}.{$column} as pivot_{$column}";
                            }, $pivotColumns);

                            $query = $relatedModel->query()
                                ->select(array_merge(
                                    ["{$relatedModel->getTable()}.*"],
                                    $pivotSelects
                                ))
                                ->join(
                                    $this->getLastPivotTable(),
                                    "{$this->getLastPivotTable()}.{$this->getLastRelatedKey()}",
                                    '=',
                                    "{$relatedModel->getTable()}.{$relatedModel->getKeyName()}"
                                )
                                ->where("{$this->getLastPivotTable()}.{$this->getLastForeignKey()}", '=', $this->getKey());

                            $results = $query->get();
                            $grouped = [];
                            foreach ($results as $result) {
                                $pivot = [];
                                foreach ($pivotColumns as $column) {
                                    $pivot[$column] = $result["pivot_{$column}"];
                                    unset($result["pivot_{$column}"]);
                                }
                                $pivotObj = (object) $pivot;
                                $result->pivot = $pivotObj;
                                $grouped[$pivot[$this->getLastForeignKey()]][] = $result;
                            }
                            $this->setRelation($name, $grouped);

                            return $results;
                    }
                }

                return $relation;
            }

            if (!isset($this->attributes[$name])) {
                throw new \Exception("Property or relation '$name' does not exist on " . static::class);
            }

            return $this->attributes[$name];
        } catch (\Throwable $th) {
            return;
        }
    }

    /**
     * Determine if the given relation is loaded.
     *
     * @param string $key
     * @return bool
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Get all the loaded relations for the instance.
     *
     * @return array
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Set the entire relations array on the model.
     *
     * @param array $relations
     * @return $this
     */
    public function setRelations(array $relations): self
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Get a specified relationship.
     *
     * @param string $relation
     * @return mixed
     */
    public function getRelationValue(string $relation)
    {
        return $this->getRelation($relation);
    }

    /**
     * Get the authentication key name used for identifying the user.
     *
     * @return string
     */
    public function getAuthKeyName(): string
    {
        return "email";
    }

    /**
     * Check is the model usage timestamps
     *
     * @return bool
     */
    public function usesTimestamps(): bool
    {
        return $this->timeStamps;
    }

    /**
     * Convert collection to array
     *
     * @return array
     */
    public function toArray(): array
    {
        $attributes = $this->makeVisible();

        foreach ($this->relations as $key => $relation) {
            if ($relation instanceof Model) {
                $attributes[$key] = $relation->toArray();
            } elseif ($relation instanceof Collection) {
                $attributes[$key] = $relation->map(function ($item) {
                    $result = $item instanceof Model ? $item->toArray() : (array)$item;
                    if (isset($item->pivot_data)) {
                        $result['pivot'] = (array)$item->pivot_data;
                        unset($result['pivot_data']);
                    }
                    return $result;
                })->all();
            } else {
                $attributes[$key] = $relation;
            }
        }

        return $attributes;
    }

    /**
     * Increment a column's value for this model
     *
     * @param string $column
     * @param int $amount
     * @param array $extra
     * @return int
     */
    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        $result = $this->query()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->increment($column, $amount, $extra);

        // Refresh model attributes if update was successful
        if ($result > 0) {
            $this->$column += $amount;
            foreach ($extra as $key => $value) {
                $this->$key = $value;
            }
        }

        return $result;
    }

    /**
     * Decrement a column's value for this model
     *
     * @param string $column
     * @param int $amount
     * @param array $extra
     * @return int
     */
    public function decrement(string $column, int $amount = 1, array $extra = []): int
    {
        $result = $this->query()
            ->where($this->getKeyName(), '=', $this->getKey())
            ->decrement($column, $amount, $extra);

        // Refresh model attributes if update was successful
        if ($result > 0) {
            $this->$column -= $amount;
            foreach ($extra as $key => $value) {
                $this->$key = $value;
            }
        }

        return $result;
    }
}
