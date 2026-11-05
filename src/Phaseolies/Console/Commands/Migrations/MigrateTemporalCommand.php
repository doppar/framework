<?php

namespace Phaseolies\Console\Commands\Migrations;

use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Phaseolies\Console\Schedule\Command;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Temporal\Attributes\Temporal;
use Phaseolies\Database\Temporal\TemporalManager;

class MigrateTemporalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'migrate:temporal {--connection= : Database connection to use} {--show : Dry-run — print SQL without executing} {--path= : Path to the Models directory}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create history tables for all #[Temporal] models';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $connection = $this->option('connection') ?: null;
            $dryRun     = (bool) $this->option('show');
            $modelsPath = $this->option('path') ?: base_path('src/Models');

            $connectionLabel = $connection ?: 'per-model';

            $this->newLine();
            $this->line("  <fg=yellow;options=bold>Temporal migration</> <fg=gray>connection: {$connectionLabel}" . ($dryRun ? ' · dry run' : '') . '</>');
            $this->newLine();

            $classes = $this->discoverTemporalModels($modelsPath);

            if (empty($classes)) {
                $this->displayInfo('No #[Temporal] models found in ' . $modelsPath);
                return Command::SUCCESS;
            }

            $entries = [];

            foreach ($classes as $class) {
                $model     = new $class();
                $baseTable = $model->getTable();

                $entries[] = [
                    'class'      => $class,
                    'base'       => $baseTable,
                    'history'    => TemporalManager::historyTable($baseTable, $class),
                    'connection' => $this->resolveConnectionForModel($model, $connection),
                ];
            }

            $classWidth      = max(array_map(fn ($e) => strlen($e['class']), $entries));
            $connectionWidth = max(array_map(fn ($e) => strlen($e['connection']), $entries));
            $tableWidth      = max(array_map(fn ($e) => strlen($e['base'] . ' → ' . $e['history']), $entries));

            $created = 0;
            $skipped = 0;

            foreach ($entries as $entry) {
                $class           = $entry['class'];
                $historyTable    = $entry['history'];
                $modelConnection = $entry['connection'];
                $attr            = TemporalManager::getAttribute($class);

                try {
                    $pdo    = Database::getPdoInstance($modelConnection);
                    $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
                } catch (\Throwable $e) {
                    $this->displayError(
                        "Failed to connect to database [{$modelConnection}] for {$class}: " . $e->getMessage()
                    );
                    return Command::FAILURE;
                }

                $row = fn (string $icon, string $color, string $status) => $this->line(
                    "  <fg={$color}>{$icon}</> <fg=white>" . str_pad($class, $classWidth) . '</>  '
                    . '<fg=gray>' . str_pad($entry['base'] . ' → ' . $historyTable, $tableWidth) . '</>  '
                    . '<fg=cyan>' . str_pad($modelConnection, $connectionWidth) . "</>  <fg={$color}>{$status}</>"
                );

                if (!$dryRun && $this->tableExists($pdo, $driver, $historyTable)) {
                    // Table exists — check if we need to add the actor and/or branch columns
                    $addedColumns = [];
                    $addedIndexes = [];

                    if ($attr?->trackActor && !$this->columnExists($pdo, $driver, $historyTable, 'actor')) {
                        $pdo->exec($this->addActorColumnSql($driver, $historyTable));
                        $addedColumns[] = 'actor';
                    }

                    if ($attr !== null && $attr->branches && !$this->columnExists($pdo, $driver, $historyTable, 'branch')) {
                        $pdo->exec($this->addBranchColumnSql($driver, $historyTable));
                        $addedColumns[] = 'branch';
                    }

                    $branchIndex = $driver === 'mysql' ? 'idx_branch' : 'idx_' . $historyTable . '_branch';
                    if ($attr !== null && $attr->branches && !$this->indexExists($pdo, $driver, $historyTable, $branchIndex)) {
                        $pdo->exec($this->createBranchIndexSql($driver, $historyTable));
                        $addedIndexes[] = 'branch';
                    }

                    if (!empty($addedColumns) || !empty($addedIndexes)) {
                        TemporalManager::resetCache();
                        $status = [];
                        if (!empty($addedColumns)) {
                            $status[] = implode(', ', $addedColumns) . ' column' . (count($addedColumns) > 1 ? 's' : '') . ' added';
                        }
                        if (!empty($addedIndexes)) {
                            $status[] = implode(', ', $addedIndexes) . ' index added';
                        }
                        $row('✓', 'green', implode('; ', $status));
                        $created++;
                    } else {
                        $row('•', 'yellow', 'already exists');
                        $skipped++;
                    }
                    continue;
                }

                $statements = TemporalManager::createHistoryTableSql(
                    historyTable:  $historyTable,
                    driver:        $driver,
                    trackActor:    $attr?->trackActor ?? false,
                    trackBranches: $attr !== null && $attr->branches,
                );

                if ($dryRun) {
                    $row('›', 'yellow', 'would create');
                    foreach ($statements as $sql) {
                        $this->line('      <fg=gray>' . preg_replace('/\s+/', ' ', trim($sql)) . '</>');
                    }
                } else {
                    foreach ($statements as $sql) {
                        $pdo->exec($sql);
                    }
                    $row('✓', 'green', 'created');
                    $created++;
                }
            }

            $this->newLine();

            if (!$dryRun) {
                $this->displaySuccess(
                    "Done. Created: {$created}" . ($skipped ? ", Skipped (already exist): {$skipped}" : '')
                );
            }

            return Command::SUCCESS;
        });
    }

    /**
     * Resolve the database connection for a temporal model, honoring a command
     * override first and then the model's own configured connection.
     *
     * @param Model $model
     * @param string|null $overrideConnection
     * @return string
     */
    private function resolveConnectionForModel(Model $model, ?string $overrideConnection = null): string
    {
        return $overrideConnection
            ?: $model->getConnectionName()
            ?: config('database.default');
    }

    /**
     * Scan the Models directory for classes that extend Model and carry #[Temporal].
     *
     * @param string $path
     * @return string[]
     */
    private function discoverTemporalModels(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $found    = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->classFromFile($file->getPathname());

            if ($class === null || !class_exists($class)) {
                continue;
            }

            if (!is_subclass_of($class, Model::class)) {
                continue;
            }

            $ref = new \ReflectionClass($class);

            if (!empty($ref->getAttributes(Temporal::class))) {
                $found[] = $class;
            }
        }

        return $found;
    }

    /**
     * Extract the fully-qualified class name from a PHP file by reading its
     * namespace and class declarations.
     *
     * @param string $filePath
     * @return string|null
     */
    private function classFromFile(string $filePath): ?string
    {
        $content = @file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        if (!preg_match('/^namespace\s+(.+?);/m', $content, $nsMatch)) {
            return null;
        }

        if (!preg_match('/^(?:abstract\s+)?class\s+(\w+)/m', $content, $classMatch)) {
            return null;
        }

        return $nsMatch[1] . '\\' . $classMatch[1];
    }

    /**
     * Check whether a table already exists in the database.
     *
     * @param PDO    $pdo
     * @param string $driver
     * @param string $table
     * @return bool
     */
    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        try {
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare("SELECT to_regclass(?) IS NOT NULL");
                $stmt->execute([$table]);
                return (bool) $stmt->fetchColumn();
            }

            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?");
                $stmt->execute([$table]);
                return (bool) $stmt->fetchColumn();
            }

            // MySQL
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check whether a specific column exists in a table.
     *
     * @param PDO    $pdo
     * @param string $driver
     * @param string $table
     * @param string $column
     * @return bool
     */
    private function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        try {
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.columns
                     WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?"
                );
                $stmt->execute([$table, $column]);
                return (bool) $stmt->fetchColumn();
            }

            if ($driver === 'sqlite') {
                $stmt = $pdo->query("PRAGMA table_info(\"{$table}\")");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                    if ($col['name'] === $column) {
                        return true;
                    }
                }
                return false;
            }

            // MySQL
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
            $stmt->execute([$column]);
            return $stmt->fetch() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Generate the ALTER TABLE SQL to add the actor column.
     *
     * @param string $driver
     * @param string $table
     * @return string
     */
    private function addActorColumnSql(string $driver, string $table): string
    {
        return match ($driver) {
            'pgsql'  => "ALTER TABLE \"{$table}\" ADD COLUMN actor VARCHAR(255) NULL",
            'sqlite' => "ALTER TABLE \"{$table}\" ADD COLUMN actor TEXT NULL",
            default  => "ALTER TABLE `{$table}` ADD COLUMN `actor` VARCHAR(255) NULL",
        };
    }

    /**
     * Generate the ALTER TABLE SQL to add the branch column.
     *
     * @param string $driver
     * @param string $table
     * @return string
     */
    private function addBranchColumnSql(string $driver, string $table): string
    {
        return match ($driver) {
            'pgsql'  => "ALTER TABLE \"{$table}\" ADD COLUMN branch VARCHAR(50) NOT NULL DEFAULT 'main'",
            'sqlite' => "ALTER TABLE \"{$table}\" ADD COLUMN branch TEXT NOT NULL DEFAULT 'main'",
            default  => "ALTER TABLE `{$table}` ADD COLUMN `branch` VARCHAR(50) NOT NULL DEFAULT 'main'",
        };
    }

    /**
     * Check whether the given index exists on the given table.
     *
     * @param PDO $pdo
     * @param string $driver
     * @param string $table
     * @param string $index
     * @return bool
     */
    private function indexExists(PDO $pdo, string $driver, string $table, string $index): bool
    {
        try {
            if ($driver === 'sqlite') {
                foreach ($pdo->query("PRAGMA index_list(\"{$table}\")")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if ($row['name'] === $index) return true;
                }
                return false;
            }
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare('SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?');
                $stmt->execute([$table, $index]);
                return $stmt->fetchColumn() !== false;
            }
            $stmt = $pdo->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = ?");
            $stmt->execute([$index]);
            return $stmt->fetch() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Generate the CREATE INDEX SQL for the branch lookup index.
     *
     * @param string $driver
     * @param string $table
     * @return string
     */
    private function createBranchIndexSql(string $driver, string $table): string
    {
        $name = $driver === 'mysql' ? 'idx_branch' : 'idx_' . $table . '_branch';
        return match ($driver) {
            'pgsql', 'sqlite' => "CREATE INDEX \"{$name}\" ON \"{$table}\" (record_id, branch, valid_from)",
            default => "CREATE INDEX `{$name}` ON `{$table}` (record_id, branch, valid_from)",
        };
    }
}
