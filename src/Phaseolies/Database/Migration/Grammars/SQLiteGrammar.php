<?php

namespace Phaseolies\Database\Migration\Grammars;

use Phaseolies\Database\Migration\ColumnDefinition;

class SQLiteGrammar extends Grammar
{
    /**
     * Get the SQL data type definition for a given column
     *
     * @param ColumnDefinition $column
     * @return string
     */
    public function getTypeDefinition(ColumnDefinition $column): string
    {
        return $this->mapType($column->type, $column->attributes);
    }

    /**
     * Compile a SQL CREATE TABLE statement
     *
     * @param string $table
     * @param array $columns
     * @param array $primaryKeys
     * @return string
     */
    public function compileCreateTable(string $table, array $columns, array $primaryKeys = []): string
    {
        $columnDefinitions = [];
        $hasAutoIncrementId = false;
        $tablePrimaryKeys = [];

        // Process all columns
        foreach ($columns as $column) {
            $originalSql = $column->toSql();

            // For SQLite, we need to modify the SQL to handle primary keys correctly
            $columnSql = $originalSql;

            // Check if this is a primary key column
            $isPrimaryKey = !empty($column->attributes['primary']) ||
                           in_array($column->name, $primaryKeys) ||
                           $column->type === 'id' ||
                           $column->type === 'bigIncrements';

            if ($isPrimaryKey) {
                if (($column->type === 'id' || $column->type === 'bigIncrements') &&
                    strpos($originalSql, 'PRIMARY KEY') === false) {
                    error_log("[DEBUG] Found auto-incrementing primary key: {$column->name}");
                    // For auto-incrementing IDs, add PRIMARY KEY directly to the column
                    $columnSql = str_replace('INTEGER', 'INTEGER PRIMARY KEY AUTOINCREMENT', $originalSql);
                    $hasAutoIncrementId = true;
                } elseif (!empty($column->attributes['primary']) &&
                          $column->type !== 'id' &&
                          $column->type !== 'bigIncrements') {
                    error_log("[DEBUG] Found non-auto-incrementing primary key: {$column->name}");
                    // For other primary keys, collect them for a separate PRIMARY KEY clause
                    $tablePrimaryKeys[] = $column->name;
                    // Remove any PRIMARY KEY from the column definition
                    $columnSql = preg_replace('/\s+PRIMARY\s+KEY/i', '', $originalSql);
                }
            }

            $columnDefinitions[] = $columnSql;
        }

        // Add composite primary key if we have multiple primary keys and no auto-incrementing ID
        $primaryKeySql = '';

        // Only add a separate PRIMARY KEY clause if:
        // 1. We have multiple primary keys, or
        // 2. We have a single non-auto-incrementing primary key that's not already defined in the column
        $hasExplicitPrimaryKey = false;
        foreach ($columnDefinitions as $def) {
            if (stripos($def, 'PRIMARY KEY') !== false) {
                $hasExplicitPrimaryKey = true;
                break;
            }
        }

        if ((!empty($tablePrimaryKeys) || count($primaryKeys) > 0) && !$hasAutoIncrementId && !$hasExplicitPrimaryKey) {
            $keys = !empty($tablePrimaryKeys) ? $tablePrimaryKeys : $primaryKeys;
            if (!empty($keys)) {
                $primaryKeySql = ', PRIMARY KEY (`' . implode('`, `', $keys) . '`)';
            }
        }

        return "CREATE TABLE `{$table}` (" . implode(', ', $columnDefinitions) . $primaryKeySql . ")";
    }

    /**
     * Compile a SQL statement to add a new column to an existing table
     *
     * @param string $table
     * @param string $columnSql
     * @return string
     */
    public function compileAddColumn(string $table, string $columnSql): string
    {
        return "ALTER TABLE `{$table}` ADD COLUMN {$columnSql}";
    }

    /**
     * Compile a SQL statement to create a non-unique index on a column
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    public function compileCreateIndex(string $table, string $column): string
    {
        $indexName = "idx_{$table}_{$column}";

        return "CREATE INDEX `{$indexName}` ON `{$table}` (`{$column}`)";
    }

    /**
     * Compile a SQL statement to add a unique constraint on a column
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    public function compileCreateUnique(string $table, string $column): string
    {
        // SQLite does not support adding constraints UNIQUE with ALTER TABLE
        return '';
    }

    /**
     * SQLite does not support adding primary key with ALTER TABLE.
     *
     * @return bool
     */
    public function supportsAddingPrimaryKey(): bool
    {
        return false;
    }

    /**
     * SQLite requires UNIQUE constraint in column definition.
     *
     * @return bool
     */
    public function shouldAddUniqueInColumnDefinition(): bool
    {
        return true;
    }

    /**
     * Map abstract column types to MySQL-specific SQL type definitions
     *
     * @param string $type
     * @param array $attributes
     * @return string
     */
    protected function mapType(string $type, array $attributes): string
    {
        $map = [
            'id' => 'INTEGER',
            'bigIncrements' => 'INTEGER',
            'string' => 'TEXT',
            'char' => 'TEXT',
            'text' => 'TEXT',
            'mediumText' => 'TEXT',
            'longText' => 'TEXT',
            'tinyText' => 'TEXT',
            'boolean' => 'INTEGER',
            'json' => 'TEXT',
            'jsonb' => 'TEXT',
            'integer' => 'INTEGER',
            'tinyInteger' => 'INTEGER',
            'smallInteger' => 'INTEGER',
            'mediumInteger' => 'INTEGER',
            'bigInteger' => 'INTEGER',
            'unsignedInteger' => 'INTEGER',
            'unsignedTinyInteger' => 'INTEGER',
            'unsignedSmallInteger' => 'INTEGER',
            'unsignedMediumInteger' => 'INTEGER',
            'unsignedBigInteger' => 'INTEGER',
            'float' => 'REAL',
            'double' => 'REAL',
            'decimal' => 'REAL',
            'date' => 'TEXT',
            'dateTime' => 'TEXT',
            'dateTimeTz' => 'TEXT',
            'time' => 'TEXT',
            'timeTz' => 'TEXT',
            'timestamp' => 'INTEGER',
            'timestampTz' => 'INTEGER',
            'year' => 'INTEGER',
            'binary' => 'BLOB',
            'tinyBlob' => 'BLOB',
            'mediumBlob' => 'BLOB',
            'longBlob' => 'BLOB',
            'enum' => 'TEXT',
            'set' => 'TEXT',
            'geometry' => 'BLOB',
            'point' => 'BLOB',
            'lineString' => 'BLOB',
            'polygon' => 'BLOB',
            'geometryCollection' => 'BLOB',
            'multiPoint' => 'BLOB',
            'multiLineString' => 'BLOB',
            'multiPolygon' => 'BLOB',
            'uuid' => 'TEXT',
            'ipAddress' => 'TEXT',
            'macAddress' => 'TEXT',
        ];

        return $map[$type] ?? strtoupper($type);
    }
}
