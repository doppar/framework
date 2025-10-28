<?php

namespace Phaseolies\Database\Entity;

use PDOStatement;
use PDOException;
use PDO;
use Phaseolies\Database\Entity\Query\{
    Debuggable,
    Grammar,
    InteractsWithTimeframe,
    QueryUtils,
    InteractsWithBigDataProcessing,
    InteractsWithModelQueryProcessing,
    InteractsWithAggregateFucntion
};
use Phaseolies\Utilities\Casts\CastToDate;
use Phaseolies\Support\Facades\URL;
use Phaseolies\Support\Contracts\Encryptable;
use Phaseolies\Support\Collection;

use Phaseolies\Database\Entity\Model;

class Builder
{
    use InteractsWithModelQueryProcessing;
    use InteractsWithBigDataProcessing;
    use QueryUtils;
    use Debuggable;
    use InteractsWithTimeframe;
    use Grammar;
    use InteractsWithAggregateFucntion;

    /**
     * Holds the PDO instance for database connectivity.
     *
     * @var PDO
     */
    protected PDO $pdo;

    /**
     * The name of the database table to query.
     *
     * @var string
     */
    protected string $table;

    /**
     * The fields to select in the query. Defaults to ['*'] which selects all columns.
     *
     * @var array
     */
    protected array $fields = ['*'];

    /**
     * The conditions (WHERE clauses) to apply to the query.
     *
     * @var array
     */
    protected array $conditions = [];

    /**
     * The ORDER BY clauses to sort the query results.
     *
     * @var array
     */
    protected array $orderBy = [];

    /**
     * The GROUP BY clauses to group the query results.
     *
     * @var array
     */
    protected array $groupBy = [];

    /**
     * The maximum number of records to return. Null means no limit.
     *
     * @var int|null
     */
    protected ?int $limit = null;

    /**
     * The number of records to skip before starting to return records. Null means no offset.
     *
     * @var int|null
     */
    protected ?int $offset = null;

    /**
     * The class name of the model associated with this query.
     *
     * @var string
     */
    protected string $modelClass;

    /**
     * The number of rows to display per page for pagination.
     *
     * @var int
     */
    protected int $rowPerPage;

    /**
     * Holds the relationships to be eager loaded
     *
     * @var array
     */
    protected array $eagerLoad = [];

    /**
     * The join clauses for the query.
     *
     * @var array
     */
    protected array $joins = [];

    /**
     * @var array
     */
    protected array $relationInfo = [];

    /**
     * @var bool
     */
    protected bool $takeWithoutEncryption = true;

    /**
     * @var bool
     */
    protected bool $suppressEagerLoad = false;

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

    /**
     * Set the relationship info
     *
     * @param array $info
     * @return self
     */
    public function setRelationInfo(array $info): self
    {
        $this->relationInfo = $info;

        return $this;
    }

    /**
     * Set the fields to select.
     *
     * @param array|string ...$fields Field(s) to select (can be array or multiple strings)
     * @return self
     */
    public function select(array|string ...$fields): self
    {
        $fields = count($fields) === 1 && is_array($fields[0])
            ? $fields[0]
            : $fields;

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
     * @param string|callable $field Field name or callback for nested conditions
     * @param mixed $operator Operator or value (if only 2 arguments passed)
     * @param mixed $value Value to compare (optional)
     * @return self
     */
    public function where($field, $operator = null, $value = null): self
    {
        if (is_callable($field)) {
            return $this->whereNested($field, 'AND');
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if ($value === null) {
            if ($operator === '=') {
                return $this->whereNull($field);
            } elseif ($operator === '!=') {
                return $this->whereNotNull($field);
            }
        }

        $this->conditions[] = ['AND', $field, $operator, $value];

        return $this;
    }

    /**
     * Add an OR WHERE condition.
     *
     * @param string|callable $field Field name or callback for nested conditions
     * @param mixed $operator Operator or value (if only 2 arguments passed)
     * @param mixed $value Value to compare (optional)
     * @return self
     */
    public function orWhere($field, $operator = null, $value = null): self
    {
        if (is_callable($field)) {
            return $this->whereNested($field, 'OR');
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = ['OR', $field, $operator, $value];

        return $this;
    }

    /**
     * Add a nested WHERE condition.
     *
     * @param callable $callback
     * @param string $boolean
     * @return self
     */
    public function whereNested(callable $callback, string $boolean = 'AND'): self
    {
        $nestedQuery = new static($this->pdo, $this->table, $this->modelClass, $this->rowPerPage);

        $callback($nestedQuery);

        if (!empty($nestedQuery->conditions)) {
            $this->conditions[] = [
                'type' => 'NESTED',
                'query' => $nestedQuery,
                'boolean' => $boolean
            ];
        }

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
     * Indicates that data should be fetched without encryption.
     *
     * @return self
     */
    public function withoutEncryption(): self
    {
        $this->takeWithoutEncryption = false;

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

        if (!empty($this->groupBy)) {
            $groupedFields = $this->groupBy;
            $nonGroupedFields = array_diff($this->fields, $groupedFields);

            if (in_array('*', $this->fields)) {
                $this->fields = $this->getTableColumns();
                $nonGroupedFields = array_diff($this->fields, $groupedFields);
            }

            if (!empty($nonGroupedFields)) {
                $processedFields = [];
                foreach ($this->fields as $field) {
                    if (in_array($field, $groupedFields)) {
                        $processedFields[] = $field;
                    } elseif (strpos($field, '(') !== false) {
                        $processedFields[] = $field;
                    } else {
                        $processedFields[] = "MAX($field) AS $field";
                    }
                }
                $sql .= implode(', ', $processedFields);
            } else {
                $sql .= implode(', ', $this->fields);
            }
        } else {
            $sql .= implode(', ', $this->fields);
        }

        $sql .= ' FROM ' . $this->table;

        foreach ($this->joins as $join) {
            $sql .= ' ' . strtoupper($join['type']) . ' JOIN ' . $join['table'] .
                ' ON ' . $join['first'] . ' ' . $join['operator'] . ' ' . $join['second'];
        }

        if (!empty($this->conditions)) {
            $conditionStrings = [];
            foreach ($this->conditions as $condition) {
                if (isset($condition['type']) && $condition['type'] === 'NESTED') {
                    $nestedSql = $condition['query']->toSql();
                    $nestedWhere = substr($nestedSql, strpos($nestedSql, 'WHERE') + 5);
                    $conditionStrings[] = "({$nestedWhere})";
                } elseif (isset($condition['type']) && $condition['type'] === 'RAW_WHERE') {
                    $conditionStrings[] = $condition['sql'];
                } elseif (isset($condition['type']) && ($condition['type'] === 'EXISTS' || $condition['type'] === 'NOT EXISTS')) {
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

        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if (!empty($this->orderBy)) {
            $orderByStrings = array_map(function ($o) {
                if (isset($o['type']) && $o['type'] === 'RAW_ORDER_BY') {
                    return $o['expression'];
                }
                // Regular order by clause (array with field and direction)
                return "$o[0] $o[1]";
            }, $this->orderBy);

            $sql .= ' ORDER BY ' . implode(', ', $orderByStrings);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

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
        $sql = $this->getTableColumnsSql($tableName);

        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->processTableColumnsResult($result);
    }

    /**
     * Filter records that have at least one related record in the given relationship
     *
     * @param string $relation
     * @param callable|null $callback
     * @return self
     */
    public function present(string $relation, ?callable $callback = null): self
    {
        return $this->handleRelationshipExists($relation, $callback, 'AND', 'EXISTS');
    }

    /**
     * Filter records that have at least one related record in the given relationship
     * using OR condition with previous where clauses
     *
     * @param string $relation
     * @param callable|null $callback
     * @return self
     */
    public function orPresent(string $relation, ?callable $callback = null): self
    {
        return $this->handleRelationshipExists($relation, $callback, 'OR', 'EXISTS');
    }

    /**
     * Filter records that don't have any related records in the given relationship
     *
     * @param string $relation
     * @return self
     */
    public function absent(string $relation): self
    {
        return $this->handleRelationshipExists($relation, null, 'AND', 'NOT EXISTS');
    }

    /**
     * Filter records that don't have any related records in the given relationship
     * using OR condition with previous where clauses
     *
     * @param string $relation
     * @return self
     */
    public function orAbsent(string $relation): self
    {
        return $this->handleRelationshipExists($relation, null, 'OR', 'NOT EXISTS');
    }

    /**
     * Internal method to handle both present and orPresent functionality
     *
     * @param string $relation
     * @param callable|null $callback
     * @param string $boolean AND/OR
     * @param string $type EXISTS/NOT EXISTS
     * @return self
     */
    private function handleRelationshipExists(string $relation, ?callable $callback, string $boolean = 'AND', string $type = 'EXISTS'): self
    {
        $model = $this->getModel();

        if (!method_exists($model, $relation)) {
            throw new \BadMethodCallException("Relationship {$relation} does not exist on model " . get_class($model));
        }

        $relationQuery = $model->$relation();
        $relationType = $model->getLastRelationType();
        $relatedModel = $model->getLastRelatedModel();

        if ($relationType === 'bindToMany') {
            $subquery = $this->buildManyToManySubquery($model, $relatedModel, $callback);
        } else {
            $subquery = $this->buildDirectRelationshipSubquery($model, $relatedModel, $callback);
        }

        $subquery .= ' LIMIT 1';

        $this->conditions[] = [
            'type' => $type,
            'subquery' => $subquery,
            'bindings' => [],
            'boolean' => $boolean
        ];

        return $this;
    }

    /**
     * Build subquery for many-to-many relationships
     *
     * @param Model $model
     * @param mixed $relatedModel
     * @param callable|null $callback
     * @return string
     */
    private function buildManyToManySubquery(Model $model, mixed $relatedModel, ?callable $callback): string
    {
        $pivotTable = $model->getLastPivotTable();
        $foreignKey = $model->getLastForeignKey();
        $relatedKey = $model->getLastRelatedKey();
        $localKey = $model->getLastLocalKey();
        $relatedTable = (new $relatedModel())->getTable();
        $relatedPrimaryKey = (new $relatedModel())->getKeyName();

        $quote = fn($identifier) => $this->quoteIdentifier($identifier);

        // Build subquery with JOIN to access related model columns
        $subquery = "SELECT 1 FROM {$quote($pivotTable)}";

        // Add JOIN to related table if we have a callback
        if ($callback) {
            $subquery .= " INNER JOIN {$quote($relatedTable)} ON {$quote($pivotTable)}.{$quote($relatedKey)} = {$quote($relatedTable)}.{$quote($relatedPrimaryKey)}";
        }

        $subquery .= " WHERE {$quote($pivotTable)}.{$quote($foreignKey)} = {$quote($this->table)}.{$quote($localKey)}";

        if ($callback) {
            $subquery = $this->addCallbackConditions($subquery, $relatedModel, $callback, $relatedTable);
        }

        return $subquery;
    }

    /**
     * Build subquery for one-to-one and one-to-many relationships
     *
     * @param Model $model
     * @param mixed $relatedModel
     * @param callable|null $callback
     * @return string
     */
    private function buildDirectRelationshipSubquery(Model $model, mixed $relatedModel, ?callable $callback): string
    {
        $foreignKey = $model->getLastForeignKey();
        $localKey = $model->getLastLocalKey();
        $relatedTable = (new $relatedModel())->getTable();

        $quote = fn($identifier) => $this->quoteIdentifier($identifier);

        $subquery = "SELECT 1 FROM {$quote($relatedTable)}
            WHERE {$quote($relatedTable)}.{$quote($foreignKey)} = {$quote($this->table)}.{$quote($localKey)}";

        if ($callback) {
            $subquery = $this->addCallbackConditions($subquery, $relatedModel, $callback, $relatedTable);
        }

        return $subquery;
    }

    /**
     * Add callback conditions to the subquery
     *
     * @param string $subquery
     * @param mixed $relatedModel
     * @param callable $callback
     * @param string $relatedTable
     * @return string
     */
    private function addCallbackConditions(string $subquery, mixed $relatedModel, callable $callback, string $relatedTable): string
    {
        $subQueryBuilder = $relatedModel::query();
        $callback($subQueryBuilder);

        $quote = fn($identifier) => $this->quoteIdentifier($identifier);
        $escapeValue = fn($value) => $this->escapeValue($value);

        foreach ($subQueryBuilder->conditions as $condition) {
            if (isset($condition['type'])) {
                continue;
            }

            $column = $condition[1];
            $operator = $condition[2];
            $value = $condition[3];

            // Ensure column is properly qualified with table name if not already
            if (strpos($column, '.') === false) {
                $column = "{$quote($relatedTable)}.{$quote($column)}";
            } else {
                // Quote qualified column names
                $column = $quote($column);
            }

            $subquery .= $this->buildConditionClause($column, $operator, $value, $escapeValue);
        }

        return $subquery;
    }

    /**
     * Build individual condition clause
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @param callable $escapeValue
     * @return string
     */
    private function buildConditionClause(string $column, string $operator, $value, callable $escapeValue): string
    {
        if ($value === null) {
            if ($operator === '=') {
                return " AND {$column} IS NULL";
            } elseif ($operator === '!=') {
                return " AND {$column} IS NOT NULL";
            }
        } elseif ($operator === 'IN') {
            if (empty($value)) {
                // Empty IN clause is always false
                return " AND 1=0";
            } else {
                $escapedValues = $escapeValue($value);
                return " AND {$column} IN {$escapedValues}";
            }
        } elseif ($operator === 'LIKE' || $operator === 'NOT LIKE') {
            $escapedValue = $escapeValue($value);
            return " AND {$column} {$operator} {$escapedValue}";
        } else {
            $escapedValue = $escapeValue($value);
            return " AND {$column} {$operator} {$escapedValue}";
        }

        return '';
    }

    /**
     * Properly escape values based on type
     *
     * @param mixed $value
     * @return string
     */
    private function escapeValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_numeric($value)) {
            return $value;
        }

        // For arrays, handle each element
        if (is_array($value)) {
            $escapedValues = array_map(function ($v) {
                return $this->escapeValue($v);
            }, $value);
            return '(' . implode(', ', $escapedValues) . ')';
        }

        // For strings, use proper quoting
        return $this->pdo->quote($value);
    }

    /**
     * Conditionally add clauses to the query
     * Only executes callback when condition is strictly true or has a non-empty value
     * (0 and false will not trigger the callback)
     *
     * @param mixed $value
     * @param callable $callback The callback that adds query constraints
     * @param callable|null $default Optional default callback if condition is false
     * @return self
     */
    public function if($value, callable $callback, ?callable $default = null): self
    {
        $payload = is_callable($value) ? $value() : $value;

        if ($payload === true || $this->hasValue($payload)) {
            $callback($this);
        } elseif ($default !== null) {
            $default($this);
        }

        return $this;
    }

    /**
     * Check if a value should be considered as having a value
     *
     * @param mixed $value
     * @return bool false for: null, empty string, false, 0, empty array
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
     * Format conditions with AND/OR.
     *
     * @param array $conditionStrings
     * @return array
     */
    protected function formatConditions(array $conditionStrings): array
    {
        $formattedConditions = [];

        foreach ($this->conditions as $index => $condition) {
            // Handle nested conditions
            $hasType = isset($condition['type']);

            if ($hasType) {
                // Handle nested conditions
                if ($condition['type'] === 'NESTED') {
                    if ($index > 0) {
                        $formattedConditions[] = $condition['boolean'] ?? 'AND';
                    }
                    $formattedConditions[] = $conditionStrings[$index];
                }
                // Handle EXISTS/NOT EXISTS conditions
                elseif (in_array($condition['type'], ['EXISTS', 'NOT EXISTS'])) {
                    if ($index > 0) {
                        $formattedConditions[] = $condition['boolean'] ?? 'AND';
                    }
                    $formattedConditions[] = $conditionStrings[$index];
                }
                // Handle raw conditions
                elseif ($condition['type'] === 'RAW_WHERE') {
                    if ($index > 0) {
                        $formattedConditions[] = $condition['boolean'];
                    }
                    $formattedConditions[] = $conditionStrings[$index];
                }
            } else {
                // For regular conditions (without 'type' key)
                if ($index > 0) {
                    $formattedConditions[] = $condition[0]; // AND/OR
                }
                $formattedConditions[] = $conditionStrings[$index];
            }
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
     * Lazily executes the query and yields one model instance at a time.
     *
     * @return \Generator
     * @throws PDOException
     */
    private function fetchLazy(): \Generator
    {
        $stmt = null;

        try {
            $stmt = $this->pdo->prepare($this->toSql());
            $this->bindValues($stmt);
            $stmt->execute();

            $needsEncryption = $this->needsEncryption();
            $encryptedAttributes = $this->getEncryptedAttributes();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $model = app($this->modelClass, [$row]);

                if ($needsEncryption && $this->takeWithoutEncryption) {
                    foreach ($encryptedAttributes as $attribute) {
                        $model->$attribute = $model->$attribute
                            ? encrypt($model->$attribute)
                            : $model->$attribute;
                    }
                }

                yield $model;
            }
        } finally {
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }
        }
    }

    /**
     * Execute the query and return a collection of model instances.
     *
     * @return Collection A collection of model instances.
     */
    public function get(): Collection
    {
        $models = [];

        foreach ($this->fetchLazy() as $model) {
            $models[] = $model;
        }

        $collection = new Collection($this->modelClass, $models);

        unset($models);
        if (gc_enabled()) {
            gc_collect_cycles();
        }

        if (!empty($this->eagerLoad)) {
            $this->eagerLoadRelations($collection);
        }

        return $collection;
    }

    /**
     * Check if the model needs encryption
     *
     * @return bool
     */
    protected function needsEncryption(): bool
    {
        return is_subclass_of($this->modelClass, Encryptable::class);
    }

    /**
     * Get encrypted attributes for the model
     *
     * @return array
     */
    protected function getEncryptedAttributes(): array
    {
        return $this->needsEncryption()
            ? app($this->modelClass)->getEncryptedProperties()
            : [];
    }

    /**
     * Encrypt attributes for a single model
     *
     * @param Model $model
     * @param array $attributes
     * @return void
     */
    protected function encryptModelAttributes($model, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $model->$attribute = $model->$attribute
                ? encrypt($model->$attribute)
                : $model->$attribute;
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
     *
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
     *
     * @return string
     */
    protected function getParentKey(): string
    {
        return $this->getModel()->getParentKey();
    }

    /**
     * Get the data without calling the eager loading
     *
     * @return self
     */
    public function withoutEagerLoad(): self
    {
        $clone = clone $this;
        $clone->eagerLoad = [];
        $clone->suppressEagerLoad = true;

        return $clone;
    }

    /**
     * Eager load the relationships for the collection
     *
     * @param Collection $collection
     * @return void
     */
    protected function eagerLoadRelations(Collection $collection): void
    {
        foreach ($this->eagerLoad as $relation => $constraint) {
            // Check if this is a count relation
            if (str_starts_with($relation, 'count:')) {
                $actualRelation = substr($relation, 6);
                $this->loadRelationCount($collection, $actualRelation, $constraint);
                continue;
            }

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
    protected function loadNestedRelations(Collection $collection, string $nestedRelation, ?callable $constraint = null): void
    {
        $relations = explode('.', $nestedRelation);
        $primaryRelation = array_shift($relations);
        $nestedPath = implode('.', $relations);

        // First load the primary relation
        $this->loadRelation($collection, $primaryRelation);

        // Collect all related models from the primary relation
        $allRelatedModels = [];
        $relatedModelClass = null;

        foreach ($collection->all() as $model) {
            if ($model->relationLoaded($primaryRelation)) {
                $related = $model->getRelation($primaryRelation);

                if ($related instanceof Collection) {
                    foreach ($related->all() as $relatedModel) {
                        $allRelatedModels[] = $relatedModel;
                    }
                    if (!$relatedModelClass && $related->count() > 0) {
                        $relatedModelClass = get_class($related->first());
                    }
                } elseif ($related !== null) {
                    $allRelatedModels[] = $related;
                    if (!$relatedModelClass) {
                        $relatedModelClass = get_class($related);
                    }
                }
            }
        }

        // If we have related models, load nested relations in bulk
        if (!empty($allRelatedModels) && $relatedModelClass) {
            $relatedCollection = new Collection($relatedModelClass, $allRelatedModels);

            if (str_contains($nestedPath, '.')) {
                $this->loadNestedRelations($relatedCollection, $nestedPath, $constraint);
            } else {
                $this->loadRelation($relatedCollection, $nestedPath, $constraint);
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
    public function load($relations, ?callable $callback = null): self
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
     * @return $this|null
     */
    public function fresh($relations = []): ?self
    {
        $model = $this->first();

        if (!$model) {
            return null;
        }

        $freshModel = $this->getModel()->newQuery()
            ->where($model->getKeyName(), $model->getKey())
            ->embed($relations)
            ->first();

        return $freshModel;
    }

    /**
     * Delete records by their primary keys.
     *
     * @param mixed ...$ids Single ID or array of IDs to delete
     * @return int Number of deleted records
     * @throws PDOException
     */
    public function purge(...$ids): int
    {
        $ids = is_array($ids[0]) ? $ids[0] : $ids;

        if (empty($ids)) {
            return 0;
        }

        $model = $this->getModel();
        $primaryKey = $model->getKeyName();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM {$this->table} WHERE {$primaryKey} IN ({$placeholders})";

        try {
            $stmt = $this->pdo->prepare($sql);

            foreach ($ids as $index => $id) {
                $stmt->bindValue($index + 1, $id, $this->getPdoParamType($id));
            }

            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
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

        if ($relationType === 'bindToMany') {
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
                $relationType === 'linkOne' || $relationType === 'bindTo'
                    ? ($relatedItems[0] ?? null)
                    : new Collection($relatedModel, $relatedItems)
            );
        }
    }

    /**
     * Load many to many relations
     *
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
        $relatedModelInstance = new $relatedModel();

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
     * Add relationship counts to be loaded with the query
     *
     * @param string|array $relations Relation name(s) to count
     * @param callable|null $callback Optional callback to filter the count
     * @return self
     */
    public function embedCount($relations, ?callable $callback = null): self
    {
        if (is_string($relations)) {
            $this->eagerLoad["count:{$relations}"] = $callback;
            return $this;
        }

        if (is_array($relations)) {
            foreach ($relations as $key => $value) {
                if (is_callable($value)) {
                    $this->eagerLoad["count:{$key}"] = $value;
                } else {
                    $this->eagerLoad["count:{$value}"] = null;
                }
            }
        }

        return $this;
    }

    /**
     * Load the count of a relationship for a collection of models
     *
     * @param Collection $collection
     * @param string $relation
     * @param callable|null $constraint
     * @return void
     */
    protected function loadRelationCount(Collection $collection, string $relation, ?callable $constraint = null): void
    {
        $models = $collection->all();
        $firstModel = $models[0] ?? null;

        if (!$firstModel || !method_exists($firstModel, $relation)) {
            return;
        }

        // Initialize the relationship to get metadata
        $firstModel->$relation();
        $relationType = $firstModel->getLastRelationType();
        $relatedModel = $firstModel->getLastRelatedModel();
        $foreignKey = $firstModel->getLastForeignKey();
        $localKey = $firstModel->getLastLocalKey();

        // Collect all local keys
        $localKeys = array_map(fn($model) => $model->{$localKey}, $models);

        if ($relationType === 'bindToMany') {
            // Handle many-to-many count
            $this->loadManyToManyCount($collection, $relation, $constraint, $localKeys);
            return;
        }

        // Handle one-to-many or one-to-one count
        $relatedModelInstance = app($relatedModel);
        $query = $relatedModelInstance->query()
            ->select([$foreignKey, 'COUNT(*) as aggregate'])
            ->whereIn($foreignKey, $localKeys)
            ->groupBy($foreignKey);

        if (is_callable($constraint)) {
            $constraint($query);
        }

        $results = $query->get();
        $counts = [];

        foreach ($results as $result) {
            $counts[$result->{$foreignKey}] = (int) $result->aggregate;
        }

        // Set the count on each model
        $countAttribute = $relation . '_count';
        foreach ($models as $model) {
            $key = $model->{$localKey};
            $model->{$countAttribute} = $counts[$key] ?? 0;
        }
    }

    /**
     * Load many-to-many relationship count
     *
     * @param Collection $collection
     * @param string $relation
     * @param callable|null $constraint
     * @param array $localKeys
     * @return void
     */
    protected function loadManyToManyCount(Collection $collection, string $relation, ?callable $constraint, array $localKeys): void
    {
        $models = $collection->all();
        $firstModel = $models[0] ?? null;

        if (!$firstModel) {
            return;
        }

        $firstModel->$relation();
        $relatedModel = $firstModel->getLastRelatedModel();
        $foreignKey = $firstModel->getLastForeignKey();
        $relatedKey = $firstModel->getLastRelatedKey();
        $pivotTable = $firstModel->getLastPivotTable();
        $localKey = $firstModel->getLastLocalKey();

        $relatedModelInstance = new $relatedModel();
        $relatedTable = $relatedModelInstance->getTable();

        $query = $relatedModelInstance->query()
            ->select(["{$pivotTable}.{$foreignKey}", 'COUNT(*) as aggregate'])
            ->join(
                $pivotTable,
                "{$pivotTable}.{$relatedKey}",
                '=',
                "{$relatedTable}.{$relatedModelInstance->getKeyName()}"
            )
            ->whereIn("{$pivotTable}.{$foreignKey}", $localKeys)
            ->groupBy("{$pivotTable}.{$foreignKey}");

        if (is_callable($constraint)) {
            $constraint($query);
        }

        $results = $query->get();
        $counts = [];

        foreach ($results as $result) {
            $counts[$result->{$foreignKey}] = (int) $result->aggregate;
        }

        // Set the count on each model
        $countAttribute = $relation . '_count';
        foreach ($models as $model) {
            $key = $model->{$localKey};
            $model->{$countAttribute} = $counts[$key] ?? 0;
        }
    }

    /**
     * Get the model instance
     *
     * @return \Phaseolies\Database\Entity\Model
     */
    public function getModel(): Model
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

            if (!$result) {
                return null;
            }

            $model = new $this->modelClass($result);

            if ($this->needsEncryption() && $this->takeWithoutEncryption) {
                $encryptedAttributes = $this->getEncryptedAttributes();
                foreach ($encryptedAttributes as $attribute) {
                    $model->$attribute = $model->$attribute
                        ? encrypt($model->$attribute)
                        : $model->$attribute;
                }
            }

            if (!empty($this->eagerLoad) && !$this->suppressEagerLoad) {
                $collection = new Collection($this->modelClass, [$model]);
                $this->eagerLoadRelations($collection);
            }

            return $model;
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Get the count of rows matching the current query.
     *
     * @param string $column The column to count (defaults to '*')
     * @return int
     * @throws PDOException
     */
    public function count(string $column = '*'): int
    {
        $query = $this->withoutEagerLoad();

        $query->orderBy = [];
        $query->limit = null;
        $query->offset = null;

        if (!empty($query->groupBy)) {
            $subQuery = clone $query;
            $subQuery->fields = $query->groupBy;

            $subSql = $subQuery->toSql();

            $countSql = "SELECT COUNT(*) as aggregate FROM ($subSql) as count_subquery";

            try {
                $stmt = $this->pdo->prepare($countSql);
                $subQuery->bindValues($stmt);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return (int) ($result['aggregate'] ?? 0);
            } catch (PDOException $e) {
                throw new PDOException("Database error: " . $e->getMessage());
            }
        } else {
            $column = $column === '*' ? '*' : $this->quoteIdentifier($column);
            $query->select(["COUNT($column) as aggregate"]);

            $result = $query->first();
            return (int) ($result->aggregate ?? 0);
        }
    }

    /**
     * Add a raw select expression to the query.
     *
     * @param string $expression The raw SQL expression
     * @param array $bindings Optional bindings for the expression
     * @return self
     */
    public function selectRaw(string $expression, array $bindings = []): self
    {
        if ($this->fields === ['*']) {
            $this->fields = [];
        }

        $processedExpression = $expression;
        if (!empty($bindings)) {
            foreach ($bindings as $value) {
                $processedExpression = preg_replace(
                    '/\?/',
                    is_numeric($value) ? $value : "'" . $this->pdo->quote($value) . "'",
                    $processedExpression,
                    1
                );
            }
        }

        $this->fields[] = $processedExpression;

        return $this;
    }

    /**
     * Add a raw GROUP BY clause to the query.
     *
     * @param string $sql The raw GROUP BY expression
     * @param array $bindings Optional bindings for parameters
     * @return self
     */
    public function groupByRaw(string $sql, array $bindings = []): self
    {
        $this->groupBy[] = $sql;

        if (!empty($bindings)) {
            $this->conditions[] = [
                'type' => 'RAW_GROUP_BY',
                'expression' => $sql,
                'bindings' => $bindings
            ];
        }

        return $this;
    }

    /**
     * Add a raw ORDER BY clause to the query.
     *
     * @param string $sql The raw ORDER BY expression
     * @param array $bindings Optional bindings for parameters
     * @return self
     */
    public function orderByRaw(string $sql, array $bindings = []): self
    {
        $this->orderBy[] = [
            'type' => 'RAW_ORDER_BY',
            'expression' => $sql,
            'bindings' => $bindings
        ];

        return $this;
    }

    /**
     * Add a raw WHERE clause to the query with optional bindings.
     *
     * @param string $sql The raw SQL WHERE clause
     * @param array $bindings Optional bindings for parameters
     * @param string $boolean The boolean operator (AND/OR)
     * @return self
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->conditions[] = [
            'type' => 'RAW_WHERE',
            'sql' => $sql,
            'bindings' => $bindings,
            'boolean' => $boolean
        ];

        return $this;
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
        $query = clone $this;
        $page = request()->page ?? 1;
        $perPage = $perPage ?? $query->rowPerPage;

        if (!is_int($perPage) || $perPage <= 0) {
            $perPage = 15;
        }

        $offset = ($page - 1) * $perPage;
        $total = $query->count();
        $results = $query->limit($perPage)->offset($offset)->get()->all();

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

        foreach ($this->orderBy as $order) {
            if (isset($order['type']) && $order['type'] === 'RAW_ORDER_BY' && !empty($order['bindings'])) {
                foreach ($order['bindings'] as $value) {
                    $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
                }
            }
        }

        foreach ($this->conditions as $condition) {
            if (isset($condition['type'])) {
                // Handle nested conditions - recursively binding values
                if ($condition['type'] === 'NESTED') {
                    $this->bindNestedValues($stmt, $condition['query'], $index);
                    continue;
                }

                // Bind all RAW_* bindings
                if (in_array($condition['type'], ['RAW_SELECT', 'RAW_GROUP_BY', 'RAW_WHERE'])) {
                    if (!empty($condition['bindings']) && is_array($condition['bindings'])) {
                        foreach ($condition['bindings'] as $value) {
                            $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
                        }
                    }

                    continue;
                }

                // Skip EXISTS/NOT EXISTS
                if (in_array($condition['type'], ['EXISTS', 'NOT EXISTS'])) {
                    continue;
                }
            }

            // Handle IS NULL / IS NOT NULL (no binding needed)
            if (isset($condition[2]) && in_array($condition[2], ['IS NULL', 'IS NOT NULL'])) {
                continue;
            }

            // BETWEEN / NOT BETWEEN
            if (isset($condition[2]) && in_array($condition[2], ['BETWEEN', 'NOT BETWEEN'])) {
                $stmt->bindValue($index++, $condition[3], $this->getPdoParamType($condition[3]));
                $stmt->bindValue($index++, $condition[4], $this->getPdoParamType($condition[4]));
                continue;
            }

            // IN clause
            if (isset($condition[2]) && $condition[2] === 'IN') {
                foreach ($condition[3] as $value) {
                    $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
                }
                continue;
            }

            // Standard case: column, operator, value
            if (isset($condition[3])) {
                $stmt->bindValue($index++, $condition[3], $this->getPdoParamType($condition[3]));
            }
        }
    }

    /**
     * Bind values from nested queries
     *
     * @param PDOStatement $stmt
     * @param self $nestedQuery
     * @param int $index
     * @return void
     */
    protected function bindNestedValues(PDOStatement $stmt, self $nestedQuery, int &$index): void
    {
        foreach ($nestedQuery->conditions as $nestedCondition) {
            if (isset($nestedCondition['type']) && $nestedCondition['type'] === 'NESTED') {
                $this->bindNestedValues($stmt, $nestedCondition['query'], $index);
                continue;
            }

            // Bind regular nested conditions
            if (!isset($nestedCondition['type'])) {
                if (in_array($nestedCondition[2], ['BETWEEN', 'NOT BETWEEN'])) {
                    $stmt->bindValue($index++, $nestedCondition[3], $this->getPdoParamType($nestedCondition[3]));
                    $stmt->bindValue($index++, $nestedCondition[4], $this->getPdoParamType($nestedCondition[4]));
                } elseif ($nestedCondition[2] === 'IN') {
                    foreach ($nestedCondition[3] as $value) {
                        $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
                    }
                } elseif (!in_array($nestedCondition[2], ['IS NULL', 'IS NOT NULL'])) {
                    $stmt->bindValue($index++, $nestedCondition[3], $this->getPdoParamType($nestedCondition[3]));
                }
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
            $lastInsertId = $this->pdo->lastInsertId();

            return $lastInsertId ? (int) $lastInsertId : false;
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Insert new records or update existing ones (upsert).
     *
     * @param array $values Array of records to insert/update
     * @param array|string $uniqueBy Column(s) that uniquely identify records
     * @param array|null $updateColumns Columns to update if record exists (null means update all)
     * @param bool $ignoreErrors Whether to continue on error (like duplicate keys)
     * @return int Number of affected rows
     * @throws PDOException
     */
    public function upsert(array $values, array|string $uniqueBy, ?array $updateColumns = null, bool $ignoreErrors = false): int
    {
        if (empty($values)) {
            return 0;
        }

        // Normalize the uniqueBy parameter
        $uniqueBy = (array) $uniqueBy;
        if (empty($uniqueBy)) {
            throw new \InvalidArgumentException('Unique key columns must be specified');
        }

        // Handle timestamp configuration
        static $timestamps = [];
        $hasCastToDateAttribute = false;
        $class = $this->modelClass;
        $model = $this->getModel();

        if (!array_key_exists($class, $timestamps)) {
            $timestamps[$class] = $this->getClassProperty($class, 'timeStamps');
            if ($this->propertyHasAttribute($class, 'timeStamps', CastToDate::class)) {
                $hasCastToDateAttribute = true;
            }
        }

        $usesTimestamps = $timestamps[$class] && $model->usesTimestamps();
        $currentTime = $hasCastToDateAttribute ? now()->startOfDay() : now();

        // Get column names from first record and add timestamp columns if needed
        $columns = array_keys(reset($values));
        if ($usesTimestamps) {
            if (!in_array('created_at', $columns)) {
                $columns[] = 'created_at';
            }
            if (!in_array('updated_at', $columns)) {
                $columns[] = 'updated_at';
            }
        }

        // Use proper column quoting based on driver
        $quoteChar = $this->getDriver() === 'mysql' ? '`' : '"';
        $columnsStr = implode(', ', array_map(fn($col) => "{$quoteChar}{$col}{$quoteChar}", $columns));

        // Prepare placeholders and bindings
        $placeholders = [];
        $bindings = [];

        foreach ($values as $record) {
            // Validate record structure
            if (array_diff(array_keys($record), $columns)) {
                throw new \InvalidArgumentException('All records must have the same columns');
            }

            // Add timestamps if needed
            if ($usesTimestamps) {
                if (!isset($record['created_at'])) {
                    $record['created_at'] = $currentTime;
                }
                $record['updated_at'] = $currentTime;
            }

            $rowPlaceholders = [];
            foreach ($columns as $column) {
                $rowPlaceholders[] = '?';
                $bindings[] = $record[$column] ?? null;
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        // Determine columns to update
        $updateColumns = $updateColumns ?? array_diff($columns, $uniqueBy);

        // Always include updated_at in updates if using timestamps
        if ($usesTimestamps && !in_array('updated_at', $updateColumns)) {
            $updateColumns[] = 'updated_at';
        }

        // Build update statements with proper quoting
        $updateStatements = array_map(
            fn($col) => "{$quoteChar}{$col}{$quoteChar} = VALUES({$quoteChar}{$col}{$quoteChar})",
            $updateColumns
        );

        // Build the SQL query
        $sql = $this->getUpsertSql(
            columnsStr: $columnsStr,
            placeholders: $placeholders,
            updateStatements: $updateStatements,
            uniqueBy: $uniqueBy,
            updateColumns: $updateColumns,
            ignoreErrors: $ignoreErrors
        );

        try {
            $stmt = $this->pdo->prepare($sql);

            // Bind all values
            foreach ($bindings as $index => $value) {
                $stmt->bindValue($index + 1, $value, $this->getPdoParamType($value));
            }

            $stmt->execute();

            $rowCount = $stmt->rowCount();

            // Normalize return value: always return the number of unique rows processed
            if ($this->getDriver() === 'mysql' && $rowCount > count($values)) {
                // MySQL returns 2 for each updated row,
                // But intentionally we are returning 1
                return count($values);
            }

            return $rowCount;
        } catch (PDOException $e) {
            if ($ignoreErrors) {
                return 0;
            }
            throw new PDOException("Database error during upsert: " . $e->getMessage());
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
        $class = $this->modelClass;

        if (app($class)->usesTimestamps()) {
            $hasCastToDate = $this->propertyHasAttribute($class, 'timeStamps', CastToDate::class);
            $attributes['updated_at'] = $hasCastToDate
                ? now()->startOfDay()
                : now();
        }

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
        $groupConcatExpression = $this->getGroupConcatExpression($column, $separator);
        $this->select(["{$groupConcatExpression} as aggregate"]);

        $result = $this->first();

        return (string) ($result->aggregate ?? '');
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
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount must be positive");
        }

        $sql = "UPDATE {$this->table} SET {$column} = {$column} {$operator} ?";
        $bindings = [$amount];

        if (!empty($extra)) {
            $extraUpdates = [];
            foreach ($extra as $key => $value) {
                $extraUpdates[] = "{$key} = ?";
                $bindings[] = $value;
            }
            $sql .= ', ' . implode(', ', $extraUpdates);
        }

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

    /**
     * Exclude columns from the select query
     *
     * @param string|array ...$columns Columns to exclude (can be array or multiple strings)
     * @return self
     */
    public function omit(string|array ...$columns): self
    {
        $columns = count($columns) === 1 && is_array($columns[0])
            ? $columns[0]
            : $columns;

        if ($this->fields === ['*']) {
            $this->fields = $this->getTableColumns();
        }

        $this->fields = array_diff($this->fields, $columns);

        return $this;
    }

    /**
     * Filter records that have at least one related record matching the given condition
     *
     * @param string $relation
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return self
     */
    public function whereLinked($relation, $column, $operator = null, $value = null): self
    {
        if (func_num_args() === 3) {
            $value = $operator;
            $operator = '=';
        }

        // Use the present() method to filter models that have the relationship
        // and apply the additional condition to the related models
        return $this->present($relation, function ($query) use ($column, $operator, $value) {
            $query->where($column, $operator, $value);
        });
    }

    /**
     * Handle dynamic method calls into the builder.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->modelClass, $bindMethod = '__' . ucfirst($method))) {
            return $this->adjustBindQuery($bindMethod, $parameters);
        }

        if (str_starts_with($method, 'where')) {
            return $this->resolveDynamicKeyToCondition($method, $parameters);
        }

        throw new \BadMethodCallException(sprintf(
            'Method %s::%s does not exist.',
            static::class,
            $method
        ));
    }

    /**
     * Apply the given anonymous function on the current builder instance.
     *
     * @param string $bind
     * @param array $parameters
     * @return mixed
     */
    protected function adjustBindQuery($bind, $parameters)
    {
        return $this->getModel()->$bind($this, ...$parameters);
    }

    /**
     * Handle dynamic "where" clause methods.
     *
     * @param string $method
     * @param array $parameters
     * @return self
     */
    protected function resolveDynamicKeyToCondition(string $method, array $parameters): self
    {
        $column = $this->camelToSnake(lcfirst(substr($method, 5)));

        $operator = '=';
        $value = $parameters[0] ?? null;

        if (count($parameters) === 2) {
            $operator = $parameters[0];
            $value = $parameters[1];
        }

        if ($value === null) {
            if ($operator === '=') {
                return $this->whereNull($column);
            } elseif ($operator === '!=') {
                return $this->whereNotNull($column);
            }
        }

        return $this->where($column, $operator, $value);
    }

    /**
     * Add a WHERE LIKE condition with driver-agnostic case handling.
     *
     * @param string $field
     * @param string $value
     * @param bool $caseSensitive
     * @return self
     */
    public function whereLike(string $field, string $value, bool $caseSensitive = false): self
    {
        return $this->addLikeCondition($field, $value, $caseSensitive, 'AND');
    }

    /**
     * Add an OR WHERE LIKE condition with driver-agnostic case handling.
     *
     * @param string $field
     * @param string $value
     * @param bool $caseSensitive
     * @return self
     */
    public function orWhereLike(string $field, string $value, bool $caseSensitive = false): self
    {
        return $this->addLikeCondition($field, $value, $caseSensitive, 'OR');
    }

    /**
     * Adds a dynamic search filter to a query based on given attributes and a search term.
     *
     * @param array $attributes
     * @param string $searchTerm
     * @return self
     */
    public function search(array $attributes, ?string $searchTerm = null): self
    {
        if (empty($searchTerm)) {
            return $this;
        }

        $searchQuery = function (Builder $query) use ($attributes, $searchTerm) {
            foreach ($attributes as $attribute) {
                if (str_contains($attribute, '.')) {
                    [$relation, $column] = explode('.', $attribute, 2);
                    $query->orPresent(
                        $relation,
                        fn(Builder $q) => $q->whereLike($column, $searchTerm)
                    );
                } else {
                    $query->orWhereLike($attribute, $searchTerm);
                }
            }
        };

        return $this->where($searchQuery);
    }

    /**
     * Convert camelCase to snake_case for column names
     *
     * @param string $input
     * @return string
     */
    protected function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Reset the builder state
     *
     * @return self
     */
    public function reset(): self
    {
        $this->fields = ['*'];
        $this->conditions = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->limit = null;
        $this->offset = null;
        $this->joins = [];
        $this->eagerLoad = [];

        return $this;
    }
}
