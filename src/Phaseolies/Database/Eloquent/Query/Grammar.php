<?php

namespace Phaseolies\Database\Eloquent\Query;

trait Grammar
{
    /**
     * Get the current driver
     *
     * @return string
     */
    public function getDriver(): string
    {
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Get the random function as per driver
     *
     * @return string
     */
    public function rand(): string
    {
        $driver = $this->getDriver();

        $randomFunction = match ($driver) {
            'sqlite', 'pgsql' => 'RANDOM()',
            default           => 'RAND()',
        };

        return $randomFunction;
    }

    /**
     * Get the SQL query to retrieve table column information
     *
     * @param string $tableName
     * @return string
     */
    public function getTableColumnsSql(string $tableName): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'sqlite' => "PRAGMA table_info({$tableName})",
            'pgsql' => "SELECT column_name FROM information_schema.columns WHERE table_name = '{$tableName}'",
            default => "DESCRIBE {$tableName}",
        };
    }

    /**
     * Process the result to extract column names based on driver
     *
     * @param array $result
     * @return array
     */
    public function processTableColumnsResult(array $result): array
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'sqlite' => array_column($result, 'name'),
            'pgsql' => array_column($result, 'column_name'),
            'mysql' => $this->processMysqlTableColumns($result),
            default => $this->processMysqlTableColumns($result),
        };
    }

    /**
     * Process MySQL DESCRIBE result to extract column names
     *
     * @param array $result
     * @return array
     */
    protected function processMysqlTableColumns(array $result): array
    {
        $columns = [];
        foreach ($result as $row) {
            // MySQL DESCRIBE returns column name in 'Field' key
            if (isset($row['Field'])) {
                $columns[] = $row['Field'];
            }
            // Fallback for different MySQL drivers
            elseif (isset($row['field'])) {
                $columns[] = $row['field'];
            }
            // Ultimate fallback - try to get first element
            elseif (!empty($row) && is_array($row)) {
                $firstValue = reset($row);
                if (is_string($firstValue)) {
                    $columns[] = $firstValue;
                }
            }
        }

        return $columns;
    }

    /**
     * Get the SQL to list tables for the current database driver
     *
     * @return string
     */
    public function getTablesSql(): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => "SHOW TABLES",
            'pgsql' => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'",
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'",
            default => "SHOW TABLES"
        };
    }

    /**
     * Get JSON contains expression for the current database driver
     *
     * @param string $column
     * @param string $path
     * @return array [sql_expression, bindings]
     */
    public function jsonContains(string $column, string $path, $value): array
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => $this->mysqlJsonContains($column, $path, $value),
            'pgsql' => $this->pgsqlJsonContains($column, $path, $value),
            'sqlite' => $this->sqliteJsonContains($column, $path, $value),
            default => $this->mysqlJsonContains($column, $path, $value),
        };
    }

    /**
     * MySQL JSON_CONTAINS implementation
     */
    protected function mysqlJsonContains(string $column, string $path, $value): array
    {
        $jsonValue = is_bool($value)
            ? ($value ? 'true' : 'false')
            : json_encode($value);

        return [
            "JSON_CONTAINS(`{$column}`, CAST(? AS JSON), '{$path}')",
            [$jsonValue]
        ];
    }

    /**
     * PostgreSQL JSON contains implementation
     *
     * @param string $column
     * @param string $path
     * @param mixed $value
     * @return array
     */
    protected function pgsqlJsonContains(string $column, string $path, $value): array
    {
        if (is_bool($value)) {
            // For boolean values, use @> operator with JSONB
            $jsonValue = $value ? 'true' : 'false';
            return [
                "{$column}::jsonb @> ?::jsonb",
                ["{\"{$this->getLastPathSegment($path)}\": {$jsonValue}}"]
            ];
        } else {
            // For other values
            $jsonValue = json_encode([$this->getLastPathSegment($path) => $value]);
            return [
                "{$column}::jsonb @> ?::jsonb",
                [$jsonValue]
            ];
        }
    }

    /**
     * SQLite JSON contains implementation
     *
     * @param string $column
     * @param string $path
     * @param mixed $value
     * @return array
     */
    protected function sqliteJsonContains(string $column, string $path, $value): array
    {
        if (is_bool($value)) {
            // For boolean values, compare directly
            return [
                "json_extract({$column}, ?) = ?",
                [$path, $value ? 1 : 0]
            ];
        } elseif (is_string($value)) {
            // For string values, don't JSON encode - use the raw string
            return [
                "json_extract({$column}, ?) = ?",
                [$path, $value]
            ];
        } else {
            // For other values (numbers, null), use as-is
            return [
                "json_extract({$column}, ?) = ?",
                [$path, $value]
            ];
        }
    }

    /**
     * Extract the last segment from JSON path
     *
     * @param string $path
     * @return string
     */
    protected function getLastPathSegment(string $path): string
    {
        $parts = explode('.', $path);

        return end($parts);
    }

    /**
     * Get the month extraction function for the current database driver
     *
     * @param string $column
     * @return string
     */
    public function month(string $column): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => "MONTH({$column})",
            'pgsql' => "EXTRACT(MONTH FROM {$column})",
            'sqlite' => "CAST(strftime('%m', {$column}) AS INTEGER)",
            default => "MONTH({$column})",
        };
    }

    /**
     * Get the year extraction function for the current database driver
     *
     * @param string $column
     * @return string
     */
    public function year(string $column): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => "YEAR({$column})",
            'pgsql' => "EXTRACT(YEAR FROM {$column})",
            'sqlite' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
            default => "YEAR({$column})",
        };
    }

    /**
     * Get the day extraction function for the current database driver
     *
     * @param string $column
     * @return string
     */
    public function day(string $column): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => "DAY({$column})",
            'pgsql' => "EXTRACT(DAY FROM {$column})",
            'sqlite' => "CAST(strftime('%d', {$column}) AS INTEGER)",
            default => "DAY({$column})",
        };
    }

    /**
     * Get the date part extraction function for the current database driver
     *
     * @param string $column
     * @param string $part
     * @return string
     */
    public function datePart(string $column, string $part): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => match ($part) {
                'month' => "MONTH({$column})",
                'year' => "YEAR({$column})",
                'day' => "DAY({$column})",
                'quarter' => "QUARTER({$column})",
                'week' => "WEEK({$column})",
                'dayofweek' => "DAYOFWEEK({$column})",
                default => "{$part}({$column})"
            },
            'pgsql' => match ($part) {
                'month', 'year', 'day', 'quarter', 'week', 'dow' => "EXTRACT({$part} FROM {$column})",
                'dayofweek' => "EXTRACT(DOW FROM {$column})",
                default => "EXTRACT({$part} FROM {$column})"
            },
            'sqlite' => match ($part) {
                'month' => "CAST(strftime('%m', {$column}) AS INTEGER)",
                'year' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
                'day' => "CAST(strftime('%d', {$column}) AS INTEGER)",
                'quarter' => "((CAST(strftime('%m', {$column}) AS INTEGER) - 1) / 3 + 1)",
                'week' => "CAST(strftime('%W', {$column}) AS INTEGER)",
                'dayofweek' => "CAST(strftime('%w', {$column}) AS INTEGER)",
                default => "strftime('%{$part}', {$column})"
            },
            default => "{$part}({$column})"
        };
    }

    /**
     * Get the time extraction function for the current database driver
     *
     * @param string $column
     * @return string
     */
    public function time(string $column): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => "TIME({$column})",
            'pgsql' => "{$column}::time",
            'sqlite' => "time({$column})",
            default => "TIME({$column})",
        };
    }

    /**
     * Get the hour extraction function for the current database driver
     *
     * @param string $column
     * @return string
     */
    public function hour(string $column): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => "HOUR({$column})",
            'pgsql' => "EXTRACT(HOUR FROM {$column})",
            'sqlite' => "CAST(strftime('%H', {$column}) AS INTEGER)",
            default => "HOUR({$column})",
        };
    }

    /**
     * Get the minute extraction function for the current database driver
     *
     * @param string $column
     * @return string
     */
    public function minute(string $column): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => "MINUTE({$column})",
            'pgsql' => "EXTRACT(MINUTE FROM {$column})",
            'sqlite' => "CAST(strftime('%M', {$column}) AS INTEGER)",
            default => "MINUTE({$column})",
        };
    }

    /**
     * Get the second extraction function for the current database driver
     *
     * @param string $column
     * @return string
     */
    public function second(string $column): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => "SECOND({$column})",
            'pgsql' => "EXTRACT(SECOND FROM {$column})",
            'sqlite' => "CAST(strftime('%S', {$column}) AS INTEGER)",
            default => "SECOND({$column})",
        };
    }

    /**
     * Get the time part extraction function for the current database driver
     *
     * @param string $column
     * @param string $part
     * @return string
     */
    public function timePart(string $column, string $part): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => match ($part) {
                'hour' => "HOUR({$column})",
                'minute' => "MINUTE({$column})",
                'second' => "SECOND({$column})",
                'time' => "TIME({$column})",
                default => "{$part}({$column})"
            },
            'pgsql' => match ($part) {
                'hour', 'minute', 'second' => "EXTRACT({$part} FROM {$column})",
                'time' => "{$column}::time",
                default => "EXTRACT({$part} FROM {$column})"
            },
            'sqlite' => match ($part) {
                'hour' => "CAST(strftime('%H', {$column}) AS INTEGER)",
                'minute' => "CAST(strftime('%M', {$column}) AS INTEGER)",
                'second' => "CAST(strftime('%S', {$column}) AS INTEGER)",
                'time' => "time({$column})",
                default => "strftime('%{$part}', {$column})"
            },
            default => "{$part}({$column})"
        };
    }

    /**
     * Get the date extraction function for the current database driver
     *
     * @param string $column
     * @return string
     */
    public function date(string $column): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => "DATE({$column})",
            'pgsql' => "DATE({$column})",
            'sqlite' => "date({$column})",
            default => "DATE({$column})",
        };
    }

    /**
     * Format date for database comparison
     *
     * @param mixed $date
     * @param bool $includeTime
     * @return string
     */
    public function formatDate($date, bool $includeTime = false): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $includeTime
                ? $date->format('Y-m-d H:i:s')
                : $date->format('Y-m-d');
        }

        if (is_string($date)) {
            if (!$includeTime && strlen($date) > 10) {
                return substr($date, 0, 10);
            }
            return $date;
        }

        throw new \InvalidArgumentException('Invalid date provided');
    }

    /**
     * Get the standard deviation function for the current database driver
     *
     * @param string $column
     * @return string
     */
    public function getStandardDeviation(string $column): string
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql', 'pgsql' => "STDDEV({$column})",
            'sqlite' => "SQRT(AVG({$column} * {$column}) - AVG({$column}) * AVG({$column}))",
            default => "SQRT(AVG({$column} * {$column}) - AVG({$column}) * AVG({$column}))",
        };
    }
}
