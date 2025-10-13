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
     * @return string The SQL column definition
     */
    public function toSql(): string
    {
        $grammar = $this->getGrammar();
        $sql = $this->name . ' ' . $grammar->getTypeDefinition($this);

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

        // adding AFTER clause if specified
        if (isset($this->attributes['after'])) {
            $sql .= " AFTER {$this->attributes['after']}";
        }

        return $sql;
    }

    /**
     * Get the SQL type definition for the column.
     *
     * @return string The SQL type definition
     */
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
