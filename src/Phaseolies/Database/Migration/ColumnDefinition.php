<?php

namespace Phaseolies\Database\Migration;

class ColumnDefinition
{
    /** @var string The name of the column */
    public string $name;

    /** @var string The data type of the column */
    public string $type;

    /** @var array Additional attributes/constraints for the column */
    public array $attributes = [];

    /**
     * Create a new column definition instance.
     *
     * @param array $attributes The column attributes including name and type
     */
    public function __construct(array $attributes = [])
    {
        $this->name = $attributes['name'];
        $this->type = $attributes['type'];
        $this->attributes = $attributes;
    }

    /**
     * Set the column as nullable (allowing NULL values).
     *
     * @return self
     */
    public function nullable(): self
    {
        $this->attributes['nullable'] = true;
        return $this;
    }

    /**
     * Set a default value for the column.
     * Converts boolean false to 0 for database compatibility.
     *
     * @param mixed $value The default value
     * @return self
     */
    public function default($value): self
    {
        $this->attributes['default'] = $value === false ? 0 : $value;
        return $this;
    }

    /**
     * Set the column as unique.
     *
     * @return self
     */
    public function unique(): self
    {
        $this->attributes['unique'] = true;
        return $this;
    }

    /**
     * Set the column as primary key.
     *
     * @return self
     */
    public function primary(): self
    {
        $this->attributes['primary'] = true;
        return $this;
    }

    /**
     * Convert the column definition to its SQL representation.
     *
     * @return string The SQL column definition
     */
    public function toSql(): string
    {
        $sql = $this->name . ' ' . $this->getTypeDefinition();

        // Add PRIMARY KEY constraint if specified
        if (isset($this->attributes['primary']) && $this->attributes['primary']) {
            $sql .= ' PRIMARY KEY';
        }

        // Add NULL/NOT NULL constraint
        if (isset($this->attributes['nullable']) && $this->attributes['nullable']) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        // Add DEFAULT value if specified
        if (isset($this->attributes['default'])) {
            $default = is_string($this->attributes['default'])
                ? "'{$this->attributes['default']}'"  // Quote string values
                : $this->attributes['default'];       // Leave non-strings as-is
            $sql .= " DEFAULT {$default}";
        }

        return $sql;
    }

    /**
     * Get the SQL type definition for the column.
     *
     * @return string The SQL type definition
     */
    protected function getTypeDefinition(): string
    {
        // Mapping of abstract types to concrete SQL types
        $map = [
            'id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'bigIncrements' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'string' => 'VARCHAR(' . ($this->attributes['length'] ?? 255) . ')',
            'char' => 'CHAR(' . ($this->attributes['length'] ?? 255) . ')',
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
            'double' => 'DOUBLE' .
                (isset($this->attributes['precision']) ?
                    "({$this->attributes['precision']}" .
                    (isset($this->attributes['scale']) ? ",{$this->attributes['scale']})" : ")")
                    : ''),
            'decimal' => 'DECIMAL(' .
                ($this->attributes['precision'] ?? 10) . ',' .
                ($this->attributes['scale'] ?? 2) . ')',
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
            'enum' => 'ENUM(' . $this->getEnumValues() . ')',
            'set' => 'SET(' . $this->getEnumValues() . ')',
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

        // Return the mapped type or fall back to uppercase version of the type
        return $map[$this->type] ?? strtoupper($this->type);
    }

    /**
     * Get formatted enum values for ENUM/SET types.
     *
     * @return string
     */
    protected function getEnumValues(): string
    {
        if (!isset($this->attributes['values']) || !is_array($this->attributes['values'])) {
            return '';
        }

        return implode(',', array_map(function ($value) {
            return "'" . addslashes($value) . "'";
        }, $this->attributes['values']));
    }
}
