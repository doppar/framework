<?php

namespace Phaseolies\Database\Eloquent\Query;

use RuntimeException;
use Phaseolies\Support\Collection;
use Phaseolies\Database\Eloquent\Builder;

trait QueryUtils
{
    /**
     * Execute a query and get the result as a key-value dictionary
     *
     * @param string $keyColumn The column to use as keys
     * @param string $valueColumn The column to use as values
     * @return Collection
     */
    public function toDictionary(string $keyColumn, string $valueColumn): Collection
    {
        $results = $this->select([$keyColumn, $valueColumn])->get();
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->$keyColumn] = $result->$valueColumn;
        }

        return new Collection('array', $dictionary);
    }

    /**
     * Execute a query and get the difference between two columns
     *
     * @param string $column1
     * @param string $column2
     * @param string $alias
     * @return Collection
     */
    public function toDiff(string $column1, string $column2, string $alias = 'difference'): Collection
    {
        return $this->select("{$column1} - {$column2} as {$alias}")->get();
    }

    /**
     * Execute a query and get the result as a nested tree structure
     *
     * @param string $primaryKey The primary key column
     * @param string $parentColumn The parent key column
     * @param string $index The key to use for children (default: 'children')
     * @return Collection
     */
    public function toTree(string $primaryKey, string $parentColumn, string $index = 'children'): Collection
    {
        $items = $this->get();
        $grouped = [];
        $visited = [];

        foreach ($items as $item) {
            $parentId = $item->$parentColumn;
            if (!isset($grouped[$parentId])) {
                $grouped[$parentId] = [];
            }
            $grouped[$parentId][] = $item;
        }

        $buildTree = function ($parentId = null) use (&$buildTree, &$visited, $grouped, $index, $primaryKey) {
            $branch = [];

            if (isset($grouped[$parentId])) {
                foreach ($grouped[$parentId] as $item) {
                    $itemId = $item->$primaryKey;

                    // Check for circular reference
                    if (isset($visited[$itemId])) {
                        throw new \RuntimeException("Circular reference detected in tree structure for item ID: {$itemId}");
                    }

                    $visited[$itemId] = true;
                    $children = $buildTree($itemId);
                    unset($visited[$itemId]); // Backtrack

                    if (!empty($children)) {
                        $item->setRelation($index, new Collection($this->modelClass, $children));
                    }
                    $branch[] = $item;
                }
            }

            return $branch;
        };

        $treeArray = $buildTree();

        return new Collection($this->modelClass, $treeArray);
    }

    /**
     * Execute a query and get the ratio between two columns
     *
     * @param string $numerator
     * @param string $denominator
     * @param string $alias
     * @param int $precision
     * @return Collection
     */
    public function toRatio(string $numerator, string $denominator, string $alias = 'ratio', int $precision = 2): Collection
    {
        return $this->selectRaw("ROUND({$numerator} / NULLIF({$denominator}, 0), {$precision}) as {$alias}")->get();
    }

    /**
     * Execute a query with a random sample
     *
     * @param int $limit The number of rows to sample
     * @return Builder
     */
    public function random(int $limit): Builder
    {
        return $this->orderByRaw($this->rand())->limit($limit);
    }

    /**
     * Search a JSON column
     *
     * @param string $column The name of the JSON column to search
     * @param string $path The JSON path to search within (e.g., '$.notifications.email')
     * @param mixed $value The value to search for (will be JSON encoded if not boolean)
     * @param string $boolean The logical operator to use ('AND' or 'OR') for chaining conditions
     * @return self Returns the query builder instance for method chaining
     */
    public function whereJson(string $column, string $path, $value, string $boolean = 'AND'): self
    {
        [$expression, $bindings] = $this->jsonContains($column, $path, $value);

        return $this->whereRaw($expression, $bindings, $boolean);
    }

    /**
     * Execute a query with a case-insensitive pattern matching filter
     *
     * @param string $column The column to filter
     * @param string $pattern The pattern to match
     * @return Builder
     */
    public function iLike(string $column, string $pattern): Builder
    {
        return $this->whereRaw("LOWER({$column}) LIKE LOWER(?)", [$pattern]);
    }

    /**
     * Execute a query with a custom transformation function
     *
     * @param callable $callback A function that receives the query builder and returns a SQL expression
     * @param string $alias The column alias (default: 'transformed')
     * @return self
     */
    public function transformBy(callable $callback, string $alias = 'transformed'): self
    {
        $transformationBuilder = new self($this->pdo, $this->table, $this->modelClass, $this->rowPerPage);

        $expression = $callback($transformationBuilder);

        // If the function didn't return a string, use the builder's SQL
        if (!is_string($expression)) {
            $expression = $transformationBuilder->toSql();
        }

        if (str_contains($expression, ' ')) {
            $expression = "({$expression})";
        }

        $this->selectRaw("{$expression} as {$alias}");

        return $this;
    }

    /**
     * Execute a query with a pattern matching filter
     *
     * @param string $column The column to filter
     * @param string $pattern The pattern to match
     * @param string $type The pattern type (LIKE, REGEXP, SIMILAR TO, etc.)
     * @return Builder
     */
    public function wherePattern(string $column, string $pattern, string $type = 'LIKE'): Builder
    {
        return $this->whereRaw("{$column} {$type} ?", [$pattern]);
    }

    /**
     * Execute a query with a first/last value in window with partitioning
     *
     * @param string $column The column to get the first/last value from
     * @param string $orderColumn The column to order by for the window
     * @param string $partitionColumn The column to partition by
     * @param bool $first Whether to get the first (true) or last (false) value
     * @param string $alias The column alias
     * @return Builder
     */
    public function firstLastInWindow(
        string $column,
        string $orderColumn,
        string $partitionColumn,
        bool $first = true,
        string $alias = 'window_value'
    ): Builder {
        $function = $first ? 'FIRST_VALUE' : 'LAST_VALUE';

        return $this->selectRaw(
            "{$function}({$column}) OVER (
            PARTITION BY {$partitionColumn} 
            ORDER BY {$orderColumn} 
            ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
        ) as {$alias}"
        );
    }

    /**
     * Execute a query with a running difference calculation
     *
     * @param string $column The column to calculate the running difference for
     * @param string $orderColumn The column to order by for the window
     * @param string $alias The column alias
     * @return Builder
     */
    public function movingDifference(string $column, string $orderColumn, string $alias = 'running_diff'): Builder
    {
        return $this->selectRaw(
            "{$column} - LAG({$column}, 1, 0) OVER (ORDER BY {$orderColumn}) as {$alias}"
        );
    }

    /**
     * Execute a query with a moving average calculation
     *
     * @param string $column The column to calculate the moving average for
     * @param int $windowSize The window size for the moving average
     * @param string $orderColumn The column to order by for the window
     * @param string $alias The column alias
     * @return Builder
     */
    public function movingAverage(string $column, int $windowSize, string $orderColumn, string $alias = 'moving_avg'): Builder
    {
        return $this->selectRaw(
            "AVG({$column}) OVER (ORDER BY {$orderColumn} ROWS BETWEEN ? PRECEDING AND CURRENT ROW) as {$alias}",
            [$windowSize - 1]
        );
    }
}
