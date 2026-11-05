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
        $type = $this->mapType($column->type, $column->attributes);

        if ($column->type === 'enum') {
            $values = $this->getEnumAllowedValues($column->attributes);
            $type .= ' ' . $this->compileEnumCheckClause($column->name, $values);
        }

        return $type . $this->compileTypeSuffix($column);
    }

    /**
     * Collation names are case sensitive in PostgreSQL and must be quoted.
     *
     * @param string $collation
     * @return string
     */
    protected function formatCollation(string $collation): string
    {
        return $this->quoteIdentifier($this->safeKeyword($collation));
    }

    /**
     * Get the SERIAL type backing an incrementing column.
     *
     * @param string $type
     * @return string
     */
    protected function serialType(string $type): string
    {
        return match ($type) {
            'tinyIncrements', 'smallIncrements' => 'SMALLSERIAL',
            'increments', 'integerIncrements', 'mediumIncrements' => 'SERIAL',
            default => 'BIGSERIAL',
        };
    }

    public function compileColumnComment(string $table, ColumnDefinition $column): array
    {
        if (!isset($column->attributes['comment'])) {
            return [];
        }

        return ["COMMENT ON COLUMN {$this->quoteIdentifier($table)}.{$this->quoteIdentifier($column->name)} IS "
            . $this->quoteString($column->attributes['comment'])];
    }

    public function compileTableOptionStatements(string $table): array
    {
        if (!isset($this->tableOptions['comment'])) {
            return [];
        }

        return ["COMMENT ON TABLE {$this->quoteIdentifier($table)} IS " . $this->quoteString($this->tableOptions['comment'])];
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

            if ($this->isIncrementing($column->type)) {
                $columnSql = sprintf(
                    '"%s" %s NOT NULL',
                    $column->name,
                    $this->serialType($column->type)
                );
                $primaryKeyColumns[] = trim($column->name, '"');
            } elseif ($column->type === 'uuid' && !empty($column->attributes['primary'])) {
                // UUID primary key with auto-generation
                $columnSql = sprintf(
                    '"%s" UUID NOT NULL DEFAULT gen_random_uuid()',
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

        return $this->createTableKeyword() . " \"{$table}\" (" . implode(', ', $columnDefinitions) . $primaryKeySql . ')';
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
     * @param string|null $name
     * @return string
     */
    public function compileCreateIndex(string $table, string $column, ?string $name = null): string
    {
        return $this->compileCreateIndexSql($table, $name ?? $this->indexName($table, [$column]), [$column]);
    }

    /**
     * Compile a SQL statement to add a unique constraint on a column
     *
     * @param string $table
     * @param string $column
     * @param string|null $name
     * @return string
     */
    public function compileCreateUnique(string $table, string $column, ?string $name = null): string
    {
        return $this->compileCreateUniqueSql($table, $name ?? $this->indexName($table, [$column], 'unique'), [$column]);
    }

    protected function compileFullTextIndex(string $table, string $name, array $columns): string
    {
        $vector = implode(" || ' ' || ", array_map(
            fn($column) => "coalesce({$this->quoteIdentifier($column)}, '')",
            $columns
        ));

        return "CREATE INDEX {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)} "
            . "USING GIN (to_tsvector('english', {$vector}))";
    }

    protected function compileSpatialIndex(string $table, string $name, array $columns): string
    {
        return "CREATE INDEX {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)} "
            . "USING GIST ({$this->quoteColumns($columns)})";
    }

    protected function compileDropPrimary(string $table): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} DROP CONSTRAINT {$this->quoteIdentifier($table . '_pkey')}";
    }

    public function compileRenameIndex(string $table, string $from, string $to): string
    {
        return "ALTER INDEX {$this->quoteIdentifier($from)} RENAME TO {$this->quoteIdentifier($to)}";
    }

    public function compileChangeColumn(string $table, ColumnDefinition $column): array
    {
        $t = $this->quoteIdentifier($table);
        $c = $this->quoteIdentifier($column->name);
        $type = $this->mapType($column->type, $column->attributes);

        if ($this->isIncrementing($column->type)) {
            $type = match ($column->type) {
                'tinyIncrements', 'smallIncrements' => 'SMALLINT',
                'increments', 'integerIncrements', 'mediumIncrements' => 'INTEGER',
                default => 'BIGINT',
            };
        } elseif (!empty($column->attributes['autoIncrement'])) {
            $type = $this->mapType($column->type, array_diff_key($column->attributes, ['autoIncrement' => 1]));
        }

        $statements = ["ALTER TABLE {$t} ALTER COLUMN {$c} TYPE {$type} USING {$c}::{$type}"];

        $statements[] = "ALTER TABLE {$t} ALTER COLUMN {$c} "
            . (!empty($column->attributes['nullable']) ? 'DROP NOT NULL' : 'SET NOT NULL');

        $default = $column->getDefaultSql();

        $statements[] = "ALTER TABLE {$t} ALTER COLUMN {$c} "
            . ($default !== null ? "SET DEFAULT {$default}" : 'DROP DEFAULT');

        return array_merge($statements, $this->compileColumnComment($table, $column));
    }

    public function compileGetColumns(): string
    {
        return 'SELECT column_name FROM information_schema.columns '
            . 'WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position';
    }

    public function compileGetIndexes(): string
    {
        return 'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ?';
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
            case 'timestamp':
                return 'TIMESTAMP' . $this->getTimePrecision($attributes);
            case 'timestampTz':
                return 'TIMESTAMPTZ' . $this->getTimePrecision($attributes);
            case 'dateTime':
                return 'TIMESTAMP' . $this->getTimePrecision($attributes);
            case 'dateTimeTz':
                return 'TIMESTAMPTZ' . $this->getTimePrecision($attributes);
            case 'time':
                return 'TIME' . $this->getTimePrecision($attributes);
            case 'timeTz':
                return 'TIMETZ' . $this->getTimePrecision($attributes);
            case 'integer':
            case 'bigInteger':
            case 'smallInteger':
                if (!empty($attributes['autoIncrement'])) {
                    return match ($type) {
                        'bigInteger' => 'BIGSERIAL',
                        'smallInteger' => 'SMALLSERIAL',
                        default => 'SERIAL',
                    };
                }
                break;
            case 'jsonb':
                return 'JSONB';
        }

        // Standard type mappings
        $map = [
            'id' => 'BIGSERIAL',
            'bigIncrements' => 'BIGSERIAL',
            'increments' => 'SERIAL',
            'integerIncrements' => 'SERIAL',
            'tinyIncrements' => 'SMALLSERIAL',
            'smallIncrements' => 'SMALLSERIAL',
            'mediumIncrements' => 'SERIAL',
            'ulid' => 'CHAR(26)',
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
     * Get the fractional seconds precision suffix, e.g. (3).
     *
     * @param array $attributes
     * @return string
     */
    protected function getTimePrecision(array $attributes): string
    {
        return isset($attributes['precision']) ? '(' . (int) $attributes['precision'] . ')' : '';
    }

    /**
     * Create an ENUM type definition for 
     *
     * @param array $attributes
     * @return string
     */
    protected function createEnumType(array $attributes): string
    {
        $this->getEnumAllowedValues($attributes);

        return 'TEXT';
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
