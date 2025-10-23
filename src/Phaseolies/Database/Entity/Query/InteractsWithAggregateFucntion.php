<?php

namespace Phaseolies\Database\Entity\Query;

trait InteractsWithAggregateFucntion
{
    /**
     * Run a SQL aggregate function without eager loading.
     *
     * @param string $expression The aggregate SQL expression (e.g. SUM(column))
     * @return float
     */
    protected function runAggregate(string $expression): float
    {
        $query = $this->withoutEagerLoad();

        $query->select(["{$expression} as aggregate"]);

        $result = $query->first();

        return (float) ($result->aggregate ?? 0);
    }

    /**
     * Retrieve the sum of the values of a given column
     *
     * @param string $column
     * @return float
     */
    public function sum(string $column): float
    {
        return $this->runAggregate("SUM({$column})");
    }

    /**
     * Retrieve the average of the values of a given column
     *
     * @param string $column
     * @return float
     */
    public function avg(string $column): float
    {
        return $this->runAggregate("AVG({$column})");
    }

    /**
     * Retrieve the minimum value of a given column
     *
     * @param string $column
     * @return mixed
     */
    public function min(string $column)
    {
        $query = $this->withoutEagerLoad();
        $query->select(["MIN({$column}) as aggregate"]);
        $result = $query->first();

        return $result->aggregate;
    }

    /**
     * Retrieve the maximum value of a given column
     *
     * @param string $column
     * @return mixed
     */
    public function max(string $column)
    {
        $query = $this->withoutEagerLoad();
        $query->select(["MAX({$column}) as aggregate"]);
        $result = $query->first();

        return $result->aggregate;
    }

    /**
     * Retrieve the variance of a column
     *
     * @param string $column
     * @return float
     */
    public function variance(string $column): float
    {
        $expression = $this->getVarianceExpression($column);

        return $this->runAggregate($expression);
    }

    /**
     * Retrieve the standard deviation of a column
     *
     * @param string $column
     * @return float
     */
    public function stdDev(string $column): float
    {
        return $this->shouldComputeStdDevInPhp()
            ? $this->stdDevPhp($column)
            : $this->stdDevSql($column);
    }

    /**
     * Compute standard deviation by fetching variance via SQL and taking sqrt() in PHP
     *
     * @param string $column
     * @return float
     */
    protected function stdDevPhp(string $column): float
    {
        $query = $this->withoutEagerLoad();

        $varianceExpression = $this->getVarianceExpression($column);
        $query->select(["{$varianceExpression} as aggregate"]);

        $result = $query->first();
        $variance = $result->aggregate ?? 0;

        if ($variance === null || !is_numeric($variance) || $variance < 0) {
            return 0.0;
        }

        return (float) sqrt((float) $variance);
    }

    /**
     * Compute standard deviation using a SQL expression provided by Grammar
     *
     * @param string $column
     * @return float
     */
    protected function stdDevSql(string $column): float
    {
        $query = $this->withoutEagerLoad();

        $stdDevExpression = $this->getStandardDeviation($column);
        $query->select(["{$stdDevExpression} as aggregate"]);

        $result = $query->first();
        $value = $result->aggregate ?? 0;

        if ($value === null || !is_numeric($value) || $value < 0) {
            return 0.0;
        }

        return (float) $value;
    }

    /**
     * Retrieve the grouped concatenation of values
     *
     * @param string $column
     * @param string $separator Defaults to ','
     * @return string
     */
    public function groupConcat(string $column, string $separator = ','): string
    {
        $expression = $this->getGroupConcatExpression($column, $separator);

        return (string) $this->runAggregate($expression);
    }
}
