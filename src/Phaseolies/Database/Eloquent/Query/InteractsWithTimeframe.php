<?php

namespace Phaseolies\Database\Eloquent\Query;

trait InteractsWithTimeframe
{
    /**
     * Add a where date clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param string|null $value
     * @param string $boolean
     * @return self
     */
    public function whereDate(string $column, $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            "DATE($column)",
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add an OR where date clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param string|null $value
     * @param string $boolean
     * @return self
     */
    public function orWhereDate(string $column, $operator, ?string $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereDate($column, $operator, $value, 'OR');
    }

    /**
     * Add a where month clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param string|null $value
     * @param string $boolean
     * @return self
     */
    public function whereMonth(string $column, $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            $this->month($column),
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add an OR where month clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param string|null $value
     * @return self
     */
    public function orWhereMonth(string $column, $operator, ?string $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereMonth($column, $operator, $value, 'OR');
    }

    /**
     * Add a where year clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param string|null $value
     * @param string $boolean
     * @return self
     */
    public function whereYear(string $column, $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            $this->year($column),
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add an OR where year clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param string|null $value
     * @return self
     */
    public function orWhereYear(string $column, $operator, ?string $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereYear($column, $operator, $value, 'OR');
    }

    /**
     * Add a where day clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param string|null $value
     * @param string $boolean
     * @return self
     */
    public function whereDay(string $column, $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            $this->day($column),
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add an OR where day clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param string|null $value
     * @return self
     */
    public function orWhereDay(string $column, $operator, ?string $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereDay($column, $operator, $value, 'OR');
    }

    /**
     * Add a where time clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param string|null $value
     * @param string $boolean
     * @return self
     */
    public function whereTime(string $column, $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            $this->time($column),
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add an OR where time clause to the query.
     *
     * @param string $column
     * @param mixed $operator
     * @param string|null $value
     * @return self
     */
    public function orWhereTime(string $column, $operator, ?string $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereTime($column, $operator, $value, 'OR');
    }

    /**
     * Filter records for today's date.
     *
     * @param string $column
     * @return self
     */
    public function whereToday(string $column): self
    {
        return $this->whereDate($column, now()->toDateString());
    }

    /**
     * Filter records for yesterday's date.
     *
     * @param string $column
     * @return self
     */
    public function whereYesterday(string $column): self
    {
        return $this->whereDate($column, now()->subDay()->toDateString());
    }

    /**
     * Filter records for yesterday's date with OR condition.
     *
     * @param string $column
     * @return self
     */
    public function orWhereYesterday(string $column): self
    {
        return $this->orWhereDate($column, now()->subDay()->toDateString());
    }

    /**
     * Filter records for this month.
     *
     * @param string $column
     * @return self
     */
    public function whereThisMonth(string $column): self
    {
        return $this->whereMonth($column, now()->month);
    }

    /**
     * Filter records for last month.
     *
     * @param string $column
     * @return self
     */
    public function whereLastMonth(string $column): self
    {
        return $this->whereMonth($column, now()->subMonth()->month);
    }

    /**
     * Filter records for this year.
     *
     * @param string $column
     * @return self
     */
    public function whereThisYear(string $column): self
    {
        return $this->whereYear($column, now()->year);
    }

    /**
     * Filter records for last year.
     *
     * @param string $column
     * @return self
     */
    public function whereLastYear(string $column): self
    {
        return $this->whereYear($column, now()->subYear()->year);
    }

    /**
     * Add a where date between condition (date only, excludes time)
     *
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @return self
     */
    public function whereDateBetween(string $column, $start, $end): self
    {
        $start = $this->formatDate($start);
        $end = $this->formatDate($end);

        return $this->whereBetween($this->date($column), [$start, $end]);
    }

    /**
     * Add a where datetime between condition (includes time)
     *
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @return self
     */
    public function whereDateTimeBetween(string $column, $start, $end): self
    {
        $start = $this->formatDateTime($start, true);
        $end = $this->formatDateTime($end, true);

        return $this->whereBetween($column, [$start, $end]);
    }

    /**
     * Format date for database comparison
     *
     * @param mixed $date
     * @return string
     */
    public function formatDate($date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        if (is_string($date)) {
            return substr($date, 0, 10);
        }

        throw new \InvalidArgumentException('Invalid date provided');
    }

    /**
     * Format datetime for database comparison with proper time handling
     *
     * @param mixed $date
     * @param string $type 'start' or 'end' to indicate how to handle missing time
     * @return string
     */
    public function formatDateTime($date, string $type = 'start'): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d H:i:s');
        }

        if (is_string($date)) {
            // If it's already a full datetime string, return as-is
            if (strlen($date) > 10 && str_contains($date, ' ')) {
                return $date;
            }

            // If it's just a date string, add appropriate time
            if (strlen($date) === 10) {
                return $type === 'start' 
                    ? $date . ' 00:00:00'
                    : $date . ' 23:59:59';
            }

            return $date;
        }

        throw new \InvalidArgumentException('Invalid date provided');
    }
}
