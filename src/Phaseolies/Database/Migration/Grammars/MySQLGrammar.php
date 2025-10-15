<?php

namespace Phaseolies\Database\Migration\Grammars;

use Phaseolies\Database\Migration\ColumnDefinition;

class MySQLGrammar extends Grammar
{
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

        // For auto-incrementing columns, add UNSIGNED for MySQL
        if ($column->type === 'id' || $column->type === 'bigIncrements') {
            $type = 'BIGINT UNSIGNED';
        }

        return $type;
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
                    '`%s` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
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

        return "CREATE TABLE `{$table}` (" . implode(', ', $columnDefinitions) . $primaryKeySql . ") ENGINE={$this->engine}";
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
        $constraintName = "{$table}_{$column}_unique";

        return "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraintName}` UNIQUE (`{$column}`)";
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
        }

        // Standard type mappings
        $map = [
            'id' => 'BIGINT',
            'bigIncrements' => 'BIGINT',
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
        $values = $attributes['allowed'] ?? $attributes['values'] ?? null;

        if (!$values || !is_array($values)) {
            throw new \InvalidArgumentException('Enum type requires an array of allowed values');
        }

        return "'" . implode("','", $values) . "'";
    }
}
