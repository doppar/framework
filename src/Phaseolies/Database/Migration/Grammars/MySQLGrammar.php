<?php

namespace Phaseolies\Database\Migration\Grammars;

use Phaseolies\Database\Migration\ColumnDefinition;

class MySQLGrammar extends Grammar
{
    /**
     * @var string
     */
    protected $engine = 'InnoDB';

    /**
     * Get the SQL data type definition for a given column
     *
     * @param ColumnDefinition $column
     * @return string
     */
    public function getTypeDefinition(ColumnDefinition $column): string
    {
        $type = $this->mapType($column->type, $column->attributes);

        // Auto-incrementing key columns are always unsigned
        if ($this->isIncrementing($column->type)) {
            $type = $this->incrementingType($column->type);
        } elseif (!empty($column->attributes['unsigned']) && !str_contains($type, 'UNSIGNED')) {
            if (preg_match('/^(TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT|FLOAT|DOUBLE|DECIMAL)\b(?!\(1\))/', $type)) {
                $type .= ' UNSIGNED';
            }
        }

        return $type . $this->compileTypeSuffix($column);
    }

    /**
     * Get the unsigned integer type backing an incrementing column.
     *
     * @param string $type
     * @return string
     */
    protected function incrementingType(string $type): string
    {
        return match ($type) {
            'increments', 'integerIncrements' => 'INT UNSIGNED',
            'tinyIncrements' => 'TINYINT UNSIGNED',
            'smallIncrements' => 'SMALLINT UNSIGNED',
            'mediumIncrements' => 'MEDIUMINT UNSIGNED',
            default => 'BIGINT UNSIGNED',
        };
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
     * Quote a string literal (MySQL treats backslash as an escape character).
     *
     * @param string $value
     * @return string
     */
    public function quoteString(string $value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $value) . "'";
    }

    /**
     * Character set and collation are both column level in MySQL.
     *
     * @param ColumnDefinition $column
     * @return string
     */
    public function compileTypeSuffix(ColumnDefinition $column): string
    {
        $sql = '';

        if (!empty($column->attributes['charset'])) {
            $sql .= ' CHARACTER SET ' . $this->safeKeyword($column->attributes['charset']);
        }

        return $sql . parent::compileTypeSuffix($column);
    }

    /**
     * MySQL specific column modifiers placed after DEFAULT.
     *
     * @param ColumnDefinition $column
     * @return string
     */
    public function compileColumnModifiers(ColumnDefinition $column): string
    {
        $attributes = $column->attributes;
        $sql = '';

        if (!empty($attributes['useCurrentOnUpdate'])) {
            $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
        }

        if ($this->isIncrementing($column->type) || !empty($attributes['autoIncrement'])) {
            $sql .= ' AUTO_INCREMENT';
        }

        if (!empty($attributes['invisible'])) {
            $sql .= ' INVISIBLE';
        }

        if (isset($attributes['comment'])) {
            $sql .= ' COMMENT ' . $this->quoteString($attributes['comment']);
        }

        return $sql;
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
                    '`%s` %s NOT NULL AUTO_INCREMENT',
                    $column->name,
                    $this->incrementingType($column->type)
                );
                $primaryKeyColumns[] = trim($column->name, '`');
            } elseif ($column->type === 'uuid' && !empty($column->attributes['primary'])) {
                // UUID primary key with auto-generation
                $columnSql = sprintf(
                    '`%s` CHAR(36) NOT NULL DEFAULT (UUID())',
                    $column->name
                );
                $primaryKeyColumns[] = trim($column->name, '`');
            } elseif (isset($column->attributes['primary']) && $column->attributes['primary']) {
                $primaryKeyColumns[] = trim($column->name, '`');
            }

            $columnDefinitions[] = $columnSql;
        }

        $primaryKeySql = '';
        if (!empty($primaryKeyColumns)) {
            $primaryKeySql = ', PRIMARY KEY (`' . implode('`, `', $primaryKeyColumns) . '`)';
        }

        $options = $this->tableOptions;
        $sql = $this->createTableKeyword() . " `{$table}` (" . implode(', ', $columnDefinitions) . $primaryKeySql . ')';
        $sql .= ' ENGINE=' . $this->safeKeyword($options['engine'] ?? $this->engine);

        if (!empty($options['charset'])) {
            $sql .= ' DEFAULT CHARSET=' . $this->safeKeyword($options['charset']);
        }

        if (!empty($options['collation'])) {
            $sql .= ' COLLATE=' . $this->safeKeyword($options['collation']);
        }

        if (isset($options['comment'])) {
            $sql .= ' COMMENT=' . $this->quoteString($options['comment']);
        }

        return $sql;
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
    public function compileCreateIndex(string $table, string $column, ?string $name = null): string
    {
        return $this->compileCreateIndexSql($table, $name ?? $this->indexName($table, [$column]), [$column]);
    }

    /**
     * Compile a SQL statement to add a unique constraint on a column
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    public function compileCreateUnique(string $table, string $column, ?string $name = null): string
    {
        return $this->compileCreateUniqueSql($table, $name ?? $this->indexName($table, [$column], 'unique'), [$column]);
    }

    /**
     * MySQL builds hash/btree choice into the index definition.
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
            . "({$this->quoteColumns($columns)})"
            . ($algorithm ? ' USING ' . $this->safeKeyword($algorithm) : '');
    }

    protected function compileFullTextIndex(string $table, string $name, array $columns): string
    {
        return "CREATE FULLTEXT INDEX {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)} "
            . "({$this->quoteColumns($columns)})";
    }

    protected function compileSpatialIndex(string $table, string $name, array $columns): string
    {
        return "CREATE SPATIAL INDEX {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)} "
            . "({$this->quoteColumns($columns)})";
    }

    public function compileDropIndex(string $table, string $name, string $type = 'index'): string
    {
        return match ($type) {
            'primary' => $this->compileDropPrimary($table),
            'foreign' => $this->compileDropForeign($table, $name),
            default => "DROP INDEX {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)}",
        };
    }

    protected function compileDropPrimary(string $table): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} DROP PRIMARY KEY";
    }

    protected function compileDropForeign(string $table, string $name): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} DROP FOREIGN KEY {$this->quoteIdentifier($name)}";
    }

    public function compileRenameTable(string $from, string $to): string
    {
        return "RENAME TABLE {$this->quoteIdentifier($from)} TO {$this->quoteIdentifier($to)}";
    }

    public function compileRenameIndex(string $table, string $from, string $to): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} RENAME INDEX "
            . "{$this->quoteIdentifier($from)} TO {$this->quoteIdentifier($to)}";
    }

    public function compileChangeColumn(string $table, ColumnDefinition $column): array
    {
        return ["ALTER TABLE {$this->quoteIdentifier($table)} MODIFY COLUMN {$column->toSql()}"];
    }

    public function compileGetColumns(): string
    {
        return 'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION';
    }

    public function compileGetIndexes(): string
    {
        return 'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?';
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
     * MySQL supports adding a primary key
     *
     * @return bool
     */
    public function supportsAddingPrimaryKey(): bool
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
        // Handle special cases that require attributes first
        switch ($type) {
            case 'enum':
                return 'ENUM(' . $this->getEnumValues($attributes) . ')';
            case 'set':
                return 'SET(' . $this->getEnumValues($attributes) . ')';
            case 'string':
                return 'VARCHAR(' . ($attributes['length'] ?? 255) . ')';
            case 'char':
                return 'CHAR(' . ($attributes['length'] ?? 255) . ')';
            case 'decimal':
                return 'DECIMAL(' . ($attributes['precision'] ?? 10) . ',' . ($attributes['scale'] ?? 2) . ')';
            case 'double':
                return 'DOUBLE' . $this->getPrecisionAndScale($attributes);
            case 'timestamp':
            case 'timestampTz':
                return 'TIMESTAMP' . $this->getTimePrecision($attributes);
            case 'dateTime':
            case 'dateTimeTz':
                return 'DATETIME' . $this->getTimePrecision($attributes);
            case 'time':
            case 'timeTz':
                return 'TIME' . $this->getTimePrecision($attributes);
        }

        // Standard type mappings
        $map = [
            'id' => 'BIGINT',
            'bigIncrements' => 'BIGINT',
            'increments' => 'INT',
            'integerIncrements' => 'INT',
            'tinyIncrements' => 'TINYINT',
            'smallIncrements' => 'SMALLINT',
            'mediumIncrements' => 'MEDIUMINT',
            'ulid' => 'CHAR(26)',
            'text' => 'TEXT',
            'mediumText' => 'MEDIUMTEXT',
            'longText' => 'LONGTEXT',
            'tinyText' => 'TINYTEXT',
            'boolean' => 'TINYINT(1)',
            'json' => 'JSON',
            'jsonb' => 'JSON',
            'integer' => 'INT',
            'tinyInteger' => 'TINYINT',
            'smallInteger' => 'SMALLINT',
            'mediumInteger' => 'MEDIUMINT',
            'bigInteger' => 'BIGINT',
            'unsignedInteger' => 'INT UNSIGNED',
            'unsignedTinyInteger' => 'TINYINT UNSIGNED',
            'unsignedSmallInteger' => 'SMALLINT UNSIGNED',
            'unsignedMediumInteger' => 'MEDIUMINT UNSIGNED',
            'unsignedBigInteger' => 'BIGINT UNSIGNED',
            'float' => 'FLOAT',
            'date' => 'DATE',
            'dateTime' => 'DATETIME',
            'dateTimeTz' => 'DATETIME',
            'time' => 'TIME',
            'timeTz' => 'TIME',
            'timestamp' => 'TIMESTAMP',
            'timestampTz' => 'TIMESTAMP',
            'year' => 'YEAR',
            'binary' => 'BLOB',
            'tinyBlob' => 'TINYBLOB',
            'mediumBlob' => 'MEDIUMBLOB',
            'longBlob' => 'LONGBLOB',
            'geometry' => 'GEOMETRY',
            'point' => 'POINT',
            'lineString' => 'LINESTRING',
            'polygon' => 'POLYGON',
            'geometryCollection' => 'GEOMETRYCOLLECTION',
            'multiPoint' => 'MULTIPOINT',
            'multiLineString' => 'MULTILINESTRING',
            'multiPolygon' => 'MULTIPOLYGON',
            'uuid' => 'CHAR(36)',
            'ipAddress' => 'VARCHAR(45)',
            'macAddress' => 'VARCHAR(17)',
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
     * Get the precision and scale for decimal/double types
     *
     * @param array $attributes
     * @return string
     */
    protected function getPrecisionAndScale(array $attributes): string
    {
        if (!isset($attributes['precision'])) {
            return '';
        }

        $result = "({$attributes['precision']}";
        if (isset($attributes['scale'])) {
            $result .= ",{$attributes['scale']}";
        }
        $result .= ')';

        return $result;
    }

    /**
     * Get the enum values
     *
     * @param array $attributes
     * @return string
     */
    protected function getEnumValues(array $attributes): string
    {
        $values = $this->getEnumAllowedValues($attributes);

        $quoted = array_map(
            fn($value) => "'" . str_replace("'", "''", (string) $value) . "'",
            $values
        );

        return implode(',', $quoted);
    }
}
