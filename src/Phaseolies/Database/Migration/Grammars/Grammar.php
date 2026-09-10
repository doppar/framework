<?php

namespace Phaseolies\Database\Migration\Grammars;

use Phaseolies\Database\Migration\ColumnDefinition;

abstract class Grammar
{
    /**
     * Get the SQL type definition for a column.
     *
     * @param ColumnDefinition $column
     * @return string
     */
    abstract public function getTypeDefinition(ColumnDefinition $column): string;

    /**
     * Get the SQL for creating a table.
     *
     * @param string $table
     * @param array $columns
     * @param array $primaryKeys
     * @return string
     */
    abstract public function compileCreateTable(string $table, array $columns, array $primaryKeys = []): string;

    /**
     * Get the SQL for adding a column.
     *
     * @param string $table
     * @param string $columnSql
     * @return string
     */
    abstract public function compileAddColumn(string $table, string $columnSql): string;

    /**
     * Get the SQL for creating an index.
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    abstract public function compileCreateIndex(string $table, string $column): string;

    /**
     * Get the SQL for creating a unique constraint.
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    abstract public function compileCreateUnique(string $table, string $column): string;

    /**
     * Check if the grammar supports adding columns with AFTER.
     *
     * @return bool
     */
    public function supportsColumnOrdering(): bool
    {
        return false;
    }

    /**
     * Check if the grammar supports adding primary key with ALTER TABLE.
     *
     * @return bool
     */
    public function supportsAddingPrimaryKey(): bool
    {
        return false;
    }

    /**
     * Check if UNIQUE constraint should be added in column definition.
     *
     * @return bool
     */
    public function shouldAddUniqueInColumnDefinition(): bool
    {
        return false;
    }

    /**
     * Extract and validate the allowed values for an enum/set column.
     *
     * @param array $attributes
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function getEnumAllowedValues(array $attributes): array
    {
        $values = $attributes['allowed'] ?? $attributes['values'] ?? null;

        if (!$values || !is_array($values)) {
            throw new \InvalidArgumentException('Enum type requires an array of allowed values');
        }

        return $values;
    }

    /**
     * Build an inline CHECK constraint clause restricting a column to
     * a fixed set of values, for drivers with no native ENUM type.
     * Appended directly to the column's type definition, so it applies
     * the same way in both CREATE TABLE and ALTER TABLE ADD COLUMN.
     *
     * @param string $column
     * @param array $values
     * @return string
     */
    protected function compileEnumCheckClause(string $column, array $values): string
    {
        $quoted = array_map(
            fn($value) => "'" . str_replace("'", "''", (string) $value) . "'",
            $values
        );

        return "CHECK ({$column} IN (" . implode(', ', $quoted) . '))';
    }
}
