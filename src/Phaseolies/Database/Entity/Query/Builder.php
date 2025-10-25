<?php

namespace Phaseolies\Database\Entity\Query;

use Phaseolies\Support\Collection;
use PDO;
use PDOException;

class Builder
{
    /**
     * The columns to be selected in a SELECT query
     *
     * @var array
     */
    protected array $fields = ['*'];

    /**
     * The collection of WHERE and OR WHERE conditions
     *
     * @var array
     */
    protected array $conditions = [];

    /**
     * The ORDER BY clauses for sorting query results
     *
     * @var array
     */
    protected array $orderBy = [];

    /**
     * The maximum number of records to retrieve
     *
     * @var int|null
     */
    protected ?int $limit = null;

    /**
     * Create a new Builder instance.
     *
     * @param PDO $pdo
     * @param string $table
     */
    public function __construct(protected PDO $pdo, protected string $table) {}

    /**
     * Specify which columns to select in the query
     *
     * @param array|string ...$fields
     * @return self
     */
    public function select(array|string ...$fields): self
    {
        $fields = count($fields) === 1 && is_array($fields[0])
            ? $fields[0]
            : $fields;

        $this->fields = $fields;

        return $this;
    }

    /**
     * Add a basic WHERE condition to the query
     *
     * @param string $column
     * @param string|null $operator
     * @param mixed|null $value
     * @return self
     */
    public function where($column, $operator = null, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = ['AND', $column, $operator, $value];

        return $this;
    }

    /**
     * Add an OR WHERE condition to the query
     *
     * @param string $column
     * @param string|null $operator
     * @param mixed|null $value
     * @return self
     */
    public function orWhere($column, $operator = null, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = ['OR', $column, $operator, $value];

        return $this;
    }

    /**
     * Add an ORDER BY clause to the query
     *
     * @param string $column
     * @param string $direction
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = [$column, $direction];

        return $this;
    }

    /**
     * Set the LIMIT clause
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
     * Execute the query as a SELECT statement and return results as a Collection
     *
     * @return Collection
     * @throws PDOException
     */
    public function get(): Collection
    {
        $sql = $this->toSql();
        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new Collection('array', $results);
    }

    /**
     * Execute the query and return the first result
     *
     * @return array|null
     */
    public function first(): ?array
    {
        $this->limit(1);

        $results = $this->get()->all();

        return $results[0] ?? null;
    }

    /**
     * Insert a new record into the database
     *
     * @param array $values
     * @return bool
     * @throws PDOException
     */
    public function insert(array $values): bool
    {
        $columns = implode(', ', array_keys($values));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindValuesForInsert($stmt, $values);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Update existing records in the database
     *
     * @param array $values
     * @return int
     * @throws PDOException
     */
    public function update(array $values): int
    {
        $setClause = implode(', ', array_map(fn($key) => "{$key} = ?", array_keys($values)));

        $sql = "UPDATE {$this->table} SET {$setClause}";

        if (!empty($this->conditions)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }

        try {
            $stmt = $this->pdo->prepare($sql);

            // Bind SET values
            $index = 1;
            foreach ($values as $value) {
                $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
            }

            // Bind WHERE values
            foreach ($this->conditions as $condition) {
                $stmt->bindValue($index++, $condition[3], $this->getPdoParamType($condition[3]));
            }

            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Delete records matching the current query conditions.
     *
     * @return int
     * @throws PDOException
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->conditions)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }

        try {
            $stmt = $this->pdo->prepare($sql);

            $index = 1;
            foreach ($this->conditions as $condition) {
                $stmt->bindValue($index++, $condition[3], $this->getPdoParamType($condition[3]));
            }

            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Execute a COUNT query and return the total number of matching records
     *
     * @return int
     */
    public function count(): int
    {
        $this->select(['COUNT(*) as count']);

        $result = $this->first();

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Check if any record exists that matches the current query conditions.
     *
     * @return bool
     */
    public function exists(): bool
    {
        $this->select(['1 as exists'])->limit(1);

        return $this->first() !== null;
    }

    /**
     * Generate the SQL query string based on the builder's state.
     *
     * @return string
     */
    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->fields) . ' FROM ' . $this->table;

        if (!empty($this->conditions)) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }

        if (!empty($this->orderBy)) {
            $orderByStrings = array_map(fn($o) => "{$o[0]} {$o[1]}", $this->orderBy);
            $sql .= ' ORDER BY ' . implode(', ', $orderByStrings);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        return $sql;
    }

    /**
     * Compile the WHERE conditions into a SQL-compatible string.
     *
     * @return string
     */
    protected function compileWheres(): string
    {
        $wheres = [];

        foreach ($this->conditions as $index => $condition) {
            [$boolean, $column, $operator, $value] = $condition;

            if ($index === 0) {
                $wheres[] = "{$column} {$operator} ?";
            } else {
                $wheres[] = "{$boolean} {$column} {$operator} ?";
            }
        }

        return implode(' ', $wheres);
    }

    /**
     * Bind WHERE condition values to a prepared statement.
     *
     * @param \PDOStatement $stmt
     * @return void
     */
    protected function bindValues(\PDOStatement $stmt): void
    {
        $index = 1;
        foreach ($this->conditions as $condition) {
            $stmt->bindValue($index++, $condition[3], $this->getPdoParamType($condition[3]));
        }
    }

    /**
     * Bind values for INSERT statements
     *
     * @param \PDOStatement $stmt
     * @param array $values
     * @return void
     */
    protected function bindValuesForInsert(\PDOStatement $stmt, array $values): void
    {
        $index = 1;
        foreach ($values as $value) {
            $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
        }
    }

    /**
     * Determine the correct PDO parameter type for a given value
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
