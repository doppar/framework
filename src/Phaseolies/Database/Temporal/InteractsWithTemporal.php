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
     * Register after_created / after_updated / after_deleted / after_restored hooks that
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
            'after_restored' => static fn(Model $model) => TemporalManager::snapshot($model, 'restored'),
        ]);
    }

    /**
     * Return a TemporalBuilder scoped to the given point in time (and branch).
     *
     * @param string $datetime
     * @param string $branch
     * @return TemporalBuilder
     *
     * @throws \RuntimeException
     */
    public static function at(string $datetime, string $branch = 'main'): TemporalBuilder
    {
        if (!TemporalManager::isTemporal(static::class)) {
            throw new \RuntimeException(
                static::class . ' is not marked #[Temporal]. Add the attribute to enable time-travel queries.'
            );
        }

        TemporalManager::assertBranchSupported(static::class, $branch);

        $model = new static();

        return new TemporalBuilder(
            pdo:        $model->getConnection(),
            table:      $model->getTable(),
            modelClass: static::class,
            rowPerPage: $model->pageSize,
            datetime:   $datetime,
            branch:     $branch,
        );
    }

    /**
     * Fork a new branch from this record's main timeline as it existed at
     * the given point in time. Its timeline starts when the fork is created,
     * so earlier queries on that branch return no snapshot.
     *
     * @param string $name
     * @param string $fromDatetime
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function branch(string $name, string $fromDatetime): bool
    {
        $this->assertTemporalAndLoaded();

        if (!TemporalManager::isBranchable(static::class)) {
            throw new \RuntimeException(
                static::class . ' does not support branches. Add #[Temporal(branches: true)] to enable them.'
            );
        }

        TemporalManager::validateBranchName($name);
        if ($name === 'main') {
            throw new \InvalidArgumentException('Branch name must be non-empty and cannot be "main".');
        }

        $snapshot = $this->snapshotAt($fromDatetime, 'main');

        if ($snapshot === null) {
            return false;
        }

        TemporalManager::snapshotBranch($this, $snapshot, $name);

        return true;
    }

    /** Append this in-memory proposed state as a new version on an existing branch. */
    public function saveToBranch(string $branch): bool
    {
        $this->assertTemporalAndLoaded();
        TemporalManager::assertBranchSupported(static::class, $branch);
        if ($branch === 'main') {
            throw new \InvalidArgumentException('saveToBranch() requires a named branch.');
        }
        TemporalManager::snapshotBranchVersion($this, $branch);
        return true;
    }

    /** Compare the current main state with the latest state on a named branch. */
    public function diffAgainstBranch(string $branch): ?array
    {
        $this->assertTemporalAndLoaded();
        TemporalManager::assertBranchSupported(static::class, $branch);
        if ($branch === 'main') {
            throw new \InvalidArgumentException('A named branch is required.');
        }
        $now = \Carbon\Carbon::now()->format('Y-m-d H:i:s.u');
        $main = $this->snapshotAt($now, 'main');
        $proposed = $this->snapshotAt($now, $branch);
        if ($main === null || $proposed === null) {
            return null;
        }
        $probe = new static();
        $diff = $this->formatDiff($main, $proposed, $branch, $now, $now, $probe);
        $visibleMain = $this->visibleSnapshot($main, $probe);
        $visibleBranch = $this->visibleSnapshot($proposed, $probe);

        return [
            'at'      => $now,
            'main'    => $visibleMain,
            'branch'  => ['name' => $branch, 'snapshot' => $visibleBranch],
            'changes' => $diff['changes'],
            'added'   => $diff['added'],
            'removed' => $diff['removed'],
        ];
    }

    /**
     * Return the complete audit history for this model instance on the given branch.
     *
     * @param string|null $branch Pass null to include every branch.
     * @return Collection
     * @throws \RuntimeException
     */
    public function history(?string $branch = 'main'): Collection
    {
        $this->assertTemporalAndLoaded();

        $historyTable = $this->historyTable();
        $pdo          = $this->getConnection();
        $driver       = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $hasActor  = TemporalManager::hasActorColumn($pdo, $historyTable, $driver);
        $hasBranch = TemporalManager::isBranchable(static::class);
        if ($branch !== null) {
            TemporalManager::assertBranchSupported(static::class, $branch);
        }
        if ($hasBranch) {
            TemporalManager::assertBranchColumn($pdo, $historyTable, $driver);
        }

        $columns = 'history_id, action, valid_from, snapshot, changed_cols'
            . ($hasActor ? ', actor' : '')
            . ($hasBranch ? ', branch' : '');

        $sql = "SELECT {$columns}
                FROM   {$historyTable}
                WHERE  record_id = ?"
            . ($hasBranch && $branch !== null ? ' AND branch = ?' : '')
            . ' ORDER  BY valid_from ASC, history_id ASC';

        $params = [$this->getKey()];
        if ($hasBranch && $branch !== null) {
            $params[] = $branch;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

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
            $entry->__actor  = $row['actor'] ?? null;
            $entry->__branch = $row['branch'] ?? 'main';

            $models[] = $entry;
        }

        return new Collection(static::class, $models);
    }

    /**
     * Compare the model's state at two points in time on the given branch and
     * return a structured diff. Values are run through the model's casts and
     * attributes marked unexposable (hidden) are excluded from the result.
     *
     * @param string $from
     * @param string $to
     * @param string $branch
     * @return array|null
     *
     * @throws \RuntimeException
     */
    public function diff(string $from, string $to, string $branch = 'main'): ?array
    {
        $this->assertTemporalAndLoaded();
        TemporalManager::assertBranchSupported(static::class, $branch);

        $snapshotFrom = $this->snapshotAt($from, $branch);
        $snapshotTo   = $this->snapshotAt($to, $branch);

        if ($snapshotFrom === null || $snapshotTo === null) {
            return null;
        }

        return $this->formatDiff($snapshotFrom, $snapshotTo, $branch, $from, $to, new static());
    }

    private function formatDiff(array $snapshotFrom, array $snapshotTo, string $branch, string $from, string $to, Model $probe): array
    {
        $visibleFrom = $this->visibleSnapshot($snapshotFrom, $probe);
        $visibleTo   = $this->visibleSnapshot($snapshotTo, $probe);

        $changes = [];
        $added   = [];
        $removed = [];

        foreach ($visibleTo as $col => $newVal) {
            if (!array_key_exists($col, $visibleFrom)) {
                $added[$col] = $newVal;
            } elseif (self::snapshotValuesDiffer($visibleFrom[$col], $newVal)) {
                $changes[$col] = ['from' => $visibleFrom[$col], 'to' => $newVal];
            }
        }

        foreach ($visibleFrom as $col => $oldVal) {
            if (!array_key_exists($col, $visibleTo)) {
                $removed[$col] = $oldVal;
            }
        }

        return [
            'from'    => $from,
            'to'      => $to,
            'branch'  => $branch,
            'changes' => $changes,
            'added'   => $added,
            'removed' => $removed,
        ];
    }

    /**
     * Apply this model's casts and unexposable (hidden) attribute filtering
     * to a raw stored snapshot, for display/diffing purposes only.
     *
     * @param array $snapshot
     * @return array
     */
    private function visibleSnapshot(array $snapshot, Model $probe): array
    {
        $visible = [];

        foreach ($snapshot as $key => $value) {
            if (in_array($key, $probe->unexposable, true)) {
                continue;
            }

            $visible[$key] = $probe->castForGet($key, $value);
        }

        return $visible;
    }

    /**
     * Return a new (unsaved) model instance populated with the state of this
     * record at the given point in time on the given branch.
     *
     * @param string $datetime
     * @param string $branch
     * @return static|null
     *
     * @throws \RuntimeException
     */
    public function rewindTo(string $datetime, string $branch = 'main'): ?static
    {
        $this->assertTemporalAndLoaded();
        TemporalManager::assertBranchSupported(static::class, $branch);

        $snapshot = $this->snapshotAt($datetime, $branch);

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
     * Restore this model to its state at the given datetime on the given
     * branch and immediately persist the change. This always creates a new
     * version through the normal save()/snapshot hooks; it never rewrites
     * existing history, and restoring from a branch other than "main"
     * requires the model to be configured for branches.
     *
     * Returns false when there is nothing to restore: no snapshot exists at
     * that point, or the record had been deleted by then. The same rule makes
     * rewindTo() return null for that point.
     *
     * @param string $datetime
     * @param string $branch
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function restoreTo(string $datetime, string $branch = 'main'): bool
    {
        $this->assertTemporalAndLoaded();
        $rewound = $this->rewindTo($datetime, $branch);

        if ($rewound === null) {
            return false;
        }

        if (!$rewound->save()) {
            return false;
        }

        // save() only writes creatable columns, so carry the snapshot's
        // soft delete state over explicitly when it differs from now.
        if ($this->usesSoftDeletes() && $rewound->trashed() !== $this->trashed()) {
            return $rewound->trashed() ? $rewound->delete() : $rewound->restore();
        }

        return true;
    }

    /**
     * Restore this record to a specific history entry and persist it as a new
     * version through the normal save()/snapshot hooks.
     *
     * Returns false when the id is unknown or belongs to another record, and
     * throws when the entry is a "deleted" snapshot, since restoring an
     * explicitly chosen deletion is always a mistake.
     *
     * @param int $historyId
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function restoreVersion(int $historyId): bool
    {
        $this->assertTemporalAndLoaded();
        $historyTable = $this->historyTable();
        $pdo = $this->getConnection();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $hasBranch = TemporalManager::isBranchable(static::class);
        if ($hasBranch) {
            TemporalManager::assertBranchColumn($pdo, $historyTable, $driver);
        }

        $stmt = $pdo->prepare(
            "SELECT snapshot, action FROM {$historyTable} WHERE history_id = ? AND record_id = ?"
        );
        $stmt->execute([$historyId, $this->getKey()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        if ($row['action'] === 'deleted') {
            throw new \RuntimeException('A deleted temporal version cannot be restored.');
        }
        $snapshot = json_decode($row['snapshot'], true) ?? [];
        $restored = new static($snapshot);
        $restored->setOriginalAttributes($this->getAttributes());
        if (!$restored->save()) {
            return false;
        }
        return true;
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
     * Fetch the raw snapshot array for this record at the given datetime on
     * the given branch.
     *
     * @param string $datetime
     * @param string $branch
     * @return array|null
     */
    private function snapshotAt(string $datetime, string $branch = 'main'): ?array
    {
        $historyTable = $this->historyTable();
        $pdo          = $this->getConnection();
        $driver       = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $hasBranch = TemporalManager::isBranchable(static::class);
        if ($hasBranch) {
            TemporalManager::assertBranchColumn($pdo, $historyTable, $driver);
        }

        $datetime = $this->normalizeTemporalDateTime($datetime);

        $sql = "SELECT snapshot, action
                FROM   {$historyTable}
                WHERE  record_id  = ?
                AND    valid_from <= ?"
            . ($hasBranch ? ' AND branch = ?' : '')
            . ' ORDER  BY valid_from DESC, history_id DESC
                LIMIT  1';

        $params = [$this->getKey(), $datetime];
        if ($hasBranch) {
            $params[] = $branch;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // The latest row decides: if the record was deleted by then, it did not exist.
        if (!$row || $row['action'] === 'deleted') {
            return null;
        }

        return json_decode($row['snapshot'], true) ?? null;
    }

    /**
     * Determine whether two snapshot values are different
     *
     * @param mixed $from
     * @param mixed $to
     * @return bool
     */
    private static function snapshotValuesDiffer(mixed $from, mixed $to): bool
    {
        if (is_array($from) || is_array($to)) {
            if (!is_array($from) || !is_array($to) || count($from) !== count($to)) {
                return true;
            }

            foreach ($from as $key => $value) {
                if (!array_key_exists($key, $to) || self::snapshotValuesDiffer($value, $to[$key])) {
                    return true;
                }
            }

            return false;
        }

        if ($from === null || $to === null) {
            return $from !== $to;
        }

        if (is_object($from) || is_object($to)) {
            return $from != $to;
        }

        return self::snapshotScalar($from) !== self::snapshotScalar($to);
    }

    /**
     * @param bool|int|float|string $value
     * @return string
     */
    private static function snapshotScalar(mixed $value): string
    {
        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    private function normalizeTemporalDateTime(string $datetime): string
    {
        $datetime = trim($datetime);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datetime)) {
            return $datetime . ' 23:59:59.999999';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $datetime)) {
            return $datetime . ':59.999999';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $datetime)) {
            return $datetime . '.999999';
        }
        return $datetime;
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
