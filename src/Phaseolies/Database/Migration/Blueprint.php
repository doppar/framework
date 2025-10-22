<?php

namespace Phaseolies\Database\Migration;

use PDO;
use Phaseolies\Database\Migration\Grammars\GrammarFactory;
use Phaseolies\Database\Migration\Grammars\Grammar;
use Phaseolies\Database\Migration\ColumnDefinition;

class Blueprint
{
    /** @var string The name of the table being created or modified */
    public string $table;

    /** @var array Collection of ColumnDefinition objects for the table */
    protected array $columns = [];

    /** @var array Collection of commands (like foreign key constraints) */
    protected array $commands = [];

    /** @var string The primary key column name */
    protected string $primaryKey = '';

    /** @var Grammar The grammar instance for the current database driver */
    protected Grammar $grammar;

    /**
     * Create a new table blueprint instance.
     *
     * @param string $table
     * @param string|null $driver
     */
    public function __construct(string $table, ?string $driver = null)
    {
        $this->table = $table;
        $this->grammar = GrammarFactory::make($driver ?? $this->getDefaultDriver());
    }

    /**
     * Get the default database driver.
     *
     * @return string
     */
    protected function getDefaultDriver(): string
    {
        $connection = app('db')->getConnection();

        return strtolower($connection->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    /**
     * Create an auto-incrementing primary key column (alias for bigIncrements with primary key).
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($column)->primary();
    }

    /**
     * Create a TINYINT column (1-byte integer, range: -128 to 127)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function tinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $column);
    }

    /**
     * Create a SMALLINT column (2-byte integer, range: -32,768 to 32,767)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function smallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $column);
    }

    /**
     * Create a MEDIUMINT column (3-byte integer, range: -8,388,608 to 8,388,607)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function mediumInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumInteger', $column);
    }

    /**
     * Create a BIGINT column (8-byte integer, large range)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column);
    }

    /**
     * Create an UNSIGNED INT column (4-byte, only positive numbers)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedInteger', $column);
    }

    /**
     * Create an UNSIGNED TINYINT column (1-byte, only positive numbers)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function unsignedTinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedTinyInteger', $column);
    }

    /**
     * Create an UNSIGNED SMALLINT column (2-byte, only positive numbers)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function unsignedSmallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedSmallInteger', $column);
    }

    /**
     * Create an UNSIGNED MEDIUMINT column (3-byte, only positive numbers)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function unsignedMediumInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedMediumInteger', $column);
    }

    /**
     * Create a FLOAT column (single-precision floating point number)
     *
     * @param string $column
     * @param int|null $precision
     * @param int|null $scale
     * @return ColumnDefinition
     */
    public function float(string $column, ?int $precision = null, ?int $scale = null): ColumnDefinition
    {
        return $this->addColumn('float', $column, array_filter(compact('precision', 'scale')));
    }

    /**
     * Create a DECIMAL column (fixed-point number, exact precision)
     *
     * @param string $column
     * @param int $precision
     * @param int $scale
     * @return ColumnDefinition
     */
    public function decimal(string $column, int $precision = 10, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, compact('precision', 'scale'));
    }

    /**
     * Create a CHAR column (fixed-length string)
     *
     * @param string $column
     * @param int $length Fixed
     * @return ColumnDefinition
     */
    public function char(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('char', $column, compact('length'));
    }

    /**
     * Create a TEXT column (variable-length string, up to 65,535 characters)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Create a TINYTEXT column (variable-length string, up to 255 characters)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function tinyText(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyText', $column);
    }

    /**
     * Create a DATE column (date only, no time)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Create a DATETIME column (date and time, no timezone)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function dateTime(string $column): ColumnDefinition
    {
        return $this->addColumn('dateTime', $column);
    }

    /**
     * Create a DATETIME column with timezone awareness
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function dateTimeTz(string $column): ColumnDefinition
    {
        return $this->addColumn('dateTimeTz', $column);
    }

    /**
     * Create a TIME column (time only, no date)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function time(string $column): ColumnDefinition
    {
        return $this->addColumn('time', $column);
    }

    /**
     * Create a TIME column with timezone awareness
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function timeTz(string $column): ColumnDefinition
    {
        return $this->addColumn('timeTz', $column);
    }

    /**
     * Create a TIMESTAMP column with timezone awareness
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function timestampTz(string $column): ColumnDefinition
    {
        return $this->addColumn('timestampTz', $column);
    }

    /**
     * Create a YEAR column (year only, 2 or 4 digit format)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function year(string $column): ColumnDefinition
    {
        return $this->addColumn('year', $column);
    }

    /**
     * Create a BLOB column (binary data, up to 65,535 bytes)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a TINYBLOB column (binary data, up to 255 bytes)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function tinyBlob(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyBlob', $column);
    }

    /**
     * Create a MEDIUMBLOB column (binary data, up to 16,777,215 bytes)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function mediumBlob(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumBlob', $column);
    }

    /**
     * Create a LONGBLOB column (binary data, up to 4GB)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function longBlob(string $column): ColumnDefinition
    {
        return $this->addColumn('longBlob', $column);
    }

    /**
     * Create an ENUM column (string with predefined possible values)
     *
     * @param string $column
     * @param array $values
     * @return ColumnDefinition
     */
    public function enum(string $column, array $values): ColumnDefinition
    {
        return $this->addColumn('enum', $column, ['values' => $values]);
    }

    /**
     * Create an ENUM column with nullable option.
     *
     * @param string $column
     * @param array $values
     * @param bool $nullable
     * @return ColumnDefinition
     */
    public function enumNullable(string $column, array $values, bool $nullable = true): ColumnDefinition
    {
        return $this->addColumn('enum', $column, ['values' => $values, 'nullable' => $nullable]);
    }


    /**
     * Create a SET column (string that can have zero or more values from predefined set)
     *
     * @param string $column
     * @param array $values
     * @return ColumnDefinition
     */
    public function set(string $column, array $values): ColumnDefinition
    {
        return $this->addColumn('set', $column, ['values' => $values]);
    }

    /**
     * Create a UUID column (stored as CHAR(36))
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function uuid(string $column): ColumnDefinition
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * Create an IP address column (stored as VARCHAR(45) to support IPv6)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function ipAddress(string $column): ColumnDefinition
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * Create a MAC address column (stored as VARCHAR(17))
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function macAddress(string $column): ColumnDefinition
    {
        return $this->addColumn('macAddress', $column);
    }

    /**
     * Create a GEOMETRY column (any type of spatial data)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function geometry(string $column): ColumnDefinition
    {
        return $this->addColumn('geometry', $column);
    }

    /**
     * Create a POINT column (single location in coordinate space)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function point(string $column): ColumnDefinition
    {
        return $this->addColumn('point', $column);
    }

    /**
     * Create a LINESTRING column (curve with linear interpolation between points)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function lineString(string $column): ColumnDefinition
    {
        return $this->addColumn('lineString', $column);
    }

    /**
     * Create a POLYGON column (polygonal surface)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function polygon(string $column): ColumnDefinition
    {
        return $this->addColumn('polygon', $column);
    }

    /**
     * Create a GEOMETRYCOLLECTION column (collection of geometry objects)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function geometryCollection(string $column): ColumnDefinition
    {
        return $this->addColumn('geometryCollection', $column);
    }

    /**
     * Create a MULTIPOINT column (collection of points)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function multiPoint(string $column): ColumnDefinition
    {
        return $this->addColumn('multiPoint', $column);
    }

    /**
     * Create a MULTILINESTRING column (collection of linestrings)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function multiLineString(string $column): ColumnDefinition
    {
        return $this->addColumn('multiLineString', $column);
    }

    /**
     * Create a MULTIPOLYGON column (collection of polygons)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function multiPolygon(string $column): ColumnDefinition
    {
        return $this->addColumn('multiPolygon', $column);
    }

    /**
     * Create a big auto-incrementing unsigned integer column.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function bigIncrements(string $column): ColumnDefinition
    {
        $column = $this->addColumn('bigIncrements', $column);
        return $column;
    }

    /**
     * Create a string (VARCHAR) column.
     *
     * @param string $column
     * @param int $length
     * @return ColumnDefinition
     */
    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $column, compact('length'));
    }

    /**
     * Create a medium text column.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * Create a long text column.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Create a boolean (TINYINT(1)) column.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }
    
    /**
     * Create a BIT column (binary flag)
     *
     * @param string $column
     * @param int $length
     * @return ColumnDefinition
     */
    public function bit(string $column, int $length = 1): ColumnDefinition
    {
        $driver = $this->connection->getDriverName();
    
        if ($driver === 'pgsql') {
            // PostgreSQL does not support BIT(1) for boolean flags
            return $this->boolean($column);
        }
    
        // Default: MySQL and others
        return $this->addColumn('bit', $column, compact('length'));
    }
    /**
     * Create a JSON column.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create a JSON column that will store arrays.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function jsonArray(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create an integer column.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column);
    }

    /**
     * Create a timestamp column.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column);
    }

    /**
     * Add nullable creation and update timestamps to the table.
     *
     * @return void
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /**
     * Add created_at and updated_at with timezone awareness.
     *
     * @return void
     */
    public function timestampsTz(): void
    {
        $this->timestampTz('created_at')->nullable();
        $this->timestampTz('updated_at')->nullable();
    }

    /**
     * Add a nullable deletion timestamp to the table.
     *
     * @return void
     */
    public function softDeletes(): void
    {
        $this->timestamp('deleted_at')->nullable();
    }

    /**
     * Create a foreign key column for the given model with optional cascade options.
     *
     * @param string $model
     * @param bool $onDeleteCascade
     * @param bool $onUpdateCascade
     * @return ColumnDefinition
     */
    public function foreignIdFor(string $model, bool $onDeleteCascade = false, bool $onUpdateCascade = false): ColumnDefinition
    {
        $modelInstance = new $model();
        $foreignKey = $this->snakeCase(class_basename($model)) . '_id';

        $column = $this->unsignedBigInteger($foreignKey);

        $foreignKeyDefinition = $this->foreign($foreignKey)
            ->references('id')
            ->on($modelInstance->getTable());

        if ($onDeleteCascade) {
            $foreignKeyDefinition->onDelete('cascade');
        }

        if ($onUpdateCascade) {
            $foreignKeyDefinition->onUpdate('cascade');
        }

        return $column;
    }

    /**
     * Create a foreign key constraint on the given column.
     *
     * @param string $column
     * @return ForeignKeyDefinition
     */
    public function foreign(string $column): ForeignKeyDefinition
    {
        $foreign = new ForeignKeyDefinition($this, $column);
        $this->commands[] = $foreign;

        return $foreign;
    }

    /**
     * Add a new column to the blueprint.
     *
     * @param string $type
     * @param string $name
     * @param array $parameters
     * @return ColumnDefinition
     */
    public function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $column = new ColumnDefinition(array_merge(compact('type', 'name'), $parameters));

        $this->columns[] = $column;

        return $column;
    }

    /**
     * Create an unsigned big integer column.
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedBigInteger', $column);
    }

    /**
     * Create a DOUBLE column (double-precision floating point number)
     *
     * @param string $column
     * @param int|null $precision
     * @param int|null $scale
     * @return ColumnDefinition
     */
    public function double(string $column, ?int $precision = null, ?int $scale = null): ColumnDefinition
    {
        return $this->addColumn('double', $column, array_filter(compact('precision', 'scale')));
    }

    /**
     * Create a BLOB column (binary data, up to 65,535 bytes)
     *
     * @param string $column
     * @return ColumnDefinition
     */
    public function blob(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a JSONB column (binary JSON format, more efficient for storage and querying)
     * Note: In MySQL, JSONB is the same as JSON (unlike PostgreSQL which has distinct types)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function jsonb(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Convert the blueprint to SQL statements.
     *
     * @return string
     * @throws \RuntimeException
     */
    public function toSql(): string
    {
        $statements = [];

        // Convert all columns to their SQL representations
        $columns = array_values(array_filter($this->columns));

        if (empty($columns)) {
            throw new \RuntimeException("No columns defined for table {$this->table}");
        }

        $hasColumnOrdering = !empty(array_filter($columns, function ($col) {
            return isset($col->attributes['after']);
        }));

        if (!$hasColumnOrdering || !$this->grammar->supportsColumnOrdering()) {
            // Create table with all columns
            $columnDefinitions = [];
            $primaryKeys = [];

            // First pass: collect column definitions and primary keys
            foreach ($columns as $column) {
                $columnSql = $column->toSql();

                // If this is a primary key column, note it for later
                if (isset($column->attributes['primary']) && $column->attributes['primary']) {
                    $columnName = trim(explode(' ', $column->name)[0], '`');
                    $primaryKeys[] = $columnName;
                }

                $columnDefinitions[] = $columnSql;
            }

            // Let the grammar handle the table creation with primary keys
            $statements[] = $this->grammar->compileCreateTable($this->table, $columns, $primaryKeys);

            // Add indexes and unique constraints
            foreach ($columns as $column) {
                $this->handleIndexAndUniqueColumn($column, $statements);
            }
        } else {
            // Handle ALTER TABLE for adding columns with ordering
            foreach ($columns as $column) {
                $columnSql = $column->toSql();

                // Skip if this is a primary key column and the grammar doesn't support adding it with ALTER
                if ((isset($column->attributes['primary']) && $column->attributes['primary']) &&
                    !$this->grammar->supportsAddingPrimaryKey()
                ) {
                    continue;
                }

                $statements[] = $this->grammar->compileAddColumn($this->table, $columnSql);
                $this->handleIndexAndUniqueColumn($column, $statements);
            }
        }

        // Add any additional commands (like foreign keys)
        foreach ($this->commands as $command) {
            if ($command instanceof ForeignKeyDefinition) {
                $statements[] = $command->toSql();
            }
        }

        return implode(';' . PHP_EOL, $statements) . ';';
    }

    /**
     * Handles the creation of index and unique constraints for a column.
     *
     * @param ColumnDefinition $column
     * @param array &$statements
     * @return void
     */
    protected function handleIndexAndUniqueColumn(ColumnDefinition $column, array &$statements): void
    {
        if (isset($column->attributes['index']) && $column->attributes['index']) {
            $statements[] = $this->grammar->compileCreateIndex($this->table, $column->name);
        }

        if (isset($column->attributes['unique']) && $column->attributes['unique']) {
            $sql = $this->grammar->compileCreateUnique($this->table, $column->name);
            if (!empty($sql)) {
                $statements[] = $sql;
            }
        }
    }

    /**
     * Check if the current database connection is SQLite.
     *
     * @return bool
     */
    protected function isSQLite(): bool
    {
        return $this->getDefaultDriver() === 'sqlite';
    }

    /**
     * Convert the given string to snake_case.
     *
     * @param string $input
     * @return string
     */
    protected function snakeCase(string $input): string
    {
        return str()->snake($input);
    }
}
