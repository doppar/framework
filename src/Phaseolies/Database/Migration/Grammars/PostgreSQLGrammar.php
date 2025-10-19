<?php

namespace Phaseolies\Database\Migration\Grammars;

use Phaseolies\Database\Migration\ColumnDefinition;

class PostgreSQLGrammar extends Grammar
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
        $primaryKeyColumns = [];

        foreach ($columns as $column) {
            $columnSql = $column->toSql();

            if ($column->type === 'id' || $column->type === 'bigIncrements') {
                $columnSql = sprintf(
                    '"%s" BIGSERIAL NOT NULL',
                    $column->name
                );
                $primaryKeyColumns[] = trim($column->name, '"');
            } elseif (isset($column->attributes['primary']) && $column->attributes['primary']) {
                $primaryKeyColumns[] = trim($column->name, '"');
            }

            $columnDefinitions[] = $columnSql;
        }

        $primaryKeySql = '';
        if (!empty($primaryKeyColumns)) {
            $primaryKeySql = ', PRIMARY KEY ("' . implode('", "', $primaryKeyColumns) . '")';
        }

        return "CREATE TABLE \"{$table}\" (" . implode(', ', $columnDefinitions) . $primaryKeySql . ")";
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
        return "ALTER TABLE \"{$table}\" ADD COLUMN {$columnSql}";
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

        return "CREATE INDEX \"{$indexName}\" ON \"{$table}\" (\"{$column}\")";
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
        $constraintName = "{$table}_{$column}_unique";

        return "ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$constraintName}\" UNIQUE (\"{$column}\")";
    }

    /**
     * Determine if the current database supports column ordering
     *
     * @return bool
     */
    public function supportsColumnOrdering(): bool
    {
        return true;
    }

    /**
     * PostgreSQL supports adding a primary key
     *
     * @return bool
     */
    public function supportsAddingPrimaryKey(): bool
    {
        return true;
    }

    /**
     * Map abstract column types to PostgreSQL-specific SQL type definitions
     *
     * @param string $type
     * @param array $attributes
     * @return string
     */
    protected function mapType(string $type, array $attributes): string
    {
        // Handle special cases that require attributes first
        switch ($type) {
            case 'enum':
                // For PostgreSQL, enums are handled differently - use text with check constraint
                return $this->createEnumType($attributes);
            case 'set':
                // PostgreSQL doesn't have SET type, use array or text with check constraint
                return 'TEXT[]';
            case 'string':
                return 'VARCHAR(' . ($attributes['length'] ?? 255) . ')';
            case 'char':
                return 'CHAR(' . ($attributes['length'] ?? 255) . ')';
            case 'decimal':
                return 'DECIMAL(' . ($attributes['precision'] ?? 10) . ',' . ($attributes['scale'] ?? 2) . ')';
            case 'double':
                return 'DOUBLE PRECISION';
            case 'jsonb':
                return 'JSONB';
        }

        // Standard type mappings
        $map = [
            'id' => 'BIGSERIAL',
            'bigIncrements' => 'BIGSERIAL',
            'increments' => 'SERIAL',
            'integerIncrements' => 'SERIAL',
            'text' => 'TEXT',
            'mediumText' => 'TEXT',
            'longText' => 'TEXT',
            'tinyText' => 'TEXT',
            'boolean' => 'BOOLEAN',
            'json' => 'JSON',
            'integer' => 'INTEGER',
            'tinyInteger' => 'SMALLINT', // PostgreSQL doesn't have TINYINT
            'smallInteger' => 'SMALLINT',
            'mediumInteger' => 'INTEGER',
            'bigInteger' => 'BIGINT',
            'unsignedInteger' => 'INTEGER', // PostgreSQL doesn't have UNSIGNED, use check constraints
            'unsignedTinyInteger' => 'SMALLINT',
            'unsignedSmallInteger' => 'SMALLINT',
            'unsignedMediumInteger' => 'INTEGER',
            'unsignedBigInteger' => 'BIGINT',
            'float' => 'REAL',
            'date' => 'DATE',
            'dateTime' => 'TIMESTAMP',
            'dateTimeTz' => 'TIMESTAMPTZ',
            'time' => 'TIME',
            'timeTz' => 'TIMETZ',
            'timestamp' => 'TIMESTAMP',
            'timestampTz' => 'TIMESTAMPTZ',
            'year' => 'INTEGER',
            'binary' => 'BYTEA',
            'tinyBlob' => 'BYTEA',
            'mediumBlob' => 'BYTEA',
            'longBlob' => 'BYTEA',
            'geometry' => 'GEOMETRY',
            'point' => 'POINT',
            'lineString' => 'LINESTRING',
            'polygon' => 'POLYGON',
            'geometryCollection' => 'GEOMETRYCOLLECTION',
            'multiPoint' => 'MULTIPOINT',
            'multiLineString' => 'MULTILINESTRING',
            'multiPolygon' => 'MULTIPOLYGON',
            'uuid' => 'UUID',
            'ipAddress' => 'INET',
            'macAddress' => 'MACADDR',
        ];

        return $map[$type] ?? strtoupper($type);
    }

    /**
     * Create an ENUM type definition for PostgreSQL
     * For PostgreSQL, we have two options:
     * 1. Create a custom enum type (requires separate DDL statement)
     * 2. Use TEXT with a CHECK constraint (simpler approach)
     * 
     * This implementation uses the CHECK constraint approach for simplicity
     *
     * @param array $attributes
     * @return string
     */
    protected function createEnumType(array $attributes): string
    {
        $values = $attributes['allowed'] ?? $attributes['values'] ?? null;

        if (!$values || !is_array($values)) {
            throw new \InvalidArgumentException('Enum type requires an array of allowed values');
        }

        // For PostgreSQL, we'll use TEXT with a CHECK constraint
        // The actual CHECK constraint will be added separately
        return 'TEXT';
    }

    /**
     * Compile a CHECK constraint for an enum column
     *
     * @param string $table
     * @param string $column
     * @param array $values
     * @return string
     */
    public function compileAddEnumCheckConstraint(string $table, string $column, array $values): string
    {
        $constraintName = "chk_{$table}_{$column}_enum";
        $quotedValues = array_map(function ($value) {
            return "'" . str_replace("'", "''", $value) . "'";
        }, $values);

        return "ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$constraintName}\" CHECK (\"{$column}\" IN (" . implode(', ', $quotedValues) . "))";
    }

    /**
     * Compile a statement to create an enum type (alternative approach)
     *
     * @param string $typeName
     * @param array $values
     * @return string
     */
    public function compileCreateEnumType(string $typeName, array $values): string
    {
        $quotedValues = array_map(function ($value) {
            return "'" . str_replace("'", "''", $value) . "'";
        }, $values);

        return "CREATE TYPE {$typeName} AS ENUM (" . implode(', ', $quotedValues) . ")";
    }

    /**
     * Compile a statement to drop an enum type
     *
     * @param string $typeName
     * @return string
     */
    public function compileDropEnumType(string $typeName): string
    {
        return "DROP TYPE IF EXISTS {$typeName}";
    }

    /**
     * Compile a statement to create a schema
     *
     * @param string $schema
     * @return string
     */
    public function compileCreateSchema(string $schema): string
    {
        return "CREATE SCHEMA IF NOT EXISTS \"{$schema}\"";
    }

    /**
     * Compile a statement to drop a schema
     *
     * @param string $schema
     * @return string
     */
    public function compileDropSchema(string $schema): string
    {
        return "DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE";
    }

    /**
     * Compile a statement to set the search path
     *
     * @param string $schema
     * @return string
     */
    public function compileSetSearchPath(string $schema): string
    {
        return "SET search_path TO \"{$schema}\"";
    }
}
