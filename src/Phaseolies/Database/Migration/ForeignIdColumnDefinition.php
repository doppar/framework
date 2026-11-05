<?php

namespace Phaseolies\Database\Migration;

class ForeignIdColumnDefinition extends ColumnDefinition
{
    /** @var Blueprint The blueprint the column belongs to */
    protected Blueprint $blueprint;

    /**
     * Create a new foreign id column definition.
     *
     * @param Blueprint $blueprint
     * @param array $attributes
     */
    public function __construct(Blueprint $blueprint, array $attributes = [])
    {
        parent::__construct($attributes);

        $this->blueprint = $blueprint;
    }

    /**
     * Add a foreign key constraint referencing the given table.
     * Without arguments the table is guessed from the column name
     * (user_id => users, category_id => categories).
     *
     * @param string|null $table
     * @param string $column
     * @param string|null $indexName
     * @return ForeignKeyDefinition
     */
    public function constrained(?string $table = null, string $column = 'id', ?string $indexName = null): ForeignKeyDefinition
    {
        $foreign = $this->references($column)->on($table ?? $this->guessTable());

        return $indexName ? $foreign->name($indexName) : $foreign;
    }

    /**
     * Start a foreign key constraint on this column.
     *
     * @param string $column
     * @return ForeignKeyDefinition
     */
    public function references(string $column): ForeignKeyDefinition
    {
        return $this->blueprint->foreign($this->name)->references($column);
    }

    /**
     * Guess the referenced table name from the column name.
     *
     * @return string
     */
    protected function guessTable(): string
    {
        $base = preg_replace('/_(id|uuid|ulid)$/', '', $this->name);

        return match (true) {
            preg_match('/[^aeiou]y$/', $base) === 1 => substr($base, 0, -1) . 'ies',
            preg_match('/(s|x|z|ch|sh)$/', $base) === 1 => $base . 'es',
            default => $base . 's',
        };
    }
}
