<?php

namespace Phaseolies\Database\Eloquent\Query;

trait InteractsWithTimeframe
{
    /**
     * Add a where date clause to the query.
     *
     * @param string $column
     * @param string $operator
     * @param string|null $value
     * @param string $boolean
     * @return self
     */
    public function whereDate(string $column, string $operator, ?string $value = null, string $boolean = 'AND'): self
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
     * Add a where month clause to the query.
     *
     * @param string $column
     * @param string $operator
     * @param string|null $value
     * @param string $boolean
     * @return self
     */
    public function whereMonth(string $column, string $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            "MONTH($column)",
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add a where year clause to the query.
     *
     * @param string $column
     * @param string $operator
     * @param string|null $value
     * @param string $boolean
     * @return self
     */
    public function whereYear(string $column, string $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            "YEAR($column)",
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add a where day clause to the query.
     *
     * @param string $column
     * @param string $operator
     * @param string|null $value
     * @param string $boolean
     * @return self
     */
    public function whereDay(string $column, string $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            "DAY($column)",
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add a where time clause to the query.
     *
     * @param string $column
     * @param string $operator
     * @param string|null $value
     * @param string $boolean
     * @return self
     */
    public function whereTime(string $column, string $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            "TIME($column)",
            $operator,
            $value
        ];

        return $this;
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
     * Filter records between two dates (inclusive).
     *
     * @param string $column
     * @param string|DateTime $start
     * @param string|DateTime $end
     * @param bool $includeTime Whether to include time in comparison
     * @return self
     */
    public function whereDateBetween(string $column, $start, $end, bool $includeTime = false): self
    {
        $start = $start instanceof \DateTime ? $start->format('Y-m-d H:i:s') : $start;
        $end = $end instanceof \DateTime ? $end->format('Y-m-d H:i:s') : $end;

        if (!$includeTime) {
            return $this->whereBetween("DATE($column)", [$start, $end]);
        }

        return $this->whereBetween($column, [$start, $end]);
    }
}