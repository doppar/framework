<?php

namespace Phaseolies\Database\Entity\Query;

use Phaseolies\Support\Collection;

trait CollectsRelations
{
    /**
     * Collect all related models from a collection for a given relation
     *
     * @param Collection $collection
     * @param string $relation
     * @return array [models, modelClass]
     */
    protected function collectRelatedModels(Collection $collection, string $relation): array
    {
        $allRelatedModels = [];
        $relatedModelClass = null;

        foreach ($collection->all() as $model) {
            if ($model->relationLoaded($relation)) {
                $related = $model->getRelation($relation);

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

        return [$allRelatedModels, $relatedModelClass];
    }

    /**
     * Collect nested related models following a relation path
     *
     * @param Collection $collection
     * @param string $nestedRelation
     * @return array [models, modelClass, remainingPath]
     */
    protected function collectNestedRelatedModels(Collection $collection, string $nestedRelation): array
    {
        $relations = explode('.', $nestedRelation);
        $primaryRelation = array_shift($relations);
        $remainingPath = implode('.', $relations);

        // First load the primary relation if not already loaded
        if (!$this->areRelationsLoaded($collection, [$primaryRelation])) {
            $this->loadRelation($collection, $primaryRelation);
        }

        [$allRelatedModels, $relatedModelClass] = $this->collectRelatedModels($collection, $primaryRelation);

        return [$allRelatedModels, $relatedModelClass, $remainingPath];
    }

    /**
     * Check if relations are loaded on all models in collection
     *
     * @param Collection $collection
     * @param array $relations
     * @return bool
     */
    protected function areRelationsLoaded(Collection $collection, array $relations): bool
    {
        foreach ($collection->all() as $model) {
            foreach ($relations as $relation) {
                if (!$model->relationLoaded($relation)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Parse relation string to extract relation name and column selection
     * 
     * @param string $relation
     * @return array [relationName, columns]
     */
    protected function parseRelationWithColumns(string $relation): array
    {
        if (!str_contains($relation, ':')) {
            return [$relation, []];
        }

        [$relationName, $columnsString] = explode(':', $relation, 2);
        $columns = array_map('trim', explode(',', $columnsString));

        return [$relationName, $columns];
    }

    /**
     * Parse nested relation with column selection
     * 
     * @param string $nestedRelation
     * @return array [relations, finalColumns]
     */
    protected function parseNestedRelationWithColumns(string $nestedRelation): array
    {
        $parts = explode('.', $nestedRelation);
        $relations = [];
        $finalColumns = [];

        foreach ($parts as $index => $part) {
            [$relationName, $columns] = $this->parseRelationWithColumns($part);
            $relations[] = $relationName;

            // Only the last relation can have column selection
            if ($index === count($parts) - 1 && !empty($columns)) {
                $finalColumns = $columns;
            }
        }

        return [$relations, $finalColumns];
    }

    /**
     * Apply column selection constraint to a query
     * 
     * @param callable|null $existingConstraint
     * @param array $columns
     * @return callable
     */
    protected function createColumnConstraint(?callable $existingConstraint, array $columns): callable
    {
        return function ($query) use ($existingConstraint, $columns) {
            if (!empty($columns)) {
                $query->select($columns);
            }

            if ($existingConstraint !== null) {
                $existingConstraint($query);
            }
        };
    }

    /**
     * Ensure foreign key is included in query selection
     *
     * @param self $query
     * @param string $foreignKey
     * @return void
     */
    protected function ensureForeignKeyInSelection($query, string $foreignKey): void
    {
        if ($query->fields !== ['*']) {
            $hasKey = false;
            foreach ($query->fields as $field) {
                $plainField = str_replace(['`', '"'], '', $field);
                if (strpos($plainField, '.') !== false) {
                    $parts = explode('.', $plainField);
                    $plainField = end($parts);
                }
                if ($plainField === $foreignKey) {
                    $hasKey = true;
                    break;
                }
            }

            if (!$hasKey) {
                $query->fields[] = $foreignKey;
            }
        }
    }

    /**
     * Ensure required keys are included in query selection
     *
     * @param self $query
     * @param string $foreignKey
     * @param string $primaryKey
     * @return void
     */
    protected function ensureRequiredKeysInSelection($query, string $foreignKey, string $primaryKey): void
    {
        if ($query->fields === ['*']) {
            // No need to add anything if selecting all
            return;
        }

        $keysToEnsure = [$foreignKey, $primaryKey];

        foreach ($keysToEnsure as $key) {
            $hasKey = false;
            foreach ($query->fields as $field) {
                $plainField = str_replace(['`', '"'], '', $field);
                if (strpos($plainField, '.') !== false) {
                    $parts = explode('.', $plainField);
                    $plainField = end($parts);
                }
                if ($plainField === $key) {
                    $hasKey = true;
                    break;
                }
            }

            if (!$hasKey) {
                $query->fields[] = $key;
            }
        }
    }
}
