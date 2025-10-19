<?php

namespace Phaseolies\Database\Migration;

use PDO;

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
     * @param array $attributes
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
     *
     * @param mixed $value
     * @return self
     */
    public function default($value): self
    {
        $connection = app('db')->getConnection();
        $driver = strtolower($connection->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver === 'pgsql' && is_bool($value)) {
            // PGSQL
            $this->attributes['default'] = $value ? 'true' : 'false';
        } else {
            $this->attributes['default'] = $value === false ? 0 : $value;
        }

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
     * Set the column to be indexed.
     *
     * @return self
     */
    public function index(): self
    {
        $this->attributes['index'] = true;

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
     * Set the column for after a column
     *
     * @param string $column
     * @return self
     */
    public function after(string $column): self
    {
        $this->attributes['after'] = $column;

        return $this;
    }

    /**
     * Convert the column definition to its SQL representation.
     *
     * @return string
     */
    public function toSql(): string
    {
        $grammar = $this->getGrammar();
        $sql = $this->name . ' ' . $grammar->getTypeDefinition($this);

        // Add PRIMARY KEY constraint if specified
        if (isset($this->attributes['primary']) && $this->attributes['primary']) {
            $sql .= ' PRIMARY KEY';
        }

        // Add UNIQUE constraint if grammar requires it in column definition
        if (!empty($this->attributes['unique']) && $grammar->shouldAddUniqueInColumnDefinition()) {
            $sql .= ' UNIQUE';
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

        // adding AFTER clause if specified
        // PGSQL No support this
        // if (isset($this->attributes['after'])) {
        //     $sql .= " AFTER {$this->attributes['after']}";
        // }

        return $sql;
    }

    /**
     * Get the appropriate grammar instance based on the database driver.
     *
     * @return Grammars\Grammar
     */
    protected function getGrammar(): Grammars\Grammar
    {
        $connection = app('db')->getConnection();
        $driver = strtolower($connection->getAttribute(PDO::ATTR_DRIVER_NAME));

        return match ($driver) {
            'mysql' => new Grammars\MySQLGrammar(),
            'sqlite' => new Grammars\SQLiteGrammar(),
            'pgsql' => new Grammars\PostgreSQLGrammar(),
            default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
        };
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
