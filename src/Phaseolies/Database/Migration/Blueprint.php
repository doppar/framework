<?php

namespace Phaseolies\Database\Migration;

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

    /** @var string The database engine to be used (default: InnoDB) */
    protected string $engine = 'InnoDB';

    /**
     * Create a new table blueprint instance.
     *
     * @param string $table The name of the table
     */
    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Create an auto-incrementing primary key column (alias for bigIncrements with primary key).
     *
     * @param string $column The column name (default: 'id')
     * @return ColumnDefinition
     */
    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($column)->primary();
    }

    /**
     * Create a TINYINT column (1-byte integer, range: -128 to 127)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function tinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $column);
    }

    /**
     * Create a SMALLINT column (2-byte integer, range: -32,768 to 32,767)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function smallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $column);
    }

    /**
     * Create a MEDIUMINT column (3-byte integer, range: -8,388,608 to 8,388,607)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function mediumInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumInteger', $column);
    }

    /**
     * Create a BIGINT column (8-byte integer, large range)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column);
    }

    /**
     * Create an UNSIGNED INT column (4-byte, only positive numbers)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedInteger', $column);
    }

    /**
     * Create an UNSIGNED TINYINT column (1-byte, only positive numbers)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function unsignedTinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedTinyInteger', $column);
    }

    /**
     * Create an UNSIGNED SMALLINT column (2-byte, only positive numbers)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function unsignedSmallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedSmallInteger', $column);
    }

    /**
     * Create an UNSIGNED MEDIUMINT column (3-byte, only positive numbers)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function unsignedMediumInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedMediumInteger', $column);
    }

    /**
     * Create a FLOAT column (single-precision floating point number)
     *
     * @param string $column The column name
     * @param int|null $precision Total number of digits (optional)
     * @param int|null $scale Number of digits after decimal point (optional)
     * @return ColumnDefinition
     */
    public function float(string $column, int $precision = null, int $scale = null): ColumnDefinition
    {
        return $this->addColumn('float', $column, array_filter(compact('precision', 'scale')));
    }

    /**
     * Create a DECIMAL column (fixed-point number, exact precision)
     *
     * @param string $column The column name
     * @param int $precision Total number of digits (default: 10)
     * @param int $scale Number of digits after decimal point (default: 2)
     * @return ColumnDefinition
     */
    public function decimal(string $column, int $precision = 10, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, compact('precision', 'scale'));
    }

    /*
     * String Type Methods
     */

    /**
     * Create a CHAR column (fixed-length string)
     *
     * @param string $column The column name
     * @param int $length Fixed length of the string (default: 255)
     * @return ColumnDefinition
     */
    public function char(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('char', $column, compact('length'));
    }

    /**
     * Create a TEXT column (variable-length string, up to 65,535 characters)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Create a TINYTEXT column (variable-length string, up to 255 characters)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function tinyText(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyText', $column);
    }

    /*
     * Date/Time Type Methods
     */

    /**
     * Create a DATE column (date only, no time)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Create a DATETIME column (date and time, no timezone)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function dateTime(string $column): ColumnDefinition
    {
        return $this->addColumn('dateTime', $column);
    }

    /**
     * Create a DATETIME column with timezone awareness
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function dateTimeTz(string $column): ColumnDefinition
    {
        return $this->addColumn('dateTimeTz', $column);
    }

    /**
     * Create a TIME column (time only, no date)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function time(string $column): ColumnDefinition
    {
        return $this->addColumn('time', $column);
    }

    /**
     * Create a TIME column with timezone awareness
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function timeTz(string $column): ColumnDefinition
    {
        return $this->addColumn('timeTz', $column);
    }

    /**
     * Create a TIMESTAMP column with timezone awareness
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function timestampTz(string $column): ColumnDefinition
    {
        return $this->addColumn('timestampTz', $column);
    }

    /**
     * Create a YEAR column (year only, 2 or 4 digit format)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function year(string $column): ColumnDefinition
    {
        return $this->addColumn('year', $column);
    }

    /*
     * Binary Type Methods
     */

    /**
     * Create a BLOB column (binary data, up to 65,535 bytes)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a TINYBLOB column (binary data, up to 255 bytes)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function tinyBlob(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyBlob', $column);
    }

    /**
     * Create a MEDIUMBLOB column (binary data, up to 16,777,215 bytes)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function mediumBlob(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumBlob', $column);
    }

    /**
     * Create a LONGBLOB column (binary data, up to 4GB)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function longBlob(string $column): ColumnDefinition
    {
        return $this->addColumn('longBlob', $column);
    }

    /*
     * Special Type Methods
     */

    /**
     * Create an ENUM column (string with predefined possible values)
     *
     * @param string $column The column name
     * @param array $values Allowed values for the enum
     * @return ColumnDefinition
     */
    public function enum(string $column, array $values): ColumnDefinition
    {
        return $this->addColumn('enum', $column, ['values' => $values]);
    }

    /**
     * Create a SET column (string that can have zero or more values from predefined set)
     *
     * @param string $column The column name
     * @param array $values Allowed values for the set
     * @return ColumnDefinition
     */
    public function set(string $column, array $values): ColumnDefinition
    {
        return $this->addColumn('set', $column, ['values' => $values]);
    }

    /**
     * Create a UUID column (stored as CHAR(36))
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function uuid(string $column): ColumnDefinition
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * Create an IP address column (stored as VARCHAR(45) to support IPv6)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function ipAddress(string $column): ColumnDefinition
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * Create a MAC address column (stored as VARCHAR(17))
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function macAddress(string $column): ColumnDefinition
    {
        return $this->addColumn('macAddress', $column);
    }

    /*
     * Spatial Type Methods
     */

    /**
     * Create a GEOMETRY column (any type of spatial data)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function geometry(string $column): ColumnDefinition
    {
        return $this->addColumn('geometry', $column);
    }

    /**
     * Create a POINT column (single location in coordinate space)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function point(string $column): ColumnDefinition
    {
        return $this->addColumn('point', $column);
    }

    /**
     * Create a LINESTRING column (curve with linear interpolation between points)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function lineString(string $column): ColumnDefinition
    {
        return $this->addColumn('lineString', $column);
    }

    /**
     * Create a POLYGON column (polygonal surface)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function polygon(string $column): ColumnDefinition
    {
        return $this->addColumn('polygon', $column);
    }

    /**
     * Create a GEOMETRYCOLLECTION column (collection of geometry objects)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function geometryCollection(string $column): ColumnDefinition
    {
        return $this->addColumn('geometryCollection', $column);
    }

    /**
     * Create a MULTIPOINT column (collection of points)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function multiPoint(string $column): ColumnDefinition
    {
        return $this->addColumn('multiPoint', $column);
    }

    /**
     * Create a MULTILINESTRING column (collection of linestrings)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function multiLineString(string $column): ColumnDefinition
    {
        return $this->addColumn('multiLineString', $column);
    }

    /**
     * Create a MULTIPOLYGON column (collection of polygons)
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function multiPolygon(string $column): ColumnDefinition
    {
        return $this->addColumn('multiPolygon', $column);
    }
    /**
     * Create a big auto-incrementing unsigned integer column.
     *
     * @param string $column The column name
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
     * @param string $column The column name
     * @param int $length The maximum length of the string (default: 255)
     * @return ColumnDefinition
     */
    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $column, compact('length'));
    }

    /**
     * Create a medium text column.
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * Create a long text column.
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Create a boolean (TINYINT(1)) column.
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Create a JSON column.
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create an integer column.
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column);
    }

    /**
     * Create a timestamp column.
     *
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column);
    }

    /**
     * Add nullable creation and update timestamps to the table.
     * Creates 'created_at' and 'updated_at' columns.
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /**
     * Add a nullable deletion timestamp to the table.
     * Creates a 'deleted_at' column for soft deletes.
     */
    public function softDeletes(): void
    {
        $this->timestamp('deleted_at')->nullable();
    }

    /**
     * Create a foreign key column for the given model.
     *
     * @param string $model The model class name
     * @return ColumnDefinition
     */
    /**
     * Create a foreign key column for the given model with optional cascade options.
     *
     * @param string $model The model class name
     * @param bool $onDeleteCascade Whether to add ON DELETE CASCADE
     * @param bool $onUpdateCascade Whether to add ON UPDATE CASCADE
     * @return ColumnDefinition
     */
    public function foreignIdFor(
        string $model,
        bool $onDeleteCascade = false,
        bool $onUpdateCascade = false
    ): ColumnDefinition {
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
     * @param string $column The column name
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
     * @param string $type The column type
     * @param string $name The column name
     * @param array $parameters Additional column parameters
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
     * @param string $column The column name
     * @return ColumnDefinition
     */
    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedBigInteger', $column);
    }

    /**
     * Create a DOUBLE column (double-precision floating point number)
     *
     * @param string $column The column name
     * @param int|null $precision Total number of digits (optional)
     * @param int|null $scale Number of digits after decimal point (optional)
     * @return ColumnDefinition
     */
    public function double(string $column, int $precision = null, int $scale = null): ColumnDefinition
    {
        return $this->addColumn('double', $column, array_filter(compact('precision', 'scale')));
    }

    /**
     * Create a BLOB column (binary data, up to 65,535 bytes)
     * Alias for binary() but included for consistency with MySQL terminology
     *
     * @param string $column The column name
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
     * @return string The generated SQL
     * @throws \RuntimeException If no columns are defined
     */
    public function toSql(): string
    {
        $statements = [];

        // Convert all columns to their SQL representations
        $columns = array_filter(array_map(function ($column) {
            return $column->toSql();
        }, $this->columns));

        if (empty($columns)) {
            throw new \RuntimeException("No columns defined for table {$this->table}");
        }

        // Create the main CREATE TABLE statement
        $statements[] = "CREATE TABLE {$this->table} (" . implode(', ', $columns) . ") ENGINE={$this->engine}";

        // Add any additional commands (like foreign keys)
        foreach ($this->commands as $command) {
            if ($command instanceof ForeignKeyDefinition) {
                $statements[] = $command->toSql();
            }
        }

        return implode('; ', $statements);
    }

    /**
     * Convert the given string to snake_case.
     *
     * @param string $input The string to convert
     * @return string The snake_case version
     */
    protected function snakeCase(string $input): string
    {
        return str()->snake($input);
    }
}
