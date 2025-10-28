<?php

namespace Phaseolies\Database\Entity\Query;

use Phaseolies\Database\Entity\Model;

trait InteractsWithNestedRelations
{
    /**
     * Handle nested relationship exists checks (e.g., 'comments.reply')
     *
     * @param string $nestedRelation
     * @param callable|null $callback
     * @param string $boolean
     * @param string $type
     * @return self
     */
    private function handleNestedRelationshipExists(string $nestedRelation, ?callable $callback, string $boolean, string $type): self
    {
        $relations = explode('.', $nestedRelation);
        $model = $this->getModel();

        $subquery = $this->buildNestedRelationshipExistsSubquery($model, $relations, $callback);
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
     * Build a nested relationship EXISTS subquery
     *
     * @param Model $model
     * @param array $relations
     * @param callable|null $callback
     * @return string
     */
    private function buildNestedRelationshipExistsSubquery(Model $model, array $relations, ?callable $callback): string
    {
        $quote = fn($identifier) => $this->quoteIdentifier($identifier);
        $escapeValue = fn($val) => $this->escapeValue($val);

        $subqueryParts = [];
        $currentModel = $model;
        $previousTable = $this->table;
        $previousKey = null;

        foreach ($relations as $index => $relation) {
            if (!method_exists($currentModel, $relation)) {
                throw new \BadMethodCallException("Relationship {$relation} does not exist on model " . get_class($currentModel));
            }

            $currentModel->$relation();
            $relationType = $currentModel->getLastRelationType();
            $relatedModel = $currentModel->getLastRelatedModel();
            $foreignKey = $currentModel->getLastForeignKey();
            $localKey = $currentModel->getLastLocalKey();

            $relatedModelInstance = new $relatedModel();
            $relatedTable = $relatedModelInstance->getTable();
            $relatedPrimaryKey = $relatedModelInstance->getKeyName();

            if ($index === 0) {
                if ($relationType === 'bindToMany') {
                    $pivotTable = $currentModel->getLastPivotTable();
                    $relatedKey = $currentModel->getLastRelatedKey();

                    $subqueryParts['from'] = $quote($pivotTable);
                    $subqueryParts['joins'][] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($relatedPrimaryKey)} = {$quote($pivotTable)}.{$quote($relatedKey)}";
                    $subqueryParts['where'] = "{$quote($pivotTable)}.{$quote($foreignKey)} = {$quote($this->table)}.{$quote($localKey)}";

                    $previousTable = $relatedTable;
                    $previousKey = $relatedPrimaryKey;
                } else {
                    $subqueryParts['from'] = $quote($relatedTable);
                    $subqueryParts['where'] = "{$quote($relatedTable)}.{$quote($foreignKey)} = {$quote($this->table)}.{$quote($localKey)}";

                    $previousTable = $relatedTable;
                    $previousKey = $relatedPrimaryKey;
                }
            } else {
                if ($relationType === 'bindToMany') {
                    $pivotTable = $currentModel->getLastPivotTable();
                    $relatedKey = $currentModel->getLastRelatedKey();

                    $subqueryParts['joins'][] = "INNER JOIN {$quote($pivotTable)} ON {$quote($pivotTable)}.{$quote($foreignKey)} = {$quote($previousTable)}.{$quote($previousKey)}";
                    $subqueryParts['joins'][] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($relatedPrimaryKey)} = {$quote($pivotTable)}.{$quote($relatedKey)}";

                    $previousTable = $relatedTable;
                    $previousKey = $relatedPrimaryKey;
                } else {
                    $subqueryParts['joins'][] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($foreignKey)} = {$quote($previousTable)}.{$quote($previousKey)}";

                    $previousTable = $relatedTable;
                    $previousKey = $relatedPrimaryKey;
                }
            }

            $currentModel = $relatedModelInstance;
        }

        // Build the complete subquery
        $subquery = "SELECT 1 FROM {$subqueryParts['from']}";

        if (!empty($subqueryParts['joins'])) {
            $subquery .= ' ' . implode(' ', $subqueryParts['joins']);
        }

        $subquery .= " WHERE {$subqueryParts['where']}";

        // Apply callback conditions on the final table
        if ($callback && $previousTable) {
            $subQueryBuilder = $currentModel->query();
            $callback($subQueryBuilder);

            foreach ($subQueryBuilder->conditions as $condition) {
                if (isset($condition['type'])) {
                    continue;
                }

                $column = $condition[1];
                $operator = $condition[2];
                $value = $condition[3];

                // Qualify column with table name
                if (strpos($column, '.') === false) {
                    $column = "{$quote($previousTable)}.{$quote($column)}";
                } else {
                    $column = $quote($column);
                }

                $subquery .= $this->buildConditionClause($column, $operator, $value, $escapeValue);
            }
        }

        return $subquery;
    }

    /**
     * Handle nested whereLinked (e.g., 'comments.reply')
     *
     * @param string $nestedRelation
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return self
     */
    protected function whereLinkedNested(string $nestedRelation, string $column, $operator, $value): self
    {
        $relations = explode('.', $nestedRelation);
        $model = $this->getModel();

        // Nested EXISTS subquery
        $subquery = $this->buildNestedRelationshipSubquery($model, $relations, $column, $operator, $value);

        $this->conditions[] = [
            'type' => 'EXISTS',
            'subquery' => $subquery,
            'bindings' => [],
            'boolean' => 'AND'
        ];

        return $this;
    }


    /**
     * Build a nested relationship subquery for whereLinked
     *
     * @param Model $model
     * @param array $relations
     * @param string $column
     * @param mixed $operator
     * @param mixed $value
     * @return string
     */
    protected function buildNestedRelationshipSubquery(Model $model, array $relations, string $column, $operator, $value): string
    {
        $quote = fn($identifier) => $this->quoteIdentifier($identifier);
        $escapeValue = fn($val) => $this->escapeValue($val);

        $currentModel = $model;
        $currentTable = $this->table;
        $joins = [];
        $lastForeignKey = null;
        $lastLocalKey = null;

        // Process each relation in the chain
        foreach ($relations as $index => $relation) {
            if (!method_exists($currentModel, $relation)) {
                throw new \BadMethodCallException("Relationship {$relation} does not exist on model " . get_class($currentModel));
            }

            // Initialize the relationship to get metadata
            $currentModel->$relation();
            $relationType = $currentModel->getLastRelationType();
            $relatedModel = $currentModel->getLastRelatedModel();
            $foreignKey = $currentModel->getLastForeignKey();
            $localKey = $currentModel->getLastLocalKey();

            $relatedModelInstance = new $relatedModel();
            $relatedTable = $relatedModelInstance->getTable();
            $relatedPrimaryKey = $relatedModelInstance->getKeyName();

            if ($relationType === 'bindToMany') {
                // Many-to-many relationship
                $pivotTable = $currentModel->getLastPivotTable();
                $relatedKey = $currentModel->getLastRelatedKey();

                if ($index === 0) {
                    // First relation: link from main table to pivot
                    $joins[] = "INNER JOIN {$quote($pivotTable)} ON {$quote($pivotTable)}.{$quote($foreignKey)} = {$quote($currentTable)}.{$quote($localKey)}";
                } else {
                    // Subsequent relation: link from previous table to pivot
                    $joins[] = "INNER JOIN {$quote($pivotTable)} ON {$quote($pivotTable)}.{$quote($foreignKey)} = {$quote($currentTable)}.{$quote($lastLocalKey)}";
                }

                // Link from pivot to related table
                $joins[] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($relatedPrimaryKey)} = {$quote($pivotTable)}.{$quote($relatedKey)}";
            } else {
                // One-to-one or one-to-many relationship
                if ($index === 0) {
                    // First relation: link from main table
                    $joins[] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($foreignKey)} = {$quote($currentTable)}.{$quote($localKey)}";
                } else {
                    // Subsequent relation: link from previous table
                    $joins[] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($foreignKey)} = {$quote($currentTable)}.{$quote($lastLocalKey)}";
                }
            }

            // Update for next iteration
            $currentModel = $relatedModelInstance;
            $currentTable = $relatedTable;
            $lastForeignKey = $foreignKey;
            $lastLocalKey = $relatedPrimaryKey;
        }

        // Build the final subquery
        $subquery = "SELECT 1 FROM {$quote($this->table)} AS {$quote($this->table . '_sub')}";

        // Add all the joins
        $subquery .= ' ' . implode(' ', $joins);

        // Add the WHERE clause linking back to the main table
        if (!empty($relations)) {
            $firstRelation = $relations[0];
            $model->$firstRelation();
            $firstLocalKey = $model->getLastLocalKey();

            $subquery .= " WHERE {$quote($this->table . '_sub')}.{$quote($firstLocalKey)} = {$quote($this->table)}.{$quote($firstLocalKey)}";
        }

        // Add the condition on the final table
        $finalTable = $currentTable;
        $columnQualified = strpos($column, '.') === false
            ? "{$quote($finalTable)}.{$quote($column)}"
            : $quote($column);

        $subquery .= $this->buildConditionClause($columnQualified, $operator, $value, $escapeValue);

        $subquery .= ' LIMIT 1';

        return $subquery;
    }
}
