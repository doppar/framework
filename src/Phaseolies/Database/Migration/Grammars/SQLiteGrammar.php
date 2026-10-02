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
        $type = $this->mapType($column->type, $column->attributes);

        if ($column->type === 'enum') {
            $values = $this->getEnumAllowedValues($column->attributes);
            $type .= ' ' . $this->compileEnumCheckClause($column->name, $values);
        }

        return $type . $this->compileTypeSuffix($column);
    }

    /**
     * Quote an identifier with backticks.
     *
     * @param string $name
     * @return string
     */
    public function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', trim($name, '"`')) . '`';
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
                           $this->isIncrementing($column->type);

            if ($isPrimaryKey) {
                if ($this->isIncrementing($column->type)) {
                    $cleanSql = preg_replace('/\s+PRIMARY\s+KEY/i', '', $originalSql);
                    $columnSql = str_replace('INTEGER', 'INTEGER PRIMARY KEY AUTOINCREMENT', $cleanSql);
                    $hasAutoIncrementId = true;
                } elseif ($column->type === 'uuid' && !empty($column->attributes['primary'])) {
                    // SQLite has no UUID function — strip PRIMARY KEY from column
                    // and add as separate clause, UUID generated at application layer
                    $columnSql = preg_replace('/\s+PRIMARY\s+KEY/i', '', $originalSql);
                    $tablePrimaryKeys[] = $column->name;
                    $hasAutoIncrementId = true; // prevent duplicate PRIMARY KEY clause
                }elseif (!empty($column->attributes['primary'])) {
                    $tablePrimaryKeys[] = $column->name;
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

        return $this->createTableKeyword() . " `{$table}` (" . implode(', ', $columnDefinitions) . $primaryKeySql . ')';
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
        // SQLite does not support adding constraints UNIQUE with ALTER TABLE
        return '';
    }

    /**
     * SQLite has no ALTER TABLE ADD CONSTRAINT, but a unique index is
     * equivalent and can be created at any time.
     *
     * @param string $table
     * @param string $name
     * @param array $columns
     * @return string
     */
    protected function compileCreateUniqueSql(string $table, string $name, array $columns): string
    {
        return "CREATE UNIQUE INDEX {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)} "
            . "({$this->quoteColumns($columns)})";
    }

    protected function compileDropUnique(string $table, string $name): string
    {
        return 'DROP INDEX ' . $this->quoteIdentifier($name);
    }

    public function compileAddPrimary(string $table, array $columns): string
    {
        throw new \RuntimeException(
            'SQLite cannot add a primary key to an existing table. Declare it in the CREATE TABLE migration.'
        );
    }

    protected function compileDropPrimary(string $table): string
    {
        throw new \RuntimeException('SQLite cannot drop a primary key from an existing table.');
    }

    protected function compileDropForeign(string $table, string $name): string
    {
        throw new \RuntimeException('SQLite cannot drop a foreign key from an existing table.');
    }

    public function compileChangeColumn(string $table, ColumnDefinition $column): array
    {
        throw new \RuntimeException(
            "SQLite cannot modify column \"{$column->name}\" on table \"{$table}\". "
            . 'Create a new table, copy the data and drop the old one instead.'
        );
    }

    /**
     * SQLite cannot add a foreign key with ALTER TABLE; it must be part of CREATE TABLE.
     *
     * @return bool
     */
    public function supportsAddingForeignKey(): bool
    {
        return false;
    }

    /**
     * SQLite keeps PRIMARY KEY in the column definition.
     *
     * @return bool
     */
    public function shouldAddPrimaryInColumnDefinition(): bool
    {
        return true;
    }

    public function compileGetColumns(): string
    {
        return 'SELECT name FROM pragma_table_info(?)';
    }

    public function compileGetIndexes(): string
    {
        return 'SELECT name FROM pragma_index_list(?)';
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
            'increments' => 'INTEGER',
            'integerIncrements' => 'INTEGER',
            'tinyIncrements' => 'INTEGER',
            'smallIncrements' => 'INTEGER',
            'mediumIncrements' => 'INTEGER',
            'ulid' => 'TEXT',
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
