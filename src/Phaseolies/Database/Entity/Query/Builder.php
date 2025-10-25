<?php

namespace Phaseolies\Database\Entity\Query;

use PDOStatement;
use PDOException;
use PDO;
use Phaseolies\Database\Entity\Query\{
    Grammar,
    InteractsWithTimeframe,
    InteractsWithAggregateFucntion
};
use Phaseolies\Support\Facades\URL;
use Phaseolies\Support\Collection;

class Builder
{
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
     * The join clauses for the query.
     *
     * @var array
     */
    protected array $joins = [];

    /**
     * @param PDO $pdo
     * @param string $table
     * @param string $modelClass
     * @param int $rowPerPage
     */
    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    /**
     * Set the fields to select.
     *
     * @param array|string ...$fields
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
     * @param string|callable $field
     * @param mixed $operator
     * @param mixed $value
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
     * @param string|callable $field
     * @param mixed $operator
     * @param mixed $value
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
        $nestedQuery = new static($this->pdo, $this->table);

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
     * Conditionally add clauses to the query
     *
     * @param mixed $value
     * @param callable $callback
     * @param callable|null $default
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
     * @return bool false
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
     * Insert multiple records into the database in a single query
     *
     * @param array $rows
     * @return int
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

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                yield $row;
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
     * @return Collection
     */
    public function get(): Collection
    {
        $rows = [];
        foreach ($this->fetchLazy() as $row) {
            $rows[] = $row;
        }

        $collection = new Collection('array', $rows);

        unset($rows);
        if (gc_enabled()) {
            gc_collect_cycles();
        }

        return $collection;
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

            return new Collection('array', $result);
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Get the count of rows matching the current query.
     *
     * @param string $column
     * @return int
     * @throws PDOException
     */
    public function count(string $column = '*'): int
    {
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = null;

        if (!empty($this->groupBy)) {
            $groupColumns = implode(', ', $this->groupBy);

            $subQuery = clone $this;
            $subQuery->fields = $this->groupBy;
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
            $this->select(["COUNT($column) as aggregate"]);

            $result = $this->first();
            return (int) ($result->aggregate ?? 0);
        }
    }

    /**
     * Add a raw select expression to the query.
     *
     * @param string $expression
     * @param array $bindings
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
     * @param string $sql
     * @param array $bindings
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
     * @param string $sql
     * @param array $bindings
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
     * @param string $sql
     * @param array $bindings
     * @param string $boolean
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
     * Properly quote an identifier for SQL.
     *
     * @param string $identifier
     * @return string
     */
    protected function quoteIdentifier(string $identifier): string
    {
        if (strpos($identifier, '.') !== false) {
            return implode('.', array_map(
                fn($part) => "`{$part}`",
                explode('.', $identifier)
            ));
        }
        return "`{$identifier}`";
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
     * @param int $perPage
     * @param int $page
     * @return array
     */
    public function paginate(int $perPage): array
    {
        $page = request()->page ?? 1;

        if (!is_int($perPage) || $perPage <= 0) {
            $perPage = 15;
        }

        $countQuery = clone $this;
        $total = $countQuery->count();

        $offset = ($page - 1) * $perPage;
        $results = $this->limit($perPage)->offset($offset)->get()->all();

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
            'current_page' => (int) $page,
            'last_page' => (int) $lastPage,
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
     * @return int|false
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

        // Get column names from first record and add timestamp columns if needed
        $columns = array_keys(reset($values));

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

            $rowPlaceholders = [];
            foreach ($columns as $column) {
                $rowPlaceholders[] = '?';
                $bindings[] = $record[$column] ?? null;
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        // Determine columns to update
        $updateColumns = $updateColumns ?? array_diff($columns, $uniqueBy);

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
     * Delete records from the database.
     *
     * @return bool
     * @throws PDOException
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
     * @param string $column
     * @return Collection
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
     * @param string $separator
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
     * @param string $column
     * @param int $amount
     * @param array $extra
     * @return int
     */
    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        return $this->updateColumn($column, $amount, '+', $extra);
    }

    /**
     * Decrement a column's value by a given amount
     *
     * @param string $column
     * @param int $amount
     * @param array $extra
     * @return int
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
     * @param bool $not
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
            'AND'
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
     * @param bool $not
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
     * @param string $column
     * @param int $amount
     * @param string $operator + or -
     * @param array $extra
     * @return int
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
     * @param string|array ...$columns
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
     * Handle dynamic method calls into the builder.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
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

        return $this;
    }
}
