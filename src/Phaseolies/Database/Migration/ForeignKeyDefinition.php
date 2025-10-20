<?php

namespace Phaseolies\Database\Migration;

class ForeignKeyDefinition
{
    /** @var Blueprint The table blueprint that contains this foreign key */
    protected Blueprint $blueprint;

    /** @var string The column name that will be the foreign key */
    protected string $column;

    /** @var string The referenced table name */
    protected string $on;

    /** @var string The referenced column name (default: 'id') */
    protected string $references = 'id';

    /** @var string|null The ON DELETE action (e.g., CASCADE, SET NULL) */
    protected ?string $onDelete = null;

    /** @var string|null The ON UPDATE action (e.g., CASCADE, SET NULL) */
    protected ?string $onUpdate = null;

    /**
     * Create a new foreign key definition instance.
     *
     * @param Blueprint $blueprint
     * @param string $column
     */
    public function __construct(Blueprint $blueprint, string $column)
    {
        $this->blueprint = $blueprint;
        $this->column = $column;
    }

    /**
     * Set the referenced column name in the foreign table.
     *
     * @param string $column
     * @return self
     */
    public function references(string $column): self
    {
        $this->references = $column;

        return $this;
    }

    /**
     * Set the referenced table name.
     *
     * @param string $table
     * @return self
     */
    public function on(string $table): self
    {
        $this->on = $table;

        return $this;
    }

    /**
     * Set the ON DELETE action for the foreign key.
     *
     * @param string $action
     * @return self
     */
    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);

        return $this;
    }

    /**
     * Set the ON UPDATE action for the foreign key.
     *
     * @param string $action
     * @return self
     */
    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);

        return $this;
    }

    /**
     * Set ON DELETE to CASCADE (delete referencing rows when referenced row is deleted).
     *
     * @return self
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    /**
     * Set ON DELETE to RESTRICT (prevent deletion of referenced row).
     *
     * @return self
     */
    public function restrictOnDelete(): self
    {
        return $this->onDelete('RESTRICT');
    }

    /**
     * Set ON DELETE to SET NULL (set foreign key to NULL when referenced row is deleted).
     *
     * @return self
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete('SET NULL');
    }

    /**
     * Set ON UPDATE to CASCADE (update referencing rows when referenced row is updated).
     *
     * @return self
     */
    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('CASCADE');
    }

    /**
     * Set ON UPDATE to RESTRICT (prevent update of referenced row).
     *
     * @return self
     */
    public function restrictOnUpdate(): self
    {
        return $this->onUpdate('RESTRICT');
    }

    /**
     * Set ON UPDATE to SET NULL (set foreign key to NULL when referenced row is updated).
     *
     * @return self
     */
    public function nullOnUpdate(): self
    {
        return $this->onUpdate('SET NULL');
    }

    /**
     * Convert the foreign key definition to its SQL representation.
     *
     * @return string
     * @throws \RuntimeException
     */
    public function toSql(): string
    {
        if (!$this->references || !$this->on) {
            throw new \RuntimeException('Foreign key constraint is incomplete. Missing references or on table.');
        }

        // Generate constraint name
        $constraintName = $this->getConstraintName();

        // Build the base ALTER TABLE statement
        $sql = "ALTER TABLE {$this->blueprint->table} ADD CONSTRAINT {$constraintName} ";
        $sql .= "FOREIGN KEY ({$this->column}) REFERENCES {$this->on} ({$this->references})";

        // Add ON DELETE clause if specified
        if ($this->onDelete) {
            $sql .= " ON DELETE {$this->onDelete}";
        }

        // Add ON UPDATE clause if specified
        if ($this->onUpdate) {
            $sql .= " ON UPDATE {$this->onUpdate}";
        }

        return $sql;
    }

    /**
     * Generate a standard name for the foreign key constraint.
     *
     * @return string
     */
    protected function getConstraintName(): string
    {
        return "fk_{$this->blueprint->table}_{$this->column}";
    }
}
