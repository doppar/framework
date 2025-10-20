<?php

namespace Phaseolies\Database\Eloquent\Query;

trait Debuggable
{
    /**
     * @var array Query execution metrics storage
     */
    protected array $queryMetrics = [];

    /**
     * Get all collected query metrics
     *
     * @return array
     */
    public function getQueryMetrics(): array
    {
        return $this->queryMetrics;
    }

    /**
     * Clear collected query metrics
     *
     * @return void
     */
    public function clearQueryMetrics(): void
    {
        $this->queryMetrics = [];
    }

    /**
     * Get the last executed query metrics
     *
     * @return array|null
     */
    public function getLastQueryMetrics(): ?array
    {
        return end($this->queryMetrics) ?: null;
    }

    /**
     * Dump the SQL query that would be executed
     *
     * @param bool $withBindings
     * @return self
     */
    public function dumpSql(bool $withBindings = false): self
    {
        $sql = $this->toSql();

        if ($withBindings) {
            $bindings = $this->getBindings();
            foreach ($bindings as $binding) {
                $sql = preg_replace('/\?/', is_numeric($binding) ? $binding : "'{$binding}'", $sql, 1);
            }
        }

        dump($sql);

        return $this;
    }

    /**
     * dd the SQL query that would be executed
     *
     * @param bool $withBindings
     * @return never
     */
    public function ddSql(bool $withBindings = false): never
    {
        $this->dumpSql($withBindings);

        exit(1);
    }

    /**
     * Get the bindings for the current query
     *
     * @return array
     */
    protected function getBindings(): array
    {
        $bindings = [];

        foreach ($this->conditions as $condition) {
            if (isset($condition['type'])) {
                if (in_array($condition['type'], ['RAW_SELECT', 'RAW_GROUP_BY', 'RAW_WHERE'])) {
                    if (!empty($condition['bindings'])) {
                        $bindings = array_merge($bindings, $condition['bindings']);
                    }
                    continue;
                }

                if (in_array($condition['type'], ['EXISTS', 'NOT EXISTS'])) {
                    continue;
                }
            }

            if (isset($condition[2])) {
                if (in_array($condition[2], ['IS NULL', 'IS NOT NULL'])) {
                    continue;
                }

                if (in_array($condition[2], ['BETWEEN', 'NOT BETWEEN'])) {
                    $bindings[] = $condition[3];
                    $bindings[] = $condition[4];
                    continue;
                }

                if ($condition[2] === 'IN') {
                    $bindings = array_merge($bindings, $condition[3]);
                    continue;
                }
            }

            if (isset($condition[3])) {
                $bindings[] = $condition[3];
            }
        }

        return $bindings;
    }

    /**
     * Debug the current query without executing it
     *
     * @return array
     */
    public function debug(): array
    {
        return [
            'sql' => $this->toSql(),
            'bindings' => $this->getBindings(),
            'select' => $this->fields,
            'where' => $this->conditions,
            'order' => $this->orderBy,
            'group' => $this->groupBy,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'joins' => $this->joins,
            'eager_load' => $this->eagerLoad,
        ];
    }

    /**
     * Get the estimated query execution plan (EXPLAIN)
     *
     * @return array
     */
    public function explain(): array
    {
        $explainSql = 'EXPLAIN ' . $this->toSql();
        $stmt = $this->pdo->prepare($explainSql);
        $this->bindValues($stmt);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
