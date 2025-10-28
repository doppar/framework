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
}
