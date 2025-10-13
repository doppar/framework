<?php

namespace Phaseolies\Database\Migration\Grammars;

use Phaseolies\Database\Migration\ColumnDefinition;

class MySQLGrammar extends Grammar
{
    protected $engine = 'InnoDB';

    public function getTypeDefinition(ColumnDefinition $column): string
    {
        $type = $this->mapType($column->type, $column->attributes);

        // For auto-incrementing columns, add UNSIGNED for MySQL
        if ($column->type === 'id' || $column->type === 'bigIncrements') {
            $type = 'BIGINT UNSIGNED';
        }

        return $type;
    }
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

    public function compileAddColumn(string $table, string $columnSql): string
    {
        return "ALTER TABLE `{$table}` ADD COLUMN {$columnSql}";
    }

    public function compileCreateIndex(string $table, string $column): string
    {
        $indexName = "idx_{$table}_{$column}";
        return "CREATE INDEX `{$indexName}` ON `{$table}` (`{$column}`)";
    }

    public function compileCreateUnique(string $table, string $column): string
    {
        $constraintName = "{$table}_{$column}_unique";
        return "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraintName}` UNIQUE (`{$column}`)";
    }

    public function supportsColumnOrdering(): bool
    {
        return true;
    }

    /**
     * MySQL supporte l'ajout de clé primaire avec ALTER TABLE.
     *
     * @return bool
     */
    public function supportsAddingPrimaryKey(): bool
    {
        return true;
    }

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

    protected function getEnumValues(array $attributes): string
    {
        if (!isset($attributes['allowed'])) {
            throw new \InvalidArgumentException('Enum type requires an array of allowed values');
        }

        return "'" . implode("','", $attributes['allowed']) . "'";
    }
}
