<?php

namespace Phaseolies\Database\Eloquent;

use Phaseolies\Support\Collection;
use PDO;
use PDOException;
use PDOStatement;
use Phaseolies\Database\Eloquent\Query\QueryCollection;
use Phaseolies\Support\Facades\URL;

class Builder
{
    use QueryCollection;

    /**
     * @var PDO
     * Holds the PDO instance for database connectivity.
     */
    protected PDO $pdo;

    /**
     * @var string
     * The name of the database table to query.
     */
    protected string $table;

    /**
     * @var array
     * The fields to select in the query. Defaults to ['*'] which selects all columns.
     */
    protected array $fields = ['*'];

    /**
     * @var array
     * The conditions (WHERE clauses) to apply to the query.
     */
    protected array $conditions = [];

    /**
     * @var array
     * The ORDER BY clauses to sort the query results.
     */
    protected array $orderBy = [];

    /**
     * @var array
     * The GROUP BY clauses to group the query results.
     */
    protected array $groupBy = [];

    /**
     * @var int|null
     * The maximum number of records to return. Null means no limit.
     */
    protected ?int $limit = null;

    /**
     * @var int|null
     * The number of records to skip before starting to return records. Null means no offset.
     */
    protected ?int $offset = null;

    /**
     * @var string
     * The class name of the model associated with this query.
     */
    protected string $modelClass;

    /**
     * @var int
     * The number of rows to display per page for pagination.
     */
    protected int $rowPerPage;

    /**
     * @var array
     * Holds the relationships to be eager loaded
     */
    protected array $eagerLoad = [];

    /**
     * @var array
     * The join clauses for the query.
     */
    protected array $joins = [];

    /**
     * @var array
     */
    protected array $relationInfo = [];

    /**
     * @param PDO $pdo
     * @param string $table
     * @param string $modelClass
     * @param int $rowPerPage
     */
    public function __construct(PDO $pdo, string $table, string $modelClass, int $rowPerPage)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->modelClass = $modelClass;
        $this->rowPerPage = $rowPerPage;
    }

    public function setRelationInfo(array $info): self
    {
        $this->relationInfo = $info;
        return $this;
    }

    /**
     * Set the fields to select.
     *
     * @param array $fields
     * @return self
     */
    public function select(array $fields): self
    {
        $this->fields = array_map(function ($field) {
            if (is_string($field) && strpos($field, '(') !== false) {
                return $field;
            }
            return $field;
        }, $fields);
        return $this;
    }

    /**
     * Add a WHERE condition.
     *
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    public function where(string $field, string $operator, $value): self
    {
        $this->conditions[] = ['AND', $field, $operator, $value];
        return $this;
    }

    /**
     * Add an OR WHERE condition.
     *
     * @param string $field
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    public function orWhere(string $field, string $operator, $value): self
    {
        $this->conditions[] = ['OR', $field, $operator, $value];
        return $this;
    }

    /**
     * Add an ORDER BY clause.
     *
     * @param string $field
     * @param string $direction
     * @return self
     */
    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderBy[] = [$field, $direction];
        return $this;
    }

    /**
     * Add a GROUP BY clause.
     *
     * @param string $field
     * @return self
     */
    public function groupBy(string $field): self
    {
        $this->groupBy[] = $field;
        return $this;
    }

    /**
     * Set the LIMIT clause.
     *
     * @param int $limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set the OFFSET clause.
     *
     * @param int $offset
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Generate the SQL query string.
     *
     * @return string
     */
    public function toSql(): string
    {
        $sql = 'SELECT ';

        // Handle SELECT clause
        if (!empty($this->groupBy)) {
            // If GROUP BY is used, ensure all selected fields are either aggregated or in the GROUP BY clause
            $groupedFields = $this->groupBy;
            $nonGroupedFields = array_diff($this->fields, $groupedFields);

            if (in_array('*', $this->fields)) {
                // If '*' is used, replace it with all table columns
                $this->fields = $this->getTableColumns();
                $nonGroupedFields = array_diff($this->fields, $groupedFields);
            }

            if (!empty($nonGroupedFields)) {
                // Check if any non-grouped fields are raw expressions
                $processedFields = [];
                foreach ($this->fields as $field) {
                    if (in_array($field, $groupedFields)) {
                        $processedFields[] = $field;
                    } elseif (strpos($field, '(') !== false) {
                        // This is a raw expression like "SUM(user_id * post_id) as total_sales"
                        $processedFields[] = $field;
                    } else {
                        // Aggregate non-grouped fields
                        $processedFields[] = "MAX($field) AS $field";
                    }
                }
                $sql .= implode(', ', $processedFields);
            } else {
                $sql .= implode(', ', $this->fields);
            }
        } else {
            // If no GROUP BY, use the original SELECT fields
            $sql .= implode(', ', $this->fields);
        }

        $sql .= ' FROM ' . $this->table;

        // Add JOIN clauses
        foreach ($this->joins as $join) {
            $sql .= ' ' . strtoupper($join['type']) . ' JOIN ' . $join['table'] .
                ' ON ' . $join['first'] . ' ' . $join['operator'] . ' ' . $join['second'];
        }

        // Handle WHERE clause
        if (!empty($this->conditions)) {
            $conditionStrings = [];
            foreach ($this->conditions as $condition) {
                if (isset($condition['type']) && ($condition['type'] === 'EXISTS' || $condition['type'] === 'NOT EXISTS')) {
                    $conditionStrings[] = "{$condition['type']} ({$condition['subquery']})";
                } elseif ($condition[2] === 'BETWEEN' || $condition[2] === 'NOT BETWEEN') {
                    $conditionStrings[] = "{$condition[1]} {$condition[2]} ? AND ?";
                } elseif ($condition[2] === 'IS NULL' || $condition[2] === 'IS NOT NULL') {
                    $conditionStrings[] = "{$condition[1]} {$condition[2]}";
                } elseif ($condition[2] === 'IN') {
                    $conditionStrings[] = "{$condition[1]} {$condition[2]} {$condition[4]}";
                } else {
                    $conditionStrings[] = "{$condition[1]} {$condition[2]} ?";
                }
            }
            $sql .= ' WHERE ' . implode(' ', $this->formatConditions($conditionStrings));
        }

        // Handle GROUP BY clause
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        // Handle ORDER BY clause
        if (!empty($this->orderBy)) {
            $orderByStrings = array_map(fn($o) => "$o[0] $o[1]", $this->orderBy);
            $sql .= ' ORDER BY ' . implode(', ', $orderByStrings);
        }

        // Handle LIMIT clause
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        // Handle OFFSET clause
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * Get the list of columns for the table.
     *
     * @return array
     */
    protected function getTableColumns(?string $table = null): array
    {
        $tableName = $table ?? $this->table;
        $stmt = $this->pdo->query("DESCRIBE {$tableName}");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $columns;
    }

    /**
     * Filter records that have at least one related record in the given relationship
     * 
     * @param string $relation
     * @return self
     */
    /**
     * Filter records that have at least one related record in the given relationship
     * 
     * @param string $relation
     * @param callable|null $callback
     * @return self
     */
    public function present(string $relation, ?callable $callback = null): self
    {
        $model = $this->getModel();

        if (!method_exists($model, $relation)) {
            throw new \BadMethodCallException("Relationship {$relation} does not exist on model " . get_class($model));
        }

        // Initialize the relationship to get metadata
        $relationQuery = $model->$relation();
        $relationType = $model->getLastRelationType();
        $relatedModel = $model->getLastRelatedModel();

        // Handle different relationship types
        if ($relationType === 'manyToMany') {
            $pivotTable = $model->getLastPivotTable();
            $foreignKey = $model->getLastForeignKey();
            $relatedKey = $model->getLastRelatedKey();
            $localKey = $model->getLastLocalKey();

            $subquery = "SELECT 1 FROM {$pivotTable} 
                    WHERE {$pivotTable}.{$foreignKey} = {$this->table}.{$localKey}";

            if ($callback) {
                $relatedTable = (new $relatedModel)->getTable();
                $subQueryBuilder = $relatedModel::query();
                $callback($subQueryBuilder);

                // Add conditions to the subquery
                foreach ($subQueryBuilder->conditions as $condition) {
                    if (isset($condition['type'])) continue;

                    $column = $condition[1];
                    $operator = $condition[2];
                    $value = $condition[3];

                    // Handle different operator types
                    if ($value === null) {
                        if ($operator === '=') {
                            $subquery .= " AND {$relatedTable}.{$column} IS NULL";
                        } elseif ($operator === '!=') {
                            $subquery .= " AND {$relatedTable}.{$column} IS NOT NULL";
                        }
                    } elseif ($operator === 'IN') {
                        $escapedValues = array_map([$this->pdo, 'quote'], $value);
                        $subquery .= " AND {$relatedTable}.{$column} IN (" . implode(',', $escapedValues) . ")";
                    } else {
                        $escapedValue = $this->pdo->quote($value);
                        $subquery .= " AND {$relatedTable}.{$column} {$operator} {$escapedValue}";
                    }
                }
            }
        } else {
            // Handle one-to-one and one-to-many relationships
            $foreignKey = $model->getLastForeignKey();
            $localKey = $model->getLastLocalKey();
            $relatedTable = (new $relatedModel)->getTable();

            $subquery = "SELECT 1 FROM {$relatedTable} 
                    WHERE {$relatedTable}.{$foreignKey} = {$this->table}.{$localKey}";

            if ($callback) {
                $subQueryBuilder = $relatedModel::query();
                $callback($subQueryBuilder);

                foreach ($subQueryBuilder->conditions as $condition) {
                    if (isset($condition['type'])) continue;

                    $column = $condition[1];
                    $operator = $condition[2];
                    $value = $condition[3];

                    if ($value === null) {
                        if ($operator === '=') {
                            $subquery .= " AND {$column} IS NULL";
                        } elseif ($operator === '!=') {
                            $subquery .= " AND {$column} IS NOT NULL";
                        }
                    } elseif ($operator === 'IN') {
                        $escapedValues = array_map([$this->pdo, 'quote'], $value);
                        $subquery .= " AND {$column} IN (" . implode(',', $escapedValues) . ")";
                    } else {
                        $escapedValue = $this->pdo->quote($value);
                        $subquery .= " AND {$column} {$operator} {$escapedValue}";
                    }
                }
            }
        }

        $subquery .= ' LIMIT 1';

        $this->conditions[] = [
            'type' => 'EXISTS',
            'subquery' => $subquery,
            'bindings' => []
        ];

        return $this;
    }

    /**
     * Conditionally add clauses to the query
     * Only executes callback when condition is strictly true or has a non-empty value
     * (0 and false will not trigger the callback)
     *
     * @param mixed $condition The condition to evaluate
     * @param callable $callback The callback that adds query constraints
     * @param callable|null $default Optional default callback if condition is false
     * @return self
     */
    public function if($condition, callable $callback, ?callable $default = null): self
    {
        $value = is_callable($condition) ? $condition() : $condition;

        if ($value === true || $this->hasValue($value)) {
            $callback($this);
        } elseif ($default !== null) {
            $default($this);
        }

        return $this;
    }

    /**
     * Check if a value should be considered as having a value
     * Returns false for: null, empty string, false, 0, empty array
     */
    protected function hasValue($value): bool
    {
        if (is_bool($value)) {
            return $value === true;
        }

        if (is_numeric($value)) {
            return $value != 0;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return $value !== null;
    }

    /**
     * Filter records that don't have any related records in the given relationship
     *
     * @param string $relation
     * @return self
     */
    public function absent(string $relation): self
    {
        $model = $this->getModel();

        if (!method_exists($model, $relation)) {
            throw new \BadMethodCallException("Relationship {$relation} does not exist on model " . get_class($model));
        }

        // Initialize the relationship to get metadata
        $relationQuery = $model->$relation();
        $relationType = $model->getLastRelationType();
        $relatedModel = $model->getLastRelatedModel();

        // Handle different relationship types
        if ($relationType === 'manyToMany') {
            $pivotTable = $model->getLastPivotTable();
            $foreignKey = $model->getLastForeignKey();
            $relatedKey = $model->getLastRelatedKey();
            $localKey = $model->getLastLocalKey();

            $subquery = "SELECT 1 FROM {$pivotTable} 
                WHERE {$pivotTable}.{$foreignKey} = {$this->table}.{$localKey}";
        } else {
            // Handle one-to-one and one-to-many relationships
            $foreignKey = $model->getLastForeignKey();
            $localKey = $model->getLastLocalKey();
            $relatedTable = (new $relatedModel)->getTable();

            $subquery = "SELECT 1 FROM {$relatedTable} 
                WHERE {$relatedTable}.{$foreignKey} = {$this->table}.{$localKey}";
        }

        $subquery .= ' LIMIT 1';

        $this->conditions[] = [
            'type' => 'NOT EXISTS',
            'subquery' => $subquery,
            'bindings' => []
        ];

        return $this;
    }

    /**
     * Add a raw WHERE clause to the query
     *
     * @param string $sql
     * @return self
     */
    public function whereRaw(string $sql): self
    {
        $this->conditions[] = ['AND', 'RAW', $sql];
        return $this;
    }

    /**
     * Format conditions with AND/OR.
     *
     * @param array $conditionStrings
     * @return array
     */
    protected function formatConditions(array $conditionStrings): array
    {
        $formattedConditions = [];

        foreach ($this->conditions as $index => $condition) {
            if ($index > 0) {
                $formattedConditions[] = $condition[0];
            }
            $formattedConditions[] = $conditionStrings[$index];
        }

        return $formattedConditions;
    }

    /**
     * Filter records that have at least one related record in the given relationship
     * with optional conditions
     *
     * @param string $relation The relationship name
     * @param callable|null $callback Optional conditions for the related model
     * @return self
     */
    public function ifExists(string $relation, ?callable $callback = null): self
    {
        return $this->present($relation, $callback);
    }

    /**
     * Filter records that don't have any related records in the given relationship
     * with optional conditions
     * 
     * @param string $relation The relationship name
     * @param callable|null $callback Optional conditions for the related model
     * @return self
     */
    public function ifNotExists(string $relation, ?callable $callback = null): self
    {
        return $this->absent($relation, $callback);
    }

    /**
     * Insert multiple records into the database in a single query
     * 
     * @param array $rows Array of arrays containing attribute sets
     * @return int Number of inserted rows
     * @throws PDOException
     */
    public function insertMany(array $rows, int $chunkSize = 100): int
    {
        if (empty($rows)) {
            return 0;
        }

        // Get the columns from the first row
        $columns = array_keys($rows[0]);
        $columnsStr = implode(', ', $columns);

        $totalAffected = 0;
        $chunks = array_chunk($rows, $chunkSize);

        foreach ($chunks as $chunk) {
            $placeholders = [];
            $bindings = [];

            foreach ($chunk as $row) {
                $rowPlaceholders = [];
                foreach ($columns as $column) {
                    if (!array_key_exists($column, $row)) {
                        throw new \InvalidArgumentException("All rows must have the same columns. Missing column: {$column}");
                    }
                    $rowPlaceholders[] = '?';
                    $bindings[] = $row[$column];
                }
                $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
            }

            $placeholdersStr = implode(', ', $placeholders);
            $sql = "INSERT INTO {$this->table} ({$columnsStr}) VALUES {$placeholdersStr}";

            try {
                $stmt = $this->pdo->prepare($sql);
                $index = 1;
                foreach ($bindings as $value) {
                    $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
                }
                $stmt->execute();
                $totalAffected += $stmt->rowCount();
            } catch (PDOException $e) {
                throw new PDOException("Database error: " . $e->getMessage());
            }
        }

        return $totalAffected;
    }

    /**
     * Execute the query and return a collection of models.
     *
     * @return Collection
     * @throws PDOException
     */
    public function get(): Collection
    {
        try {
            $stmt = $this->pdo->prepare($this->toSql());
            $this->bindValues($stmt);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $models = array_map(fn($item) => new $this->modelClass($item), $results);

            $collection = new Collection($this->modelClass, $models);

            // Eager load relationships if any
            if (!empty($this->eagerLoad)) {
                $this->eagerLoadRelations($collection);
            }

            return $collection;
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Attach models to the parent (many-to-many relationship)
     * 
     * @param mixed $ids Single ID or array of IDs to attach
     * @param array $pivotData Additional pivot table data
     * @return int Number of affected rows
     */
    public function link($ids, array $pivotData = []): int
    {
        if (empty($this->relationInfo)) {
            throw new \BadMethodCallException("Relationship metadata not found");
        }

        $pivotTable = $this->relationInfo['pivotTable'];
        $foreignKey = $this->relationInfo['foreignKey'];
        $relatedKey = $this->relationInfo['relatedKey'];
        $parentKey = $this->relationInfo['parentKey'] ?? null;

        if (empty($pivotTable) || empty($foreignKey) || empty($relatedKey)) {
            throw new \RuntimeException("Many-to-many relationship metadata is incomplete");
        }

        if (empty($parentKey)) {
            throw new \RuntimeException(
                "Cannot link - parent model has no primary key value. " .
                    "Did you remember to save the model before creating relationships?"
            );
        }

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $rows = 0;
        $connection = $this->getConnection();

        foreach ($ids as $id) {
            $data = array_merge([
                $foreignKey => $parentKey,
                $relatedKey => $id
            ], $pivotData);

            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO {$pivotTable} ({$columns}) VALUES ({$placeholders})";

            try {
                $stmt = $connection->prepare($sql);
                $stmt->execute(array_values($data));
                $rows += $stmt->rowCount();
            } catch (\PDOException $e) {
                throw new \RuntimeException("Failed to create relationship: " . $e->getMessage());
            }
        }

        return $rows;
    }

    /**
     * Get the db connection
     * @return \PDO
     */
    public function getConnection()
    {
        return $this->pdo;
    }

    /**
     * Detach models from the parent (many-to-many relationship)
     * 
     * @param mixed $ids Single ID or array of IDs to detach (empty for all)
     * @return int Number of affected rows
     */
    public function unlink($ids = null): int
    {
        if (empty($this->relationInfo)) {
            throw new \BadMethodCallException("Relationship metadata not found");
        }

        $pivotTable = $this->relationInfo['pivotTable'];
        $foreignKey = $this->relationInfo['foreignKey'];
        $relatedKey = $this->relationInfo['relatedKey'];
        $parentKey = $this->relationInfo['parentKey'] ?? null;

        if (empty($parentKey)) {
            throw new \RuntimeException("Cannot unlink - parent model has no primary key value");
        }

        $connection = $this->getConnection();
        $sql = "DELETE FROM {$pivotTable} WHERE {$foreignKey} = ?";
        $params = [$parentKey];

        if (!is_null($ids)) {
            if (!is_array($ids)) {
                $ids = [$ids];
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND {$relatedKey} IN ({$placeholders})";
            $params = array_merge($params, $ids);
        }

        try {
            $stmt = $connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            throw new \RuntimeException("Failed to unlink relationship: " . $e->getMessage());
        }
    }

    /**
     * Sync pivot table data with detach mode
     *
     * @param mixed $ids
     * @param bool $detaching
     * @return array
     */
    public function relate($ids, bool $detaching = true): array
    {
        if (empty($this->relationInfo)) {
            throw new \BadMethodCallException("Relationship metadata not found");
        }

        $pivotTable = $this->relationInfo['pivotTable'];
        $foreignKey = $this->relationInfo['foreignKey'];
        $relatedKey = $this->relationInfo['relatedKey'];
        $parentKey = $this->relationInfo['parentKey'] ?? null;

        if (empty($parentKey)) {
            throw new \RuntimeException("Cannot sync - parent model has no primary key value");
        }

        $ids = $this->normalizeSyncIds($ids);
        $currentIds = $this->getCurrentRelatedIds($pivotTable, $foreignKey, $relatedKey, $parentKey);

        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => []
        ];

        if ($detaching) {
            $detachIds = array_diff($currentIds, array_keys($ids));
            if (!empty($detachIds)) {
                $this->unlink($detachIds);
                $changes['detached'] = $detachIds;
            }
        }

        foreach ($ids as $id => $pivotData) {
            if (!in_array($id, $currentIds)) {
                $this->link([$id], $pivotData);
                $changes['attached'][$id] = $pivotData;
            } elseif (!empty($pivotData)) {
                $this->updatePivot($id, $pivotData, $pivotTable, $foreignKey, $relatedKey, $parentKey);
                $changes['updated'][$id] = $pivotData;
            }
        }

        return $changes;
    }

    /**
     * Normalize sync
     *
     * @param mixed $ids
     * @return array
     */
    protected function normalizeSyncIds($ids): array
    {
        if ($ids instanceof \Traversable) {
            $ids = iterator_to_array($ids);
        }

        if (!is_array($ids)) {
            return array_fill_keys((array)$ids, []);
        }

        $normalized = [];
        foreach ($ids as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = $value;
            } else {
                $normalized[$value] = [];
            }
        }

        return $normalized;
    }

    /**
     * Get the current related ids
     *
     * @param string $pivotTable
     * @param string $foreignKey
     * @param string $relatedKey
     * @param mixed $parentKey
     * @return array
     */
    protected function getCurrentRelatedIds(string $pivotTable, string $foreignKey, string $relatedKey, $parentKey): array
    {
        $connection = $this->getConnection();
        $stmt = $connection->prepare("SELECT {$relatedKey} FROM {$pivotTable} WHERE {$foreignKey} = ?");
        $stmt->execute([$parentKey]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * Update the pivot table data
     *
     * @param mixed $id
     * @param array $pivotData
     * @param string $pivotTable
     * @param string $foreignKey
     * @param string $relatedKey
     * @param mixed $parentKey
     * @return int
     */
    protected function updatePivot($id, array $pivotData, string $pivotTable, string $foreignKey, string $relatedKey, $parentKey): int
    {
        $connection = $this->getConnection();
        $sets = [];
        $params = [];

        foreach ($pivotData as $key => $value) {
            $sets[] = "{$key} = ?";
            $params[] = $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : $value;
        }

        $params[] = $parentKey;
        $params[] = $id;

        $sql = "UPDATE {$pivotTable} SET " . implode(', ', $sets) .
            " WHERE {$foreignKey} = ? AND {$relatedKey} = ?";

        $stmt = $connection->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Get the parent key value for the relationship
     */
    protected function getParentKey()
    {
        return $this->getModel()->getParentKey();
    }

    /**
     * Eager load the relationships for the collection
     *
     * @param Collection $collection
     * @return void
     */
    protected function eagerLoadRelations(Collection $collection)
    {
        foreach ($this->eagerLoad as $relation => $constraint) {
            if (str_contains($relation, '.')) {
                $this->loadNestedRelations($collection, $relation, $constraint);
            } else {
                $this->loadRelation($collection, $relation, $constraint);
            }
        }
    }

    /**
     * Load nested relationships (e.g., 'posts.comments')
     *
     * @param Collection $collection
     * @param string $nestedRelation
     * @param callable|null $constraint
     * @return void
     */
    protected function loadNestedRelations(Collection $collection, string $nestedRelation, ?callable $constraint = null)
    {
        $relations = explode('.', $nestedRelation);
        $primaryRelation = array_shift($relations);
        $nestedPath = implode('.', $relations);

        // First load the primary relation
        $this->loadRelation($collection, $primaryRelation);

        // Then load nested relations for each model's primary relation
        $models = $collection->all();

        foreach ($models as $model) {
            if ($model->relationLoaded($primaryRelation)) {
                $related = $model->getRelation($primaryRelation);

                if ($related instanceof Collection) {
                    // If the relation is a collection (oneToMany, manyToMany, etc.)
                    $this->loadNestedRelationsForCollection($related, $nestedPath, $constraint);
                } elseif ($related !== null) {
                    // If the relation is a single model (oneToOne)
                    $this->loadNestedRelationsForModel($related, $nestedPath, $constraint);
                }
            }
        }
    }


    /**
     * Load nested relations for a collection of models
     *
     * @param Collection $collection
     * @param string $nestedPath
     * @param callable|null $constraint
     * @return void
     */
    protected function loadNestedRelationsForCollection(Collection $collection, string $nestedPath, ?callable $constraint = null)
    {
        if (str_contains($nestedPath, '.')) {
            $relations = explode('.', $nestedPath);
            $primaryRelation = array_shift($relations);
            $remainingPath = implode('.', $relations);

            $this->loadRelation($collection, $primaryRelation);

            foreach ($collection->all() as $model) {
                if ($model->relationLoaded($primaryRelation)) {
                    $related = $model->getRelation($primaryRelation);

                    if ($related instanceof Collection) {
                        $this->loadNestedRelationsForCollection($related, $remainingPath, $constraint);
                    } elseif ($related !== null) {
                        $this->loadNestedRelationsForModel($related, $remainingPath, $constraint);
                    }
                }
            }
        } else {
            $this->loadRelation($collection, $nestedPath, $constraint);
        }
    }

    /**
     * Load nested relations for a single model
     *
     * @param Model $model
     * @param string $nestedPath
     * @param callable|null $constraint
     * @return void
     */
    protected function loadNestedRelationsForModel(Model $model, string $nestedPath, ?callable $constraint = null)
    {
        if (str_contains($nestedPath, '.')) {
            $relations = explode('.', $nestedPath);
            $primaryRelation = array_shift($relations);
            $remainingPath = implode('.', $relations);

            // Load the primary relation if not already loaded
            if (!$model->relationLoaded($primaryRelation)) {
                $this->loadRelation(new Collection(get_class($model), [$model]), $primaryRelation);
            }

            $related = $model->getRelation($primaryRelation);

            if ($related instanceof Collection) {
                $this->loadNestedRelationsForCollection($related, $remainingPath, $constraint);
            } elseif ($related !== null) {
                $this->loadNestedRelationsForModel($related, $remainingPath, $constraint);
            }
        } else {
            if (!$model->relationLoaded($nestedPath)) {
                $this->loadRelation(new Collection(get_class($model), [$model]), $nestedPath, $constraint);
            }
        }
    }

    /**
     * Load a relationship onto the model(s)
     *
     * @param string|array $relations
     * @param callable|null $callback
     * @return $this
     */
    public function load($relations, ?callable $callback = null)
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        // Convert single model to collection
        $models = $this instanceof Collection
            ? $this
            : new Collection($this->modelClass, [$this]);

        foreach ($relations as $key => $value) {
            $relation = is_string($key) ? $key : $value;
            $constraint = is_callable($value) ? $value : $callback;

            if (str_contains($relation, '.')) {
                $this->loadNestedRelations($models, $relation, $constraint);
            } else {
                $this->loadRelation($models, $relation, $constraint);
            }
        }

        return $this;
    }

    /**
     * Reload the current model instance with fresh attributes from the database.
     *
     * @param string|array $relations
     * @return $this
     */
    public function fresh($relations = [])
    {
        $model = $this->first();

        if (!$model) {
            return null;
        }

        $freshModel = $this->getModel()->query()
            ->where($model->getKeyName(), '=', $model->getKey())
            ->embed($relations)
            ->first();

        return $freshModel;
    }

    /**
     * Add a join clause to the query.
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @param string $type
     * @return self
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): self
    {
        $this->joins[] = [
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'type' => $type
        ];
        return $this;
    }

    /**
     * Load a specific relation for the collection
     *
     * @param Collection $collection
     * @param string $relation
     * @param callable|null $constraint
     * @return void
     */
    protected function loadRelation(Collection $collection, string $relation, $constraint = null): void
    {
        $models = $collection->all();
        $firstModel = $models[0] ?? null;

        if (!$firstModel || !method_exists($firstModel, $relation)) {
            return;
        }

        $firstModel->$relation();
        $relationType = $firstModel->getLastRelationType();
        $relatedModel = $firstModel->getLastRelatedModel();
        $foreignKey = $firstModel->getLastForeignKey();
        $localKey = $firstModel->getLastLocalKey();

        if ($relationType === 'manyToMany') {
            $this->loadManyToManyRelation($collection, $relation, $constraint);
            return;
        }

        $keys = array_map(fn($model) => $model->{$localKey}, $models);
        $relatedModelInstance = app($relatedModel);
        $query = $relatedModelInstance->query()->whereIn($foreignKey, $keys);

        if (is_callable($constraint)) {
            $constraint($query);
        }

        $results = $query->get();
        $grouped = [];

        foreach ($results as $result) {
            $grouped[$result->{$foreignKey}][] = $result;
        }

        foreach ($models as $model) {
            $key = $model->{$localKey};
            $relatedItems = $grouped[$key] ?? [];

            $model->setRelation(
                $relation,
                $relationType === 'oneToOne'
                    ? ($relatedItems[0] ?? null)
                    : new Collection($relatedModel, $relatedItems)
            );
        }
    }

    /**
     * @param Collection $collection
     * @param string $relation
     * @param null $constraint
     * @return void
     */
    protected function loadManyToManyRelation(Collection $collection, string $relation, $constraint = null): void
    {
        $models = $collection->all();
        $firstModel = $models[0] ?? null;

        if (!$firstModel || !method_exists($firstModel, $relation)) {
            return;
        }

        $firstModel->$relation();
        $relatedModel = $firstModel->getLastRelatedModel();
        $foreignKey = $firstModel->getLastForeignKey();
        $relatedKey = $firstModel->getLastRelatedKey();
        $pivotTable = $firstModel->getLastPivotTable();

        $keys = array_map(fn($model) => $model->getKey(), $models);
        $relatedModelInstance = new $relatedModel;

        $pivotColumns = $this->getTableColumns($pivotTable);

        $pivotSelects = array_map(function ($column) use ($pivotTable) {
            return "{$pivotTable}.{$column} as pivot_{$column}";
        }, $pivotColumns);

        $query = $relatedModelInstance->query()
            ->select(array_merge(
                ["{$relatedModelInstance->getTable()}.*"],
                $pivotSelects
            ))
            ->join(
                $pivotTable,
                "{$pivotTable}.{$relatedKey}",
                '=',
                "{$relatedModelInstance->getTable()}.{$relatedModelInstance->getKeyName()}"
            )
            ->whereIn("{$pivotTable}.{$foreignKey}", $keys);

        if (is_callable($constraint)) {
            $constraint($query);
        }

        $results = $query->get();
        $grouped = [];

        foreach ($results as $result) {
            $pivot = [];

            foreach ($pivotColumns as $column) {
                $pivot[$column] = $result["pivot_{$column}"];
                unset($result["pivot_{$column}"]);
            }

            $pivotObj = (object)$pivot;
            $result->pivot = $pivotObj;

            $grouped[$pivot[$foreignKey]][] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getKey();
            $model->setRelation(
                $relation,
                new Collection($relatedModel, $grouped[$key] ?? [])
            );
        }
    }

    /**
     * Get the model instance
     * @return \Phaseolies\Database\Eloquent\Model
     */
    public function getModel()
    {
        return app($this->modelClass);
    }

    /**
     * Add a WHERE IN condition
     *
     * @param string $field
     * @param array $values
     * @return self
     */
    public function whereIn(string $field, array $values): self
    {
        if (empty($values)) {
            return $this->where($field, '=', 'NULL');
        }

        if (strpos($field, '.') === false) {
            $field = "{$this->table}.{$field}";
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->conditions[] = ['AND', $field, 'IN', $values, "($placeholders)"];
        return $this;
    }

    /**
     * Add a OR WHERE IN condition
     *
     * @param string $field
     * @param array $values
     * @return self
     */
    public function orWhereIn(string $field, array $values): self
    {
        if (empty($values)) {
            $this->orWhere($field, '=', 'NULL');
            return $this;
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->conditions[] = ['OR', $field, 'IN', $values, "($placeholders)"];
        return $this;
    }


    /**
     * Load one-to-one relationships
     *
     * @param array $models
     * @param string $relation
     * @param string $relatedModel
     * @param string $foreignKey
     * @param string $localKey
     * @return void
     */
    protected function loadOneToOne(array $models, string $relation, string $relatedModel, string $foreignKey, string $localKey): void
    {
        $localKeys = array_map(fn($model) => $model->$localKey, $models);
        $relatedModels = $relatedModel::query()
            ->whereIn($foreignKey, $localKeys)
            ->get()
            ->keyBy($foreignKey);

        foreach ($models as $model) {
            $key = $model->$localKey;
            if (isset($relatedModels[$key])) {
                $model->setRelation($relation, $relatedModels[$key]);
            }
        }
    }

    /**
     * Load one-to-many relationships
     *
     * @param array $models
     * @param string $relation
     * @param string $relatedModel
     * @param string $foreignKey
     * @param string $localKey
     * @return void
     */
    protected function loadOneToMany(array $models, string $relation, string $relatedModel, string $foreignKey, string $localKey): void
    {
        $localKeys = array_map(fn($model) => $model->$localKey, $models);
        $relatedModels = $relatedModel::query()
            ->whereIn($foreignKey, $localKeys)
            ->get()
            ->getItemsGroupedBy($foreignKey);

        foreach ($models as $model) {
            $key = $model->$localKey;
            if (isset($relatedModels[$key])) {
                $model->setRelation($relation, new Collection($relatedModel, $relatedModels[$key]));
            }
        }
    }

    /**
     * Add relationships to be eager loaded.
     *
     * @param string|array $relations
     * @param callable|null $callback
     * @return self
     */
    public function embed($relations, ?callable $callback = null): self
    {
        if (is_string($relations)) {
            $this->eagerLoad[$relations] = $callback;
            return $this;
        }

        if (is_array($relations)) {
            foreach ($relations as $key => $value) {
                if (is_callable($value)) {
                    $this->eagerLoad[$key] = $value;
                } else {
                    $this->eagerLoad[$value] = null;
                }
            }
        }

        return $this;
    }

    /**
     * Execute the query and return an array of arrays.
     *
     * @return array
     * @throws PDOException
     */
    /**
     * Execute the query and return an array of arrays (for pagination).
     *
     * @return array
     * @throws PDOException
     */
    public function getForPagination(): array
    {
        try {
            $stmt = $this->pdo->prepare($this->toSql());
            $this->bindValues($stmt);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $models = array_map(fn($item) => new $this->modelClass($item), $results);

            $collection = new Collection($this->modelClass, $models);

            if (!empty($this->eagerLoad)) {
                $this->eagerLoadRelations($collection);
            }

            return $collection->all();
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Execute the query and return the first result.
     *
     * @return mixed
     * @throws PDOException
     */
    public function first()
    {
        $this->limit(1);
        try {
            $stmt = $this->pdo->prepare($this->toSql());
            $this->bindValues($stmt);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? new $this->modelClass($result) : null;
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Get the count of rows matching the current query.
     *
     * @return int
     * @throws PDOException
     */
    public function count(string $column = '*'): int
    {
        try {
            if (!empty($this->groupBy)) {
                $sql = 'SELECT COUNT(DISTINCT ' . implode(', ', $this->groupBy) . ') as count FROM ' . $this->table;
            } else {
                $column = $column === '*' ? '*' : "`{$column}`";
                $sql = "SELECT COUNT({$column}) as count FROM {$this->table}";
            }

            if (!empty($this->conditions)) {
                $conditionStrings = [];
                foreach ($this->conditions as $condition) {
                    $conditionStrings[] = "{$condition[1]} {$condition[2]} ?";
                }
                $sql .= ' WHERE ' . implode(' ', $this->formatConditions($conditionStrings));
            }

            $stmt = $this->pdo->prepare($sql);
            $this->bindValues($stmt);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) $result['count'];
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Get the records as per desc order
     *
     * @param string $column
     * @return self
     */
    public function newest(string $column = 'id'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Get the records as per asc order
     *
     * @param string $column
     * @return self
     */
    public function oldest(string $column = 'id'): self
    {
        return $this->orderBy($column, 'ASC');
    }

    /**
     * Check if any records exist for the current query.
     *
     * @return bool
     * @throws PDOException
     */
    public function exists(): bool
    {
        $this->limit(1);
        try {
            $stmt = $this->pdo->prepare($this->toSql());
            $this->bindValues($stmt);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Paginate the query results.
     *
     * @param int $perPage Number of items per page.
     * @param int $page Current page number.
     * @return array
     */
    public function paginate(?int $perPage = null): array
    {
        $page = request()->page ?? 1;
        $perPage = $perPage ?? $this->rowPerPage;

        if (!is_int($perPage) || $perPage <= 0) {
            $perPage = 15;
        }

        $offset = ($page - 1) * $perPage;
        $total = $this->count();
        $results = $this->limit($perPage)->offset($offset)->getForPagination();

        $lastPage = max(ceil($total / $perPage), 1);
        $path = URL::current();
        $from = $offset + 1;
        $to = min($offset + $perPage, $total);

        $firstPageUrl = "{$path}?page=1";
        $lastPageUrl = "{$path}?page={$lastPage}";
        $nextPageUrl = $page < $lastPage ? "{$path}?page=" . ($page + 1) : null;
        $prevPageUrl = $page > 1 ? "{$path}?page=" . ($page - 1) : null;

        return [
            'data' => $results,
            'first_page_url' => $firstPageUrl,
            'last_page_url' => $lastPageUrl,
            'next_page_url' => $nextPageUrl,
            'previous_page_url' => $prevPageUrl,
            'path' => $path,
            'from' => $from,
            'to' => $to,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Bind values to the prepared statement.
     *
     * @param PDOStatement $stmt
     * @return void
     */
    protected function bindValues(PDOStatement $stmt): void
    {
        $index = 1;
        foreach ($this->conditions as $condition) {
            if (isset($condition['type'])) {
                if (in_array($condition['type'], ['EXISTS', 'NOT EXISTS'])) {
                    continue;
                }
            } elseif ($condition[2] === 'IS NULL' || $condition[2] === 'IS NOT NULL') {
                continue;
            } elseif ($condition[2] === 'BETWEEN' || $condition[2] === 'NOT BETWEEN') {
                $stmt->bindValue($index++, $condition[3], $this->getPdoParamType($condition[3]));
                $stmt->bindValue($index++, $condition[4], $this->getPdoParamType($condition[4]));
            } elseif ($condition[2] === 'IN') {
                foreach ($condition[3] as $value) {
                    $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
                }
            } else {
                $stmt->bindValue($index++, $condition[3], $this->getPdoParamType($condition[3]));
            }
        }
    }

    /**
     * Insert a new record into the database.
     *
     * @param array $attributes
     * @return int|false The ID of the inserted record or false on failure.
     */
    public function insert(array $attributes)
    {
        $columns = implode(', ', array_keys($attributes));
        $values = implode(', ', array_fill(0, count($attributes), '?'));

        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($values)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindValuesForInsertOrUpdate($stmt, $attributes);
            $stmt->execute();
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Update records in the database.
     *
     * @param array $attributes
     * @return bool
     */
    public function update(array $attributes): bool
    {
        $setClause = implode(', ', array_map(fn($key) => "$key = ?", array_keys($attributes)));

        $sql = "UPDATE {$this->table} SET $setClause";

        if (!empty($this->conditions)) {
            $conditionStrings = [];
            foreach ($this->conditions as $condition) {
                $conditionStrings[] = "{$condition[1]} {$condition[2]} ?";
            }
            $sql .= ' WHERE ' . implode(' ', $this->formatConditions($conditionStrings));
        }

        try {
            $stmt = $this->pdo->prepare($sql);

            // Bind SET clause values
            $index = 1;
            foreach ($attributes as $value) {
                $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
            }

            // Bind WHERE clause values
            foreach ($this->conditions as $condition) {
                $stmt->bindValue($index++, $condition[3], $this->getPdoParamType($condition[3]));
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * @param string $table
     *
     * @return self
     */
    public function from(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Delete records from the database.
     *
     * @return bool Returns true if the delete operation was successful, false otherwise.
     * @throws PDOException If a database error occurs.
     */
    public function delete(): bool
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->conditions)) {
            $conditionStrings = [];
            foreach ($this->conditions as $condition) {
                $conditionStrings[] = "{$condition[1]} {$condition[2]} ?";
            }
            $sql .= ' WHERE ' . implode(' ', $this->formatConditions($conditionStrings));
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $index = 1;
            foreach ($this->conditions as $condition) {
                $stmt->bindValue($index++, $condition[3], $this->getPdoParamType($condition[3]));
            }

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Execute a raw SQL query and return the results as model instances.
     *
     * @param string $sql The raw SQL query to execute
     * @param array $bindings Optional parameter bindings for prepared statements
     * @return Collection
     * @throws PDOException
     */
    public function useRaw(string $sql, array $bindings = []): Collection
    {
        try {
            $stmt = $this->pdo->prepare($sql);

            // Bind parameters if provided
            if (!empty($bindings)) {
                $index = 1;
                foreach ($bindings as $value) {
                    $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
                }
            }

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convert results to model instances
            $models = array_map(fn($item) => new $this->modelClass($item), $results);

            $collection = new Collection($this->modelClass, $models);

            // Eager load relationships if any
            if (!empty($this->eagerLoad)) {
                $this->eagerLoadRelations($collection);
            }

            return $collection;
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Bind values for INSERT or UPDATE operations.
     *
     * @param PDOStatement $stmt
     * @param array $attributes
     * @return void
     */
    protected function bindValuesForInsertOrUpdate(PDOStatement $stmt, array $attributes): void
    {
        $index = 1;
        foreach ($attributes as $value) {
            $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
        }
    }

    /**
     * Add aggregation methods to Builder class
     */

    /**
     * Retrieve the sum of the values of a given column
     *
     * @param string $column
     * @return float
     */
    public function sum(string $column): float
    {
        $this->select(["SUM({$column}) as aggregate"]);
        $result = $this->first();
        return (float) ($result->aggregate ?? 0);
    }

    /**
     * Retrieve the average of the values of a given column
     *
     * @param string $column
     * @return float
     */
    public function avg(string $column): float
    {
        $this->select(["AVG({$column}) as aggregate"]);
        $result = $this->first();
        return (float) ($result->aggregate ?? 0);
    }

    /**
     * Retrieve the minimum value of a given column
     *
     * @param string $column
     * @return mixed
     */
    public function min(string $column)
    {
        $this->select(["MIN({$column}) as aggregate"]);
        $result = $this->first();
        return $result->aggregate;
    }

    /**
     * Retrieve the maximum value of a given column
     *
     * @param string $column
     * @return mixed
     */
    public function max(string $column)
    {
        $this->select(["MAX({$column}) as aggregate"]);
        $result = $this->first();
        return $result->aggregate;
    }

    /**
     * Retrieve one model per distinct value of the specified column
     * 
     * @param string $column The column to check for distinct values
     * @return Collection Collection of models with one row per distinct column value
     */
    /**
     * Retrieve one model per distinct value of the specified column
     * 
     * @param string $column The column to check for distinct values
     * @return Collection Collection of models with one row per distinct column value
     */
    /**
     * Retrieve one model per distinct value of the specified column
     * 
     * @param string $column The column to check for distinct values
     * @return Collection Collection of models with one row per distinct column value
     */
    /**
     * Retrieve distinct values for a column
     * 
     * @param string $column The column to get distinct values from
     * @return Collection Collection of distinct values
     */
    public function distinct(string $column): Collection
    {
        // Validate the column exists
        if (!in_array($column, $this->getTableColumns())) {
            throw new \InvalidArgumentException("Column {$column} does not exist in table {$this->table}");
        }

        // Build the distinct query
        $sql = "SELECT DISTINCT {$column} FROM {$this->table}";

        // Add WHERE conditions if any
        if (!empty($this->conditions)) {
            $conditionStrings = [];
            foreach ($this->conditions as $condition) {
                $conditionStrings[] = "{$condition[1]} {$condition[2]} ?";
            }
            $sql .= ' WHERE ' . implode(' ', $this->formatConditions($conditionStrings));
        }

        // Add ORDER BY if any
        if (!empty($this->orderBy)) {
            $orderByStrings = array_map(fn($o) => "$o[0] $o[1]", $this->orderBy);
            $sql .= ' ORDER BY ' . implode(', ', $orderByStrings);
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindValues($stmt);
            $stmt->execute();

            // Fetch just the column values
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            return new Collection('array', $results);
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Retrieve the grouped concatenation of values
     *
     * @param string $column
     * @param string $separator Defaults to ','
     * @return string
     */
    public function groupConcat(string $column, string $separator = ','): string
    {
        $this->select(["GROUP_CONCAT({$column} SEPARATOR '{$separator}') as aggregate"]);
        $result = $this->first();
        return (string) ($result->aggregate ?? '');
    }

    /**
     * Retrieve the standard deviation of a column
     *
     * @param string $column
     * @return float
     */
    public function stdDev(string $column): float
    {
        $this->select(["STDDEV({$column}) as aggregate"]);
        $result = $this->first();
        return (float) ($result->aggregate ?? 0);
    }

    /**
     * Retrieve the variance of a column
     *
     * @param string $column
     * @return float
     */
    public function variance(string $column): float
    {
        $this->select(["VARIANCE({$column}) as aggregate"]);
        $result = $this->first();
        return (float) ($result->aggregate ?? 0);
    }

    /**
     * Increment a column's value by a given amount
     * 
     * @param string $column The column to increment
     * @param int $amount Amount to increment by (default 1)
     * @param array $extra Additional columns to update
     * @return int Number of affected rows
     */
    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        return $this->updateColumn($column, $amount, '+', $extra);
    }

    /**
     * Decrement a column's value by a given amount
     * 
     * @param string $column The column to decrement
     * @param int $amount Amount to decrement by (default 1)
     * @param array $extra Additional columns to update
     * @return int Number of affected rows
     */
    public function decrement(string $column, int $amount = 1, array $extra = []): int
    {
        return $this->updateColumn($column, $amount, '-', $extra);
    }

    /**
     * Add a where between clause to the query
     *
     * @param string $column
     * @param array $values [min, max]
     * @param string $boolean (AND/OR)
     * @param bool $not Whether to use NOT BETWEEN
     * @return self
     */
    public function whereBetween(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        // Validate input
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('whereBetween requires an array with exactly 2 values');
        }

        [$min, $max] = $values;

        $this->conditions[] = [
            $boolean,
            $column,
            $not ? 'NOT BETWEEN' : 'BETWEEN',
            $min,
            $max,
            'AND' // Internal separator between min/max
        ];

        return $this;
    }

    /**
     * Add a or where between clause to the query
     *
     * @param string $column
     * @param array $values [min, max]
     * @return self
     */
    public function orWhereBetween(string $column, array $values): self
    {
        return $this->whereBetween($column, $values, 'OR');
    }

    /**
     * Add a where not between clause to the query
     *
     * @param string $column
     * @param array $values [min, max]
     * @return self
     */
    public function whereNotBetween(string $column, array $values): self
    {
        return $this->whereBetween($column, $values, 'AND', true);
    }

    /**
     * Add a or where not between clause to the query
     *
     * @param string $column
     * @param array $values [min, max]
     * @return self
     */
    public function orWhereNotBetween(string $column, array $values): self
    {
        return $this->whereBetween($column, $values, 'OR', true);
    }

    /**
     * Add a where null clause to the query
     *
     * @param string $column
     * @param string $boolean (AND/OR)
     * @param bool $not Whether to use IS NOT NULL
     * @return self
     */
    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): self
    {
        $this->conditions[] = [
            $boolean,
            $column,
            $not ? 'IS NOT NULL' : 'IS NULL'
        ];
        return $this;
    }

    /**
     * Add a where not null clause to the query
     *
     * @param string $column
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        return $this->whereNull($column, 'AND', true);
    }

    /**
     * Add an or where null clause to the query
     *
     * @param string $column
     * @return self
     */
    public function orWhereNull(string $column): self
    {
        return $this->whereNull($column, 'OR');
    }

    /**
     * Add an or where not null clause to the query
     *
     * @param string $column
     * @return self
     */
    public function orWhereNotNull(string $column): self
    {
        return $this->whereNull($column, 'OR', true);
    }

    /**
     * Helper method to handle both increment and decrement operations
     * 
     * @param string $column Column to modify
     * @param int $amount Amount to change
     * @param string $operator + or -
     * @param array $extra Additional columns to update
     * @return int Number of affected rows
     */
    protected function updateColumn(string $column, int $amount, string $operator, array $extra = []): int
    {
        // Validate amount
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount must be positive");
        }

        // Start building the SQL
        $sql = "UPDATE {$this->table} SET {$column} = {$column} {$operator} ?";
        $bindings = [$amount];

        // Add extra columns if provided
        if (!empty($extra)) {
            $extraUpdates = [];
            foreach ($extra as $key => $value) {
                $extraUpdates[] = "{$key} = ?";
                $bindings[] = $value;
            }
            $sql .= ', ' . implode(', ', $extraUpdates);
        }

        // Add WHERE conditions if any
        if (!empty($this->conditions)) {
            $conditionStrings = [];
            foreach ($this->conditions as $condition) {
                $conditionStrings[] = "{$condition[1]} {$condition[2]} ?";
                $bindings[] = $condition[3];
            }
            $sql .= ' WHERE ' . implode(' ', $this->formatConditions($conditionStrings));
        }

        try {
            $stmt = $this->pdo->prepare($sql);

            // Bind all values
            foreach ($bindings as $index => $value) {
                $stmt->bindValue($index + 1, $value, $this->getPdoParamType($value));
            }

            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Get the PDO param type for a value.
     *
     * @param mixed $value
     * @return int
     */
    protected function getPdoParamType($value): int
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        }
        if (is_bool($value)) {
            return PDO::PARAM_BOOL;
        }
        if (is_null($value)) {
            return PDO::PARAM_NULL;
        }
        return PDO::PARAM_STR;
    }
}
