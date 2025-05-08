<?php

namespace Phaseolies\Database\Eloquent;

use Phaseolies\Support\Collection;
use Phaseolies\Database\Eloquent\Query\QueryCollection;
use Phaseolies\Database\Contracts\Support\Jsonable;
use Stringable;
use JsonSerializable;
use ArrayAccess;

abstract class Model implements ArrayAccess, JsonSerializable, Stringable, Jsonable
{
    use QueryCollection;

    /**
     * The name of the database table associated with the model.
     * If not set, it will be inferred from the class name.
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
     * Indicates whether the model should maintain timestamps (`created_at` and `updated_at` fields.).
     *
     * @var bool
     */
    protected $timeStamps = true;

    /**
     * @var array
     * Holds the loaded relationships
     */
    protected $relations = [];

    /**
     * @var string
     * The last relation type that was set
     */
    protected $lastRelationType;

    /**
     * @var string
     * The last related model that was set
     */
    protected $lastRelatedModel;

    /**
     * @var string
     * The last foreign key that was set
     */
    protected $lastForeignKey;

    /**
     * @var string
     * The last local key that was set
     */
    protected $lastLocalKey;

    /**
     * @var string
     * The last related key that was set (for many-to-many)
     */
    protected $lastRelatedKey;

    /**
     * @var string
     * The last pivot table that was set (for many-to-many)
     */
    protected $lastPivotTable;

    /**
     * Model constructor.
     *
     * @param array $attributes Initial attributes to populate the model.
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);

        // Automatically set the table name
        // If not explicitly defined.
        if (!isset($this->table)) {
            $this->table = $this->getTable();
        }
    }

    /**
     * Infers the table name from the class name.
     *
     * @return string The inferred table name.
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
     * Mass-assign attributes to the model.
     *
     * @param array $attributes Key-value pairs of attributes to assign.
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $this->sanitize($value);
        }
    }

    /**
     * Sanitizes a value before assigning it to an attribute.
     * Override this method to implement custom sanitization logic.
     *
     * @param mixed $value The value to sanitize.
     * @return mixed The sanitized value.
     */
    protected function sanitize($value)
    {
        return $value;
    }

    /**
     * Magic setter for assigning values to model attributes.
     *
     * @param string $name The attribute name.
     * @param mixed $value The value to assign.
     */
    public function __set($name, $value)
    {
        $this->attributes[$name] = $this->sanitize($value);
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->{$this->getKeyName()};
    }

    /**
     * Returns an array of attributes that are not marked as unexposable.
     *
     * @return array The visible attributes.
     */
    public function makeVisible()
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
     * @return array The array representation of the model.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Converts the model to a JSON string.
     *
     * @return string The JSON representation of the model.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Checks if an attribute exists (ArrayAccess implementation).
     *
     * @param mixed $offset The attribute name.
     * @return bool True if the attribute exists, false otherwise.
     */
    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Retrieves an attribute value (ArrayAccess implementation).
     *
     * @param mixed $offset The attribute name.
     * @return mixed The attribute value or null if it doesn't exist.
     */
    public function offsetGet($offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    /**
     * Sets an attribute value (ArrayAccess implementation).
     *
     * @param mixed $offset The attribute name.
     * @param mixed $value The value to assign.
     */
    public function offsetSet($offset, $value): void
    {
        $this->attributes[$offset] = $this->sanitize($value);
    }

    /**
     * Unsets an attribute (ArrayAccess implementation).
     *
     * @param mixed $offset The attribute name.
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Delete the model from the database.
     *
     * @return bool True if the deletion was successful, false otherwise.
     */
    public function delete(): bool
    {
        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }

        return static::query()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->delete();
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
     * @param string $related The related model class name
     * @param string $foreignKey The foreign key on the related model
     * @param string $localKey The local key on this model
     * @return \Phaseolies\Database\Eloquent\Builder The query builder for the related model
     */
    public function oneToOne(string $related, string $foreignKey, string $localKey)
    {
        $this->lastRelationType = 'oneToOne';
        $this->lastRelatedModel = $related;
        $this->lastForeignKey = $foreignKey;
        $this->lastLocalKey = $localKey;

        $relatedInstance = app($related);
        return $relatedInstance->query()->where($foreignKey, '=', $this->$localKey);
    }

    /**
     * Define a one-to-many relationship
     *
     * @param string $related The related model class name
     * @param string $foreignKey The foreign key on the related model
     * @param string $localKey The local key on this model
     * @return \Phaseolies\Database\Eloquent\Builder The query builder for the related model
     */
    public function oneToMany(string $related, string $foreignKey, string $localKey)
    {
        $this->lastRelationType = 'oneToMany';
        $this->lastRelatedModel = $related;
        $this->lastForeignKey = $foreignKey;
        $this->lastLocalKey = $localKey;

        $relatedInstance = app($related);
        return $relatedInstance->query()->where($foreignKey, '=', $this->$localKey);
    }

    /**
     * Define a many-to-many relationship
     *
     * @param string $related The related model class name
     * @param string $foreignKey The foreign key on the pivot table (references this model)
     * @param string $relatedKey The related key on the pivot table (references related model)
     * @param string $pivotTable The name of the pivot table
     * @return \Phaseolies\Database\Eloquent\Builder The query builder for the related model
     */
    public function manyToMany(string $related, string $foreignKey, string $relatedKey, string $pivotTable)
    {
        $this->lastRelationType = 'manyToMany';
        $this->lastRelatedModel = $related;
        $this->lastForeignKey = $foreignKey;
        $this->lastRelatedKey = $relatedKey;
        $this->lastPivotTable = $pivotTable;

        $relatedModel = app($related);
        $query = $relatedModel->query();

        $query->setRelationInfo([
            'type' => 'manyToMany',
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
     */
    public function getParentKey()
    {
        return $this->{$this->getLastLocalKey()};
    }

    /**
     * Get the local key for relationships
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
                        case 'oneToOne':
                            $result = $relation->first();
                            $this->setRelation($name, $result);
                            return $result;

                        case 'oneToMany':
                            $results = $relation->get();
                            $this->setRelation($name, $results);
                            return $results;

                        case 'manyToMany':
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
     * @param  string  $key
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
     * @param  array  $relations
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
     * @param  string  $relation
     * @return mixed
     */
    public function getRelationValue(string $relation)
    {
        return $this->getRelation($relation);
    }

    /**
     * Get the authentication key name used for identifying the user.
     * @return string
     */
    public function getAuthKeyName(): string
    {
        return "email";
    }

    /**
     * Convert collection to array
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
     * @return int Number of affected rows (should be 1)
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
     * @return int Number of affected rows (should be 1)
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
