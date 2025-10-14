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
        $start = $this->formatDate($start, false);
        $end = $this->formatDate($end, false);

        $endDate = \DateTime::createFromFormat('Y-m-d', $end);
        if ($endDate) {
            $endDate->modify('+1 day');
            $adjustedEnd = $endDate->format('Y-m-d');
        } else {
            $adjustedEnd = $end;
        }

        return $this->whereBetween($this->date($column), [$start, $adjustedEnd]);
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
        $start = $this->formatDate($start, true);
        $end = $this->formatDate($end, true);

        return $this->whereBetween($column, [$start, $end]);
    }

    /**
     * Add a where date between condition with time handling
     *
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @param bool $includeTime
     * @return self
     */
    public function whereDateBetweenLegacy(string $column, $start, $end, bool $includeTime = false): self
    {
        if ($includeTime) {
            return $this->whereDateTimeBetween($column, $start, $end);
        }

        return $this->whereDateBetween($column, $start, $end);
    }


    /**
     * Get the proper operator for date range based on includeTime flag
     *
     * @param bool $includeTime
     * @return array [startOperator, endOperator]
     */
    public function getDateRangeOperators(bool $includeTime = false): array
    {
        if ($includeTime) {
            return ['>=', '<='];
        }

        // For date-only ranges, we typically want inclusive start and exclusive end
        // to cover the entire day
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql', 'pgsql', 'sqlite' => ['>=', '<'],
            default => ['>=', '<='],
        };
    }
}
