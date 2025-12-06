<?php

namespace Phaseolies\Database\Entity\Query;

use PDOStatement;

trait InteractsWithConditionBinding
{
    /**
     * Build the WHERE clause SQL string for any query type
     *
     * @return array [sql, bindings]
     */
    protected function buildWhereClause(): array
    {
        if (empty($this->conditions)) {
            return ['', []];
        }

        $sqlParts = [];
        $bindings = [];

        foreach ($this->conditions as $condition) {
            if (isset($condition['type'])) {
                // Handle special condition types
                switch ($condition['type']) {
                    case 'RAW_WHERE':
                        $sqlParts[] = $condition['sql'];
                        $bindings = array_merge($bindings, $condition['bindings'] ?? []);
                        break;

                    case 'NESTED':
                        [$nestedSql, $nestedBindings] = $condition['query']->buildWhereClause();
                        $sqlParts[] = "({$nestedSql})";
                        $bindings = array_merge($bindings, $nestedBindings);
                        break;

                    case 'EXISTS':
                    case 'NOT EXISTS':
                        $sqlParts[] = "{$condition['type']} ({$condition['subquery']})";
                        $bindings = array_merge($bindings, $condition['bindings'] ?? []);
                        break;
                }
            } else {
                // Handle regular conditions
                [$conditionSql, $conditionBindings] = $this->buildCondition($condition);
                $sqlParts[] = $conditionSql;
                $bindings = array_merge($bindings, $conditionBindings);
            }
        }

        $sql = implode(' ', $this->formatConditionStrings($sqlParts));
        return [$sql, $bindings];
    }

    /**
     * Build a single condition into SQL and extract bindings
     *
     * @param array $condition
     * @return array [sql, bindings]
     */
    protected function buildCondition(array $condition): array
    {
        $boolean = $condition[0] ?? 'AND';
        $column = $condition[1] ?? '';
        $operator = $condition[2] ?? '';
        $value = $condition[3] ?? null;
        $extra = $condition[4] ?? null;

        $bindings = [];
        $sql = '';

        // Handle different operator types
        switch ($operator) {
            case 'IS NULL':
            case 'IS NOT NULL':
                $sql = "{$column} {$operator}";
                break;

            case 'BETWEEN':
            case 'NOT BETWEEN':
                $sql = "{$column} {$operator} ? AND ?";
                $bindings = [$value, $extra];
                break;

            case 'IN':
                if (empty($value)) {
                    $sql = "1=0"; // Empty IN clause is always false
                } else {
                    $placeholders = implode(',', array_fill(0, count($value), '?'));
                    $sql = "{$column} IN ({$placeholders})";
                    $bindings = $value;
                }
                break;

            default:
                $sql = "{$column} {$operator} ?";
                $bindings = [$value];
                break;
        }

        return [$sql, $bindings];
    }

    /**
     * Format condition strings with boolean operators
     *
     * @param array $conditionStrings
     * @return array
     */
    protected function formatConditionStrings(array $conditionStrings): array
    {
        $formatted = [];

        foreach ($this->conditions as $index => $condition) {
            $hasType = isset($condition['type']);

            if ($hasType) {
                $boolean = $condition['boolean'] ?? ($index > 0 ? 'AND' : '');
            } else {
                $boolean = $condition[0] ?? ($index > 0 ? 'AND' : '');
            }

            if ($index > 0 && $boolean) {
                $formatted[] = $boolean;
            }
            $formatted[] = $conditionStrings[$index];
        }

        return $formatted;
    }

    /**
     * Bind all values to a prepared statement
     *
     * @param PDOStatement $stmt
     * @param array $additionalBindings
     * @return void
     */
    protected function bindAllValues(PDOStatement $stmt, array $additionalBindings = []): void
    {
        $index = 1;

        // Bind additional values first
        // SET clause values for UPDATE
        foreach ($additionalBindings as $value) {
            $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
        }

        // Bind ORDER BY raw bindings
        foreach ($this->orderBy as $order) {
            if (isset($order['type']) && $order['type'] === 'RAW_ORDER_BY' && !empty($order['bindings'])) {
                foreach ($order['bindings'] as $value) {
                    $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
                }
            }
        }

        // Get WHERE clause bindings
        [$whereSql, $whereBindings] = $this->buildWhereClause();

        // Bind WHERE clause values
        foreach ($whereBindings as $value) {
            $stmt->bindValue($index++, $value, $this->getPdoParamType($value));
        }
    }
}