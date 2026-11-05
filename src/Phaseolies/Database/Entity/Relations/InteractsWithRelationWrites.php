<?php

namespace Phaseolies\Database\Entity\Relations;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Support\Collection;

trait InteractsWithRelationWrites
{
    /**
     * @var Model|null
     */
    protected ?Model $parent = null;

    /**
     * @var string
     */
    protected string $parentForeignKey = '';

    /**
     * @var string
     */
    protected string $parentLocalKey = '';

    /**
     * Remember the parent model and the keys that tie the records together
     *
     * @param Model $parent
     * @param string $foreignKey
     * @param string $localKey
     * @return static
     */
    public function setRelationParent(Model $parent, string $foreignKey, string $localKey): static
    {
        $this->parent = $parent;
        $this->parentForeignKey = $foreignKey;
        $this->parentLocalKey = $localKey;

        return $this;
    }

    /**
     * Create a related record with the foreign key already set
     *
     * @param array $attributes
     * @return Model
     * @throws \LogicException
     */
    public function create(array $attributes): Model
    {
        $this->assertCanWrite();

        return parent::create(array_merge($attributes, [
            $this->parentForeignKey => $this->parentKeyValue(),
        ]));
    }

    /**
     * Create several related records. Each one is a separate insert
     *
     * @param array<int, array> $records
     * @return Collection
     * @throws \LogicException
     */
    public function createMany(array $records): Collection
    {
        $this->assertCanWrite();

        $models = [];

        foreach ($records as $attributes) {
            $models[] = $this->create($attributes);
        }

        return new Collection($this->modelClass, $models);
    }

    /**
     * Save an existing or new model as a related record
     *
     * @param Model $related
     * @return Model|false
     * @throws \LogicException
     */
    public function store(Model $related): Model|false
    {
        $this->assertCanWrite();

        $expected = $this->modelClass;

        if (!($related instanceof $expected)) {
            throw new \InvalidArgumentException(
                'Expected an instance of ' . $expected . ', got ' . $related::class . '.'
            );
        }

        $related->setAttribute($this->parentForeignKey, $this->parentKeyValue());

        if ($this->withoutHooks) {
            $related->withoutHook();
        }

        return $related->save() ? $related : false;
    }

    /**
     * Save several models as related records
     *
     * @param iterable<Model> $models
     * @return array<int, Model|false>
     * @throws \LogicException
     */
    public function storeMany(iterable $models): array
    {
        $saved = [];

        foreach ($models as $model) {
            $saved[] = $this->store($model);
        }

        return $saved;
    }

    /**
     * The parent's value of the local key
     *
     * @return mixed
     * @throws \LogicException
     */
    protected function parentKeyValue(): mixed
    {
        $value = $this->parent?->{$this->parentLocalKey};

        if ($value === null) {
            throw new \LogicException(
                'Cannot write through a relation of an unsaved ' . ($this->parent ? $this->parent::class : 'model')
                    . ': its [' . $this->parentLocalKey . '] is empty.'
            );
        }

        return $value;
    }

    /**
     * @return void
     * @throws \LogicException
     */
    protected function assertCanWrite(): void
    {
        if ($this->parent === null) {
            throw new \LogicException('This relation was not created from a model, so it cannot write.');
        }

        if (!app($this->modelClass)->isCreatable($this->parentForeignKey)) {
            throw new \LogicException(
                'Add [' . $this->parentForeignKey . '] to ' . $this->modelClass
                    . '::$creatable to create records through this relation.'
            );
        }
    }
}
