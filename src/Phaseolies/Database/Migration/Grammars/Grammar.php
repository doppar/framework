<?php

namespace Phaseolies\Database\Migration\Grammars;

use Phaseolies\Database\Migration\ColumnDefinition;
use Phaseolies\Database\Migration\IndexDefinition;

abstract class Grammar
{
    /**
     * Column types that are auto-incrementing primary keys.
     *
     * @var array
     */
    protected const INCREMENTING_TYPES = [
        'id',
        'bigIncrements',
        'increments',
        'integerIncrements',
        'tinyIncrements',
        'smallIncrements',
        'mediumIncrements',
    ];

    /**
     * Table level options (engine, charset, collation, comment, temporary)
     *
     * @var array
     */
    protected array $tableOptions = [];

    /**
     * Set the table level options used by the next CREATE TABLE statement.
     *
     * @param array $options
     * @return static
     */
    public function setTableOptions(array $options): static
    {
        $this->tableOptions = $options;

        return $this;
    }

    /**
     * Determine whether the given column type is an auto-incrementing key.
     *
     * @param string $type
     * @return bool
     */
    public function isIncrementing(string $type): bool
    {
        return in_array($type, static::INCREMENTING_TYPES, true);
    }

    /**
     * Quote an identifier (table / column / index name).
     *
     * @param string $name
     * @return string
     */
    public function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', trim($name, '"`')) . '"';
    }

    /**
     * Quote a list of identifiers and join them with commas.
     *
     * @param array $names
     * @return string
     */
    public function quoteColumns(array $names): string
    {
        return implode(', ', array_map(fn($name) => $this->quoteIdentifier($name), $names));
    }

    /**
     * Quote a string literal.
     *
     * @param string $value
     * @return string
     */
    public function quoteString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Build the default name of an index for the given table and columns.
     * The scheme matches the one used for single-column indexes
     * (idx_{table}_{column}, {table}_{column}_unique) so that
     * dropIndex(['col']) always resolves to the index that index(['col'])
     * created. Names longer than the 63 byte identifier limit are shortened
     * with a stable hash.
     *
     * @param string $table
     * @param array $columns
     * @param string $type
     * @return string
     */
    public function indexName(string $table, array $columns, string $type = 'index'): string
    {
        $base = $table . '_' . implode('_', $columns);

        $name = match ($type) {
            'index' => 'idx_' . $base,
            default => $base . '_' . $type,
        };

        $name = preg_replace('/[^A-Za-z0-9_]/', '_', $name);

        if (strlen($name) > 63) {
            $name = substr($name, 0, 54) . '_' . substr(md5($name), 0, 8);
        }

        return $name;
    }

    /**
     * Get the SQL appended to a column type (collation, generated column).
     *
     * @param ColumnDefinition $column
     * @return string
     */
    public function compileTypeSuffix(ColumnDefinition $column): string
    {
        $sql = '';
        $attributes = $column->attributes;

        if (!empty($attributes['collation'])) {
            $sql .= ' COLLATE ' . $this->formatCollation($attributes['collation']);
        }

        $generated = $attributes['storedAs'] ?? $attributes['virtualAs'] ?? null;

        if ($generated !== null) {
            $sql .= " GENERATED ALWAYS AS ({$generated}) "
                . (isset($attributes['storedAs']) ? 'STORED' : 'VIRTUAL');
        }

        return $sql;
    }

    /**
     * Get driver specific modifiers appended after the DEFAULT clause.
     *
     * @param ColumnDefinition $column
     * @return string
     */
    public function compileColumnModifiers(ColumnDefinition $column): string
    {
        return '';
    }

    /**
     * Whether PRIMARY KEY is declared inline in the column definition.
     * Only SQLite needs this; the other grammars emit a table level clause.
     *
     * @return bool
     */
    public function shouldAddPrimaryInColumnDefinition(): bool
    {
        return false;
    }

    /**
     * Whether foreign keys can be added to an existing table.
     *
     * @return bool
     */
    public function supportsAddingForeignKey(): bool
    {
        return true;
    }

    /**
     * Append table level constraints to a compiled CREATE TABLE statement.
     *
     * @param string $createSql
     * @param array $constraints
     * @return string
     */
    public function appendTableConstraints(string $createSql, array $constraints): string
    {
        $position = strrpos($createSql, ')');

        return substr($createSql, 0, $position) . ', ' . implode(', ', $constraints) . substr($createSql, $position);
    }

    /**
     * Compile statements that must follow CREATE TABLE (table comment, ...).
     *
     * @param string $table
     * @return array
     */
    public function compileTableOptionStatements(string $table): array
    {
        return [];
    }

    /**
     * Compile statements that attach a comment to a column when the driver
     * does not support inline comments.
     *
     * @param string $table
     * @param ColumnDefinition $column
     * @return array
     */
    public function compileColumnComment(string $table, ColumnDefinition $column): array
    {
        return [];
    }

    /**
     * Compile a statement that creates a table (used to add TEMPORARY).
     *
     * @return string
     */
    protected function createTableKeyword(): string
    {
        return !empty($this->tableOptions['temporary']) ? 'CREATE TEMPORARY TABLE' : 'CREATE TABLE';
    }

    /**
     * Compile DROP COLUMN statements (one per column, SQLite has no multi-drop).
     *
     * @param string $table
     * @param array $columns
     * @return array
     */
    public function compileDropColumn(string $table, array $columns): array
    {
        return array_map(
            fn($column) => "ALTER TABLE {$this->quoteIdentifier($table)} DROP COLUMN {$this->quoteIdentifier($column)}",
            $columns
        );
    }

    /**
     * Compile a RENAME COLUMN statement.
     *
     * @param string $table
     * @param string $from
     * @param string $to
     * @return string
     */
    public function compileRenameColumn(string $table, string $from, string $to): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} RENAME COLUMN "
            . "{$this->quoteIdentifier($from)} TO {$this->quoteIdentifier($to)}";
    }

    /**
     * Compile a RENAME TABLE statement.
     *
     * @param string $from
     * @param string $to
     * @return string
     */
    public function compileRenameTable(string $from, string $to): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($from)} RENAME TO {$this->quoteIdentifier($to)}";
    }

    /**
     * Compile a DROP TABLE statement.
     *
     * @param string $table
     * @param bool $ifExists
     * @return string
     */
    public function compileDropTable(string $table, bool $ifExists = false): string
    {
        return 'DROP TABLE ' . ($ifExists ? 'IF EXISTS ' : '') . $this->quoteIdentifier($table);
    }

    /**
     * Compile a table level index (index, unique, fulltext, spatial).
     *
     * @param string $table
     * @param IndexDefinition $index
     * @return string
     */
    public function compileIndex(string $table, IndexDefinition $index): string
    {
        $name = $index->name ?? $this->indexName($table, $index->columns, $index->type);

        return match ($index->type) {
            'index' => $this->compileCreateIndexSql($table, $name, $index->columns, $index->algorithm),
            'unique' => $this->compileCreateUniqueSql($table, $name, $index->columns),
            'fulltext' => $this->compileFullTextIndex($table, $name, $index->columns),
            'spatial' => $this->compileSpatialIndex($table, $name, $index->columns),
            default => throw new \InvalidArgumentException("Unknown index type [{$index->type}]."),
        };
    }

    /**
     * Compile CREATE INDEX for one or more columns.
     *
     * @param string $table
     * @param string $name
     * @param array $columns
     * @param string|null $algorithm
     * @return string
     */
    protected function compileCreateIndexSql(string $table, string $name, array $columns, ?string $algorithm = null): string
    {
        return "CREATE INDEX {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)} "
            . ($algorithm ? "USING {$this->safeKeyword($algorithm)} " : '')
            . "({$this->quoteColumns($columns)})";
    }

    /**
     * Compile a UNIQUE constraint for one or more columns.
     *
     * @param string $table
     * @param string $name
     * @param array $columns
     * @return string
     */
    protected function compileCreateUniqueSql(string $table, string $name, array $columns): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} ADD CONSTRAINT {$this->quoteIdentifier($name)} "
            . "UNIQUE ({$this->quoteColumns($columns)})";
    }

    /**
     * Compile a full text index.
     *
     * @param string $table
     * @param string $name
     * @param array $columns
     * @return string
     */
    protected function compileFullTextIndex(string $table, string $name, array $columns): string
    {
        throw new \RuntimeException('This database driver does not support full text indexes.');
    }

    /**
     * Compile a spatial index.
     *
     * @param string $table
     * @param string $name
     * @param array $columns
     * @return string
     */
    protected function compileSpatialIndex(string $table, string $name, array $columns): string
    {
        throw new \RuntimeException('This database driver does not support spatial indexes.');
    }

    /**
     * Compile a statement that drops an index of the given type.
     *
     * @param string $table
     * @param string $name
     * @param string $type index|unique|fulltext|spatial|primary|foreign
     * @return string
     */
    public function compileDropIndex(string $table, string $name, string $type = 'index'): string
    {
        return match ($type) {
            'primary' => $this->compileDropPrimary($table),
            'foreign' => $this->compileDropForeign($table, $name),
            'unique' => $this->compileDropUnique($table, $name),
            default => 'DROP INDEX ' . $this->quoteIdentifier($name),
        };
    }

    /**
     * Compile a DROP UNIQUE statement.
     *
     * @param string $table
     * @param string $name
     * @return string
     */
    protected function compileDropUnique(string $table, string $name): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} DROP CONSTRAINT {$this->quoteIdentifier($name)}";
    }

    /**
     * Compile a DROP PRIMARY KEY statement.
     *
     * @param string $table
     * @return string
     */
    protected function compileDropPrimary(string $table): string
    {
        throw new \RuntimeException('This database driver cannot drop a primary key.');
    }

    /**
     * Compile a DROP FOREIGN KEY statement.
     *
     * @param string $table
     * @param string $name
     * @return string
     */
    protected function compileDropForeign(string $table, string $name): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} DROP CONSTRAINT {$this->quoteIdentifier($name)}";
    }

    /**
     * Compile an ADD PRIMARY KEY statement.
     *
     * @param string $table
     * @param array $columns
     * @return string
     */
    public function compileAddPrimary(string $table, array $columns): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} ADD PRIMARY KEY ({$this->quoteColumns($columns)})";
    }

    /**
     * Compile a RENAME INDEX statement.
     *
     * @param string $table
     * @param string $from
     * @param string $to
     * @return string
     */
    public function compileRenameIndex(string $table, string $from, string $to): string
    {
        throw new \RuntimeException('This database driver cannot rename indexes.');
    }

    /**
     * Compile the statements that modify an existing column.
     *
     * @param string $table
     * @param ColumnDefinition $column
     * @return array
     */
    public function compileChangeColumn(string $table, ColumnDefinition $column): array
    {
        throw new \RuntimeException('This database driver cannot modify existing columns.');
    }

    /**
     * SQL (with a single table-name binding) that lists the column names.
     *
     * @return string
     */
    abstract public function compileGetColumns(): string;

    /**
     * SQL (with a single table-name binding) that lists the index names.
     *
     * @return string
     */
    abstract public function compileGetIndexes(): string;

    /**
     * Format a collation name for use in COLLATE.
     *
     * @param string $collation
     * @return string
     */
    protected function formatCollation(string $collation): string
    {
        return $this->safeKeyword($collation);
    }

    /**
     * Allow only characters that are valid in keywords / charset names, to
     * keep schema definitions from injecting SQL.
     *
     * @param string $value
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function safeKeyword(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $value)) {
            throw new \InvalidArgumentException("Invalid identifier [{$value}].");
        }

        return $value;
    }

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
     * @param string|null $name
     * @return string
     */
    abstract public function compileCreateIndex(string $table, string $column, ?string $name = null): string;

    /**
     * Get the SQL for creating a unique constraint.
     *
     * @param string $table
     * @param string $column
     * @param string|null $name
     * @return string
     */
    abstract public function compileCreateUnique(string $table, string $column, ?string $name = null): string;

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
