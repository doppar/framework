<?php

namespace Phaseolies\Database\Temporal;

use PDO;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Hooks\HookHandler;
use Phaseolies\Database\Temporal\TemporalBuilder;
use Phaseolies\Database\Temporal\TemporalManager;
use Phaseolies\Support\Collection;

trait InteractsWithTemporal
{
    /**
     * Register after_created / after_updated / after_deleted hooks that
     * automatically snapshot this model if it carries #[Temporal].
     *
     * Invoked once per class during the first model construction.
     *
     * @return void
     */
    protected function registerTemporalHooks(): void
    {
        if (!TemporalManager::isTemporal(static::class)) {
            return;
        }

        HookHandler::register(static::class, [
            'after_created' => static fn(Model $model) => TemporalManager::snapshot($model, 'created'),
            'after_updated' => static fn(Model $model) => TemporalManager::snapshot($model, 'updated'),
            'after_deleted' => static fn(Model $model) => TemporalManager::snapshot($model, 'deleted'),
        ]);
    }

    /**
     * Return a TemporalBuilder scoped to the given point in time.
     *
     * @param string $datetime
     * @return TemporalBuilder
     *
     * @throws \RuntimeException
     */
    public static function at(string $datetime): TemporalBuilder
    {
        if (!TemporalManager::isTemporal(static::class)) {
            throw new \RuntimeException(
                static::class . ' is not marked #[Temporal]. Add the attribute to enable time-travel queries.'
            );
        }

        $model = new static();

        return new TemporalBuilder(
            pdo:        $model->getConnection(),
            table:      $model->getTable(),
            modelClass: static::class,
            rowPerPage: $model->pageSize,
            datetime:   $datetime,
        );
    }

    /**
     * Return the complete audit history for this model instance
     *
     * @return Collection
     * @throws \RuntimeException
     */
    public function history(): Collection
    {
        $this->assertTemporalAndLoaded();

        $historyTable = $this->historyTable();
        $pdo          = $this->getConnection();

        $stmt = $pdo->prepare(
            "SELECT history_id, action, valid_from, snapshot, changed_cols, actor
             FROM   {$historyTable}
             WHERE  record_id = ?
             ORDER  BY valid_from ASC"
        );
        $stmt->execute([$this->getKey()]);

        $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $models = [];

        foreach ($rows as $row) {
            $data  = json_decode($row['snapshot'], true) ?? [];
            $entry = new static($data);
            $entry->setOriginalAttributes($data);

            // Attach metadata as virtual attributes (not in $creatable → safe)
            $entry->__history_id  = (int)   $row['history_id'];
            $entry->__action      =          $row['action'];
            $entry->__valid_from  =          $row['valid_from'];
            $entry->__changed_cols = $row['changed_cols']
                ? json_decode($row['changed_cols'], true)
                : null;
            $entry->__actor = $row['actor'] ?? null;

            $models[] = $entry;
        }

        return new Collection(static::class, $models);
    }

    /**
     * Compare the model's state at two points in time and return a structured diff.
     *
     * @param string $from
     * @param string $to
     * @return array|null
     */
    public function diff(string $from, string $to): ?array
    {
        $this->assertTemporalAndLoaded();

        $snapshotFrom = $this->snapshotAt($from);
        $snapshotTo   = $this->snapshotAt($to);

        if ($snapshotFrom === null || $snapshotTo === null) {
            return null;
        }

        $changes = [];
        $added   = [];
        $removed = [];

        foreach ($snapshotTo as $col => $newVal) {
            if (!array_key_exists($col, $snapshotFrom)) {
                $added[$col] = $newVal;
            } elseif ((string) $snapshotFrom[$col] !== (string) $newVal) {
                $changes[$col] = ['from' => $snapshotFrom[$col], 'to' => $newVal];
            }
        }

        foreach ($snapshotFrom as $col => $oldVal) {
            if (!array_key_exists($col, $snapshotTo)) {
                $removed[$col] = $oldVal;
            }
        }

        return [
            'from'    => $from,
            'to'      => $to,
            'changes' => $changes,
            'added'   => $added,
            'removed' => $removed,
        ];
    }

    /**
     * Return a new (unsaved) model instance populated with the state of this
     * record at the given point in time.
     *
     * @param string $datetime
     * @return static|null
     */
    public function rewindTo(string $datetime): ?static
    {
        $this->assertTemporalAndLoaded();

        $snapshot = $this->snapshotAt($datetime);

        if ($snapshot === null) {
            return null;
        }

        $rewound = new static($snapshot);

        // Compare against the instance's current state so save() persists only
        // the fields that actually change during the rewind/restore.
        $rewound->setOriginalAttributes($this->getAttributes());

        return $rewound;
    }

    /**
     * Restore this model to its state at the given datetime and immediately
     * persist the change.
     *
     * @param string $datetime
     * @return bool
     */
    public function restoreTo(string $datetime): bool
    {
        $rewound = $this->rewindTo($datetime);

        if ($rewound === null) {
            return false;
        }

        return $rewound->save();
    }

    /**
     * Whether this model class is marked #[Temporal].
     *
     * @return bool
     */
    public function isTemporal(): bool
    {
        return TemporalManager::isTemporal(static::class);
    }

    /**
     * Return the history table name for this model.
     *
     * @return string
     */
    public function historyTable(): string
    {
        return TemporalManager::historyTable($this->getTable(), static::class);
    }

    /**
     * Fetch the raw snapshot array for this record at the given datetime.
     *
     * @param string $datetime
     * @return array|null
     */
    private function snapshotAt(string $datetime): ?array
    {
        $historyTable = $this->historyTable();
        $pdo          = $this->getConnection();

        // Normalize to end-of-day when only a date is given
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($datetime))) {
            $datetime .= ' 23:59:59.999999';
        }

        $stmt = $pdo->prepare(
            "SELECT snapshot
             FROM   {$historyTable}
             WHERE  record_id  = ?
             AND    valid_from <= ?
             AND    action     != 'deleted'
             ORDER  BY valid_from DESC
             LIMIT  1"
        );
        $stmt->execute([$this->getKey(), $datetime]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (json_decode($row['snapshot'], true) ?? null) : null;
    }

    /**
     * Assert that the model is temporal and has a primary key loaded.
     *
     * @throws \RuntimeException
     */
    private function assertTemporalAndLoaded(): void
    {
        if (!TemporalManager::isTemporal(static::class)) {
            throw new \RuntimeException(
                static::class . ' is not marked #[Temporal].'
            );
        }

        if ($this->getKey() === null) {
            throw new \RuntimeException(
                'Cannot call temporal methods on an unsaved ' . static::class . ' instance.'
            );
        }
    }
}
