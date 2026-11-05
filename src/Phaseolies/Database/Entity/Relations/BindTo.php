<?php

namespace Phaseolies\Database\Entity\Relations;

use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Entity\Model;

class BindTo extends Builder
{
    /**
     * @var Model|null
     */
    protected ?Model $child = null;

    /**
     * @var string
     */
    protected string $childKey = '';

    /**
     * @var string
     */
    protected string $ownerKey = '';

    /**
     * Remember the model that owns the foreign key, and the columns that tie it to its parent
     *
     * @param Model $child
     * @param string $childKey
     * @param string $ownerKey
     * @return static
     */
    public function setRelationChild(Model $child, string $childKey, string $ownerKey): static
    {
        $this->child = $child;
        $this->childKey = $childKey;
        $this->ownerKey = $ownerKey;

        return $this;
    }

    /**
     * Point the child at a parent. The child is not saved: call save() on it.
     *
     * @param Model|int|string $parent
     * @return Model The child
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function associate(Model|int|string $parent): Model
    {
        $expected = $this->modelClass;

        if ($parent instanceof Model && !($parent instanceof $expected)) {
            throw new \InvalidArgumentException(
                'Expected an instance of ' . $expected . ', got ' . $parent::class . '.'
            );
        }

        $child = $this->childModel();

        $value = $parent instanceof Model ? $parent->{$this->ownerKey} : $parent;

        if ($value === null) {
            throw new \LogicException('Cannot associate an unsaved ' . get_debug_type($parent) . ': its [' . $this->ownerKey . '] is empty.');
        }

        $child->setAttribute($this->childKey, $value);

        return $child;
    }

    /**
     * Remove the link to the parent. The child is not saved: call save() on it.
     *
     * @return Model The child
     * @throws \LogicException
     */
    public function dissociate(): Model
    {
        $child = $this->childModel();

        $child->setAttribute($this->childKey, null);

        return $child;
    }

    /**
     * @return Model
     * @throws \LogicException
     */
    private function childModel(): Model
    {
        if ($this->child === null) {
            throw new \LogicException('This relation was not created from a model.');
        }

        if (!$this->child->isCreatable($this->childKey)) {
            throw new \LogicException(
                'Add [' . $this->childKey . '] to ' . $this->child::class . '::$creatable so the change can be saved.'
            );
        }

        return $this->child;
    }
}
