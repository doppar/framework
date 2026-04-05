<?php

namespace Phaseolies\Database\Temporal;

use PDO;
use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Support\Collection;

class TemporalBuilder extends Builder
{
    /**
     * The target point-in-time for history queries.
     *
     * @var string
     */
    protected string $datetime;

    /**
     * @param PDO $pdo
     * @param string $table
     * @param string $modelClass
     * @param int $rowPerPage 
     * @param string $datetime
     */
    public function __construct(PDO $pdo, string $table, string $modelClass, int $rowPerPage, string $datetime)
    {
        parent::__construct($pdo, $table, $modelClass, $rowPerPage);

        $this->datetime = $this->normalizeDateTime($datetime);
    }

    /**
     * Return a Collection of model instances as they existed at $this->datetime.
     *
     * Algorithm:
     *   For each distinct record_id in the history table, take the row with the
     *   largest valid_from that is still ≤ $datetime. Exclude records whose last
     *   action was 'deleted'. Hydrate each snapshot into a model instance.
     *   Apply any fluent where / orderBy / limit conditions in PHP.
     *
     * @return Collection
     */
    public function get(): Collection
    {
        $rows   = $this->fetchTemporalRows();
        $models = $this->hydrateSnapshots($rows);
        $models = $this->applyPhpConditions($models);
        $models = $this->applyPhpOrdering($models);
        $models = $this->applyPhpLimit($models);

        return new Collection($this->modelClass, $models);
    }

    /**
     * Return the first model instance as it existed at $this->datetime
     *
     * @return Model|null
     */
    public function first(): ?Model
    {
        $rows = $this->fetchTemporalRows();

        if (empty($rows)) {
            return null;
        }

        $models = $this->hydrateSnapshots($rows);
        $models = $this->applyPhpConditions($models);
        $models = $this->applyPhpOrdering($models);

        return $models[0] ?? null;
    }

    /**
     * Find a specific record by primary key as it existed at $this->datetime.
     *
     * @param int|string $id
     * @return Model|null
     */
    public function find($id): ?Model
    {
        $historyTable = TemporalManager::historyTable($this->table, $this->modelClass);
        $key          = (new $this->modelClass())->getKeyName();

        $sql = "
            SELECT snapshot
            FROM   {$historyTable}
            WHERE  record_id = ?
            AND    valid_from <= ?
            AND    action != 'deleted'
            ORDER  BY valid_from DESC
            LIMIT  1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id, $this->datetime]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrateOne($row['snapshot']);
    }

    /**
     * Fetch the latest-snapshot-per-record rows from the history table
     *
     * Uses a correlated subquery for broad driver compatibility (MySQL 5.7+,
     * PostgreSQL 9+, SQLite 3.7+).
     *
     * @return array
     */
    private function fetchTemporalRows(): array
    {
        $historyTable = TemporalManager::historyTable($this->table, $this->modelClass);

        $sql = "
            SELECT h1.snapshot, h1.record_id, h1.action
            FROM   {$historyTable} h1
            WHERE  h1.valid_from = (
                       SELECT MAX(h2.valid_from)
                       FROM   {$historyTable} h2
                       WHERE  h2.record_id  = h1.record_id
                       AND    h2.valid_from <= ?
                   )
            AND    h1.action != 'deleted'
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->datetime]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hydrate an array of raw history rows into model instances.
     *
     * @param array $rows
     * @return Model[]
     */
    private function hydrateSnapshots(array $rows): array
    {
        return array_map(
            fn(array $row) => $this->hydrateOne($row['snapshot']),
            $rows
        );
    }

    /**
     * Decode a JSON snapshot string and construct a model instance from it
     *
     * @param string $snapshotJson
     * @return Model
     */
    private function hydrateOne(string $snapshotJson): Model
    {
        $data  = json_decode($snapshotJson, true) ?? [];
        $model = new $this->modelClass($data);
        $model->setOriginalAttributes($data);

        return $model;
    }

    /**
     * Apply the builder's $conditions array as a PHP-level filter on an array
     * of already-hydrated model instances.
     *
     * @param Model[] $models
     * @return Model[]
     */
    private function applyPhpConditions(array $models): array
    {
        if (empty($this->conditions)) {
            return $models;
        }

        return array_values(array_filter($models, function (Model $model) {
            $pass = true;

            foreach ($this->conditions as $condition) {
                // Skip typed conditions (NESTED, EXISTS, RAW_WHERE, etc.)
                if (isset($condition['type'])) {
                    continue;
                }

                [$boolean, $field, $operator, $value] = array_pad($condition, 4, null);
                $extra = $condition[4] ?? null;

                // Strip table prefix if present (e.g. "contracts.status" → "status")
                $column = str_contains($field, '.') ? explode('.', $field, 2)[1] : $field;
                $actual = $model->{$column} ?? null;

                $result = $this->evaluateCondition($actual, $operator, $value, $extra);

                $pass = $boolean === 'OR' ? ($pass || $result) : ($pass && $result);
            }

            return $pass;
        }));
    }

    /**
     * Evaluate a single condition against an actual value.
     *
     * @param mixed $actual
     * @param string $operator
     * @param mixed $value
     * @param mixed $extra
     * @return bool
     */
    private function evaluateCondition(mixed $actual, string $operator, mixed $value, mixed $extra): bool
    {
        return match (strtoupper($operator)) {
            '='           => $actual == $value,
            '!='          => $actual != $value,
            '<>'          => $actual != $value,
            '>'           => $actual > $value,
            '>='          => $actual >= $value,
            '<'           => $actual < $value,
            '<='          => $actual <= $value,
            'IS NULL'     => $actual === null,
            'IS NOT NULL' => $actual !== null,
            'IN'          => in_array($actual, (array) $value, strict: false),
            'NOT IN'      => !in_array($actual, (array) $value, strict: false),
            'BETWEEN'     => $actual >= $value && $actual <= $extra,
            'NOT BETWEEN' => $actual < $value  || $actual > $extra,
            'LIKE', 'ILIKE' => $this->phpLike($actual, $value),
            'NOT LIKE'    => !$this->phpLike($actual, $value),
            default       => true,
        };
    }

    /**
     * Emulate SQL LIKE pattern matching in PHP.
     * Converts SQL wildcards (% and _) to regex equivalents.
     *
     * @param mixed  $actual
     * @param string $pattern
     * @return bool
     */
    private function phpLike(mixed $actual, string $pattern): bool
    {
        $regex = preg_quote($pattern, '/');
        $regex = str_replace(['%', '_'], ['.*', '.'], $regex);

        return (bool) preg_match('/^' . $regex . '$/iu', (string) $actual);
    }

    /**
     * Apply $this->orderBy clauses to an in-memory model array.
     *
     * @param Model[] $models
     * @return Model[]
     */
    private function applyPhpOrdering(array $models): array
    {
        if (empty($this->orderBy)) {
            return $models;
        }

        usort($models, function (Model $a, Model $b) {
            foreach ($this->orderBy as $order) {
                // Skip raw ORDER BY entries
                if (isset($order['type'])) {
                    continue;
                }

                [$field, $direction] = $order;
                $col = str_contains($field, '.') ? explode('.', $field, 2)[1] : $field;

                $va = $a->{$col} ?? null;
                $vb = $b->{$col} ?? null;

                $cmp = $va <=> $vb;

                if ($cmp !== 0) {
                    return strtoupper($direction) === 'DESC' ? -$cmp : $cmp;
                }
            }

            return 0;
        });

        return $models;
    }

    /**
     * Apply $this->limit and $this->offset to an in-memory model array.
     *
     * @param Model[] $models
     * @return Model[]
     */
    private function applyPhpLimit(array $models): array
    {
        $offset = $this->offset ?? 0;
        $limit  = $this->limit;

        if ($offset > 0) {
            $models = array_slice($models, $offset);
        }

        if ($limit !== null) {
            $models = array_slice($models, 0, $limit);
        }

        return $models;
    }

    /**
     * Normalize various human-friendly datetime strings into a sortable
     *
     * @param string $datetime
     * @return string
     */
    private function normalizeDateTime(string $datetime): string
    {
        $datetime = trim($datetime);

        // Date-only: append end-of-day
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datetime)) {
            return $datetime . ' 23:59:59.999999';
        }

        // Date + HH:MM only
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $datetime)) {
            return $datetime . ':59.999999';
        }

        // Date + HH:MM:SS only
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $datetime)) {
            return $datetime . '.999999';
        }

        return $datetime;
    }

    /**
     * Expose the resolved datetime for external use (e.g. in TemporalEntry).
     *
     * @return string
     */
    public function getDatetime(): string
    {
        return $this->datetime;
    }
}
