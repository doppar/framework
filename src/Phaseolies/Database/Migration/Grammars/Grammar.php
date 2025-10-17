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
}
