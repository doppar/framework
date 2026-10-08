<?php

namespace Phaseolies\Database\Entity\Query;

use Phaseolies\Database\Entity\SoftDeletes\SoftDeleteManager;

trait InteractsWithSoftDeleteScope
{
    /**
     * Which rows a soft-deletable query returns: 'exclude' hides trashed
     * rows, 'include' returns everything, 'only' returns trashed rows only.
     *
     * @var string
     */
    protected string $trashed = 'exclude';

    /**
     * When true, delete() removes rows even on a soft-deletable model.
     *
     * @var bool
     */
    protected bool $forceDeleting = false;

    /**
     * Include soft-deleted rows in the results.
     *
     * @return self
     * @throws \LogicException
     */
    public function withTrashed(): self
    {
        $this->assertSoftDeletable(__FUNCTION__);

        $this->trashed = 'include';

        return $this;
    }

    /**
     * Return only soft-deleted rows.
     *
     * @return self
     * @throws \LogicException
     */
    public function onlyTrashed(): self
    {
        $this->assertSoftDeletable(__FUNCTION__);

        $this->trashed = 'only';

        return $this;
    }

    /**
     * Exclude soft-deleted rows from the results (the default).
     *
     * @return self
     * @throws \LogicException
     */
    public function withoutTrashed(): self
    {
        $this->assertSoftDeletable(__FUNCTION__);

        $this->trashed = 'exclude';

        return $this;
    }

    /**
     * Turn the soft delete constraint off for this query.
     *
     * Used internally for queries that target a single row by its key,
     * such as saving or deleting a model that may already be trashed.
     *
     * @internal
     * @return self
     */
    public function withoutSoftDeleteScope(): self
    {
        $this->trashed = 'include';

        return $this;
    }

    /**
     * Restore the soft-deleted rows matched by the query.
     *
     * @return bool
     * @throws \LogicException
     */
    public function restore(): bool
    {
        $this->assertSoftDeletable(__FUNCTION__);

        if ($this->trashed === 'exclude') {
            $this->trashed = 'only';
        }

        return $this->writeSoftDeleteState(null, (string) now());
    }

    /**
     * Write the soft delete column on the matched rows, touching
     * updated_at with the given timestamp when the model uses timestamps.
     *
     * Taking the timestamp from the caller lets a model mirror the exact
     * values that were written.
     *
     * @internal
     * @param string|null $deletedAt
     * @param string $timestamp
     * @return bool
     */
    public function writeSoftDeleteState(?string $deletedAt, string $timestamp): bool
    {
        $attributes = [$this->getSoftDeleteColumn() => $deletedAt];

        if ($this->getModel()->usesTimestamps()) {
            $attributes['updated_at'] = $timestamp;
        }

        return $this->performUpdate($attributes);
    }

    /**
     * Permanently delete the rows matched by the query.
     *
     * @return bool
     * @throws \LogicException
     */
    public function forceDelete(): bool
    {
        $this->assertSoftDeletable(__FUNCTION__);

        $this->forceDeleting = true;

        try {
            return $this->delete();
        } finally {
            $this->forceDeleting = false;
        }
    }

    /**
     * Determine whether delete() on this query should stamp rows
     * instead of removing them.
     *
     * @return bool
     */
    protected function shouldSoftDelete(): bool
    {
        return !$this->forceDeleting && $this->getSoftDeleteColumn() !== null;
    }

    /**
     * Get the soft delete column of the query's model, if it has one.
     *
     * @return string|null
     */
    public function getSoftDeleteColumn(): ?string
    {
        return SoftDeleteManager::column($this->modelClass);
    }

    /**
     * Build the soft delete constraint for this query.
     *
     * With no table the column is qualified exactly as the query's own
     * FROM clause names its table (honouring an alias), so the reference
     * resolves on every driver. A table passed in comes from a hand-built
     * subquery that quotes its identifiers, so it is quoted to match.
     *
     * @param string|null $table
     * @return string|null
     */
    protected function compileSoftDeleteConstraint(?string $table = null): ?string
    {
        $column = $this->getSoftDeleteColumn();

        if ($column === null || $this->trashed === 'include') {
            return null;
        }

        $qualified = $table === null
            ? $this->tableReference() . '.' . $column
            : $this->quoteIdentifier("{$table}.{$column}");

        return $this->trashed === 'only'
            ? "{$qualified} IS NOT NULL"
            : "{$qualified} IS NULL";
    }

    /**
     * Get the name the query's FROM clause refers to its table by:
     * the alias for "table as alias" or "table alias", else the table.
     *
     * @return string
     */
    protected function tableReference(): string
    {
        $table = trim($this->table);

        if (preg_match('/^\S+\s+(?:as\s+)?(\S+)$/i', $table, $matches)) {
            return $matches[1];
        }

        return $table;
    }

    /**
     * Build the default soft delete constraint for a related model used
     * inside a hand-written subquery, as an " AND ..." fragment.
     *
     * @param string $modelClass
     * @param string $table
     * @return string
     */
    protected function relatedSoftDeleteClause(string $modelClass, string $table): string
    {
        $column = SoftDeleteManager::column($modelClass);

        if ($column === null) {
            return '';
        }

        return ' AND ' . $this->quoteIdentifier("{$table}.{$column}") . ' IS NULL';
    }

    /**
     * Build the WHERE clause, appending the soft delete constraint to
     * the user's conditions.
     *
     * The user's conditions are wrapped in parentheses so that an OR
     * among them cannot bypass the constraint.
     *
     * @return array [sql, bindings]
     */
    protected function buildWhereClause(): array
    {
        [$sql, $bindings] = $this->buildConditionWhereClause();

        $constraint = $this->compileSoftDeleteConstraint();

        if ($constraint === null) {
            return [$sql, $bindings];
        }

        return [$sql === '' ? $constraint : "({$sql}) AND {$constraint}", $bindings];
    }

    /**
     * @param string $method
     * @return void
     * @throws \LogicException
     */
    protected function assertSoftDeletable(string $method): void
    {
        if ($this->getSoftDeleteColumn() === null) {
            throw new \LogicException(
                "Cannot call {$method}() on {$this->modelClass}: the model is not marked #[SoftDeletes]."
            );
        }
    }
}
