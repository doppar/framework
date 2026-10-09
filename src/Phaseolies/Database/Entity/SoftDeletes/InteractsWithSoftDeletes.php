<?php

namespace Phaseolies\Database\Entity\SoftDeletes;

trait InteractsWithSoftDeletes
{
    /**
     * True while forceDelete() is removing the model, so delete hooks
     * can tell a permanent delete from a soft one.
     *
     * @var bool
     */
    protected bool $forceDeleting = false;

    /**
     * Determine whether the model is marked #[SoftDeletes].
     *
     * @return bool
     */
    public function usesSoftDeletes(): bool
    {
        return SoftDeleteManager::isSoftDeletable(static::class);
    }

    /**
     * Get the soft delete column, or null when the model is not soft-deletable.
     *
     * @return string|null
     */
    public function getSoftDeleteColumn(): ?string
    {
        return SoftDeleteManager::column(static::class);
    }

    /**
     * Determine whether the model has been soft-deleted.
     *
     * @return bool
     */
    public function trashed(): bool
    {
        $column = $this->getSoftDeleteColumn();

        return $column !== null && ($this->attributes[$column] ?? null) !== null;
    }

    /**
     * Determine whether the model is currently being force deleted.
     *
     * @return bool
     */
    public function isForceDeleting(): bool
    {
        return $this->forceDeleting;
    }

    /**
     * Permanently delete the model, bypassing soft deletes.
     *
     * Fires the before_deleted and after_deleted hooks; isForceDeleting()
     * returns true while they run.
     *
     * @return bool
     * @throws \LogicException
     */
    public function forceDelete(): bool
    {
        $this->assertUsesSoftDeletes(__FUNCTION__);

        $this->forceDeleting = true;

        try {
            return $this->delete();
        } finally {
            $this->forceDeleting = false;
        }
    }

    /**
     * Restore a soft-deleted model.
     *
     * Fires the before_restored and after_restored hooks.
     *
     * @return bool
     * @throws \LogicException
     */
    public function restore(): bool
    {
        $this->assertUsesSoftDeletes(__FUNCTION__);

        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }

        try {
            if (self::$isHookShouldBeCalled && $this->fireBeforeHooks('restored') === false) {
                return false;
            }

            $now = (string) now();

            $result = $this->newQuery()
                ->withoutSoftDeleteScope()
                ->where($this->primaryKey, $this->attributes[$this->primaryKey])
                ->writeSoftDeleteState(null, $now);

            if ($result) {
                $this->syncSoftDeleteAttributes(null, $now);

                if (self::$isHookShouldBeCalled) {
                    $this->fireAfterHooks('restored');
                }
            }

            return $result;
        } finally {
            self::$isHookShouldBeCalled = true;
        }
    }

    /**
     * Stamp the soft delete column on the model's row.
     *
     * @return bool
     */
    protected function performSoftDelete(): bool
    {
        $now = (string) now();

        $result = $this->newQuery()
            ->withoutSoftDeleteScope()
            ->where($this->primaryKey, $this->attributes[$this->primaryKey])
            ->writeSoftDeleteState($now, $now);

        if ($result) {
            $this->syncSoftDeleteAttributes($now, $now);
        }

        return $result;
    }

    /**
     * Mirror the values a soft delete or restore wrote onto the in-memory
     * model, so it matches the row without a reload.
     *
     * @param string|null $deletedAt
     * @param string $timestamp
     * @return void
     */
    private function syncSoftDeleteAttributes(?string $deletedAt, string $timestamp): void
    {
        $attributes = [$this->getSoftDeleteColumn() => $deletedAt];

        if ($this->usesTimestamps()) {
            $attributes['updated_at'] = $timestamp;
        }

        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
            $this->originalAttributes[$key] = $value;
        }
    }

    /**
     * @param string $method
     * @return void
     * @throws \LogicException
     */
    private function assertUsesSoftDeletes(string $method): void
    {
        if (!$this->usesSoftDeletes()) {
            throw new \LogicException(
                "Cannot call {$method}() on " . static::class . ': the model is not marked #[SoftDeletes].'
            );
        }
    }
}
