<?php

namespace Phaseolies\Database\Entity\Relations;

use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Support\Collection;

class BindToMany extends Builder
{
    /**
     * Create a related record and link it to the parent
     *
     * @param array $attributes
     * @param array $pivotData
     * @return Model
     * @throws \LogicException
     */
    public function create(array $attributes, array $pivotData = []): Model
    {
        $this->assertParentIsSaved();

        return $this->inTransaction(function () use ($attributes, $pivotData) {
            $model = parent::create($attributes);

            $this->link([$model->getKey()], $pivotData);

            return $model;
        });
    }

    /**
     * Create several related records and link them to the parent.
     *
     * @param array<int, array> $records
     * @param array $pivotData
     * @return Collection
     * @throws \LogicException
     */
    public function createMany(array $records, array $pivotData = []): Collection
    {
        $this->assertParentIsSaved();

        return $this->inTransaction(function () use ($records, $pivotData) {
            $models = [];

            foreach ($records as $attributes) {
                $models[] = $this->create($attributes, $pivotData);
            }

            return new Collection($this->modelClass, $models);
        });
    }

    /**
     * Save a model and link it to the parent
     *
     * @param Model $related
     * @param array $pivotData
     * @return Model|false
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function store(Model $related, array $pivotData = []): Model|false
    {
        $this->assertParentIsSaved();

        $expected = $this->modelClass;

        if (!($related instanceof $expected)) {
            throw new \InvalidArgumentException(
                'Expected an instance of ' . $expected . ', got ' . $related::class . '.'
            );
        }

        return $this->inTransaction(function () use ($related, $pivotData) {
            if ($this->withoutHooks) {
                $related->withoutHook();
            }

            if (!$related->save()) {
                return false;
            }

            $this->linkOnce($related->getKey(), $pivotData);

            return $related;
        });
    }

    /**
     * Save several models and link them to the parent, in one transaction
     *
     * @param iterable<Model> $models
     * @param array $pivotData
     * @return array<int, Model|false>
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function storeMany(iterable $models, array $pivotData = []): array
    {
        $this->assertParentIsSaved();

        return $this->inTransaction(function () use ($models, $pivotData) {
            $stored = [];

            foreach ($models as $model) {
                $stored[] = $this->store($model, $pivotData);
            }

            return $stored;
        });
    }

    /**
     * Link a related model to the parent, if not already linked. If already linked, update the pivot data.
     *
     * @param mixed $id
     * @param array $pivotData
     * @return void
     */
    private function linkOnce(mixed $id, array $pivotData): void
    {
        $info = $this->relationInfo;

        $linked = $this->getCurrentRelatedIds(
            $info['pivotTable'],
            $info['foreignKey'],
            $info['relatedKey'],
            $info['parentKey']
        );

        if (!in_array($id, $linked)) {
            $this->link([$id], $pivotData);

            return;
        }

        if ($pivotData !== []) {
            $this->updatePivot(
                $id,
                $pivotData,
                $info['pivotTable'],
                $info['foreignKey'],
                $info['relatedKey'],
                $info['parentKey']
            );
        }
    }

    /**
     * Run a callback in a database transaction
     *
     * @param \Closure $callback
     * @return mixed
     */
    private function inTransaction(\Closure $callback): mixed
    {
        return (new Database($this->connectionName))->transaction($callback);
    }

    /**
     * Assert that the parent model is saved (has a primary key) before performing operations that require it.
     *
     * @return void
     * @throws \LogicException
     */
    private function assertParentIsSaved(): void
    {
        if (empty($this->relationInfo['parentKey'] ?? null)) {
            throw new \LogicException('Cannot write through a relation of an unsaved model: its key is empty.');
        }
    }
}
