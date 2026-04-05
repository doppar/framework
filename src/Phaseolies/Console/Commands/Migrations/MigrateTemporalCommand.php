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
            $connection = $this->option('connection') ?: config('database.default');
            $dryRun     = (bool) $this->option('show');
            $modelsPath = $this->option('path') ?: base_path('app/Models');

            try {
                $pdo    = Database::getPdoInstance($connection ?: null);
                $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            } catch (\Throwable $e) {
                $this->displayError('Failed to connect to database: ' . $e->getMessage());
                return Command::FAILURE;
            }

            $this->line("<fg=yellow>🕐 Temporal Migration — connection: {$connection}</>");
            $this->newLine();

            $classes = $this->discoverTemporalModels($modelsPath);

            if (empty($classes)) {
                $this->displayInfo('No #[Temporal] models found in ' . $modelsPath);
                return Command::SUCCESS;
            }

            $created = 0;
            $skipped = 0;

            foreach ($classes as $class) {
                $attr         = TemporalManager::getAttribute($class);
                $model        = new $class();
                $baseTable    = $model->getTable();
                $historyTable = TemporalManager::historyTable($baseTable, $class);

                $this->line("  <fg=cyan>Model</>        : <fg=white>{$class}</>");
                $this->line("  <fg=cyan>Base table</>   : <fg=white>{$baseTable}</>");
                $this->line("  <fg=cyan>History table</> : <fg=white>{$historyTable}</>");

                if (!$dryRun && $this->tableExists($pdo, $driver, $historyTable)) {
                    // Table exists — check if we need to add the actor column
                    if ($attr?->trackActor && !$this->columnExists($pdo, $driver, $historyTable, 'actor')) {
                        $sql = $this->addActorColumnSql($driver, $historyTable);
                        $pdo->exec($sql);
                        $this->line('  <fg=green>→ Added missing `actor` column.</>');
                        $created++;
                    } else {
                        $this->line('  <fg=yellow>→ Already exists, skipped.</>');
                        $skipped++;
                    }
                    $this->newLine();
                    continue;
                }

                $statements = TemporalManager::createHistoryTableSql(
                    historyTable: $historyTable,
                    driver:       $driver,
                    trackActor:   $attr?->trackActor ?? false,
                );

                if ($dryRun) {
                    foreach ($statements as $sql) {
                        $this->line('  <fg=yellow>SQL:</> ' . preg_replace('/\s+/', ' ', trim($sql)));
                    }
                } else {
                    foreach ($statements as $sql) {
                        $pdo->exec($sql);
                    }
                    $this->line('  <fg=green>→ Created successfully.</>');
                    $created++;
                }

                $this->newLine();
            }

            if (!$dryRun) {
                $this->displaySuccess(
                    "Done. Created: {$created}" . ($skipped ? ", Skipped (already exist): {$skipped}" : '')
                );
            }

            return Command::SUCCESS;
        });
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
                     WHERE table_name = ? AND column_name = ?"
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
}
