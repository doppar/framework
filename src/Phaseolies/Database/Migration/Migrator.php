<?php

namespace Phaseolies\Database\Migration;

class Migrator
{
    /**
     * Path to the directory containing migration files
     *
     * @var string
     */
    protected string $migrationPath;

    /**
     * Migration repository instance for tracking run migrations
     *
     * @var MigrationRepository
     */
    protected MigrationRepository $repository;

    /**
     * @var array
     */
    protected $migrations = [];

    /**
     * @var array
     */
    protected array $migrationPaths = [];

    /**
     * The database connection name this migration should use
     *
     * @var string|null
     */
    protected ?string $connection = null;

    /**
     * Constructor
     * @param MigrationRepository $repository
     * @param string $migrationPath
     */
    public function __construct(MigrationRepository $repository, string $migrationPath)
    {
        $this->repository = $repository;
        $this->migrationPath = $migrationPath;
    }

    /**
     * Add a migration to the migrator
     *
     * @param string $file
     * @param Migration $migration
     */
    public function addMigration(string $file, Migration $migration): void
    {
        $this->migrations[$file] = $migration;
        $this->migrationPaths[] = $file;
    }

    /**
     * Run all pending migrations
     *
     * @param string $connection
     * @return array
     */
    public function run(string $connection, ?string $path = null): array
    {
        $connection = $connection ?? config('database.default');
        $this->ensureMigrationTableExists($connection);

        $files = $this->getMigrationFiles($connection);
        $ran = $this->repository->getRan($connection);

        $localMigrations = [];
        $vendorMigrations = [];

        foreach ($files as $file) {
            if (str_contains($file, '/vendor/')) {
                $vendorMigrations[basename($file)] = $file;
            } else {
                $localMigrations[basename($file)] = $file;
            }
        }

        $fileNames = array_map('basename', array_merge($files));
        $migrations = array_diff($fileNames, $ran);

        foreach ($vendorMigrations as $basename => $vendorPath) {
            if (!file_exists(database_path('migration/' . $basename))) {
                $migrations[] = $vendorPath;
            }

            foreach ($migrations as $key => $value) {
                if (basename($value) === $basename && strpos($value, 'vendor') === false) {
                    unset($migrations[$key]);
                }
            }
        }

        if ($path) {
            $file = basename($path);
            if (in_array($file, $ran)) {
                return [];
            }

            $fullPath = is_file($path) ? $path : $this->migrationPath . DIRECTORY_SEPARATOR . $file;
            if (!is_file($fullPath)) {
                throw new \RuntimeException("Migration file not found: {$fullPath}\n\n");
            }

            $this->runMigrationList([$file], $connection);

            return [$file];
        }

        if (empty($migrations)) {
            return [];
        }

        $executed = [];
        foreach ($migrations as $file) {
            $fullPath = is_file($file) ? $file : $this->migrationPath . DIRECTORY_SEPARATOR . $file;

            if (!is_file($fullPath)) {
                echo "Migration file not found: {$file}\n\n";
                continue;
            }

            $executed[] = basename($fullPath);
        }
        sort($executed);

        $this->runMigrationList($executed, $connection);

        return $executed;
    }

    /**
     * Ensure the migration tracking table exists in the database
     *
     * @param string|null $connection
     * @return void
     */
    protected function ensureMigrationTableExists(?string $connection = null): void
    {
        if (!$this->repository->exists($connection)) {
            $this->repository->create($connection);
        }
    }

    /**
     * Get all migration files from the migration path
     *
     * @param string|null $connection
     * @return array
     */
    public function getMigrationFiles(?string $connection = null): array
    {
        $connection = $connection ?? config('database.default');

        $files = glob($this->migrationPath . '/*.php') ?: [];
        $packageMigrations = $this->migrationPaths ?: [];

        // Normalize vendor migrations to full paths if needed
        $normalizedPackages = array_map(function ($file) {
            return is_file($file) ? $file : (base_path($file));
        }, $packageMigrations);

        /**
         * 🧠 Build a map by basename so project migrations override vendor ones
         * Example:
         *   2025_10_11_000001_create_users_table.php → pick from database/migrations if exists
         */
        $migrationMap = [];

        foreach ($normalizedPackages as $vendorFile) {
            if (is_file($vendorFile)) {
                $migrationMap[basename($vendorFile)] = $vendorFile;
            }
        }

        foreach ($files as $localFile) {
            $migrationMap[basename($localFile)] = $localFile;
        }

        $allFiles = array_values($migrationMap);

        $filtered = [];

        foreach ($allFiles as $path) {
            if (!is_file($path)) continue;

            $content = file_get_contents($path);
            if ($content === false) continue;

            $patterns = [
                "/Schema::connection\(\s*['\"]([^'\"]+)['\"]\s*\)/i",
                "/DB::connection\(\s*['\"]([^'\"]+)['\"]\s*\)/i",
                "/protected\s+\$connection\s*=\s*['\"]([^'\"]+)['\"]\s*;/i",
                "/public\s+\$connection\s*=\s*['\"]([^'\"]+)['\"]\s*;/i",
                "/\$this->connection\s*=\s*['\"]([^'\"]+)['\"]\s*;/i",
            ];

            $migrationConnection = null;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content, $m)) {
                    $migrationConnection = $m[1];
                    break;
                }
            }

            if ($migrationConnection === null) {
                $migrationConnection = config('database.default');
            }

            if ($migrationConnection === $connection) {
                $filtered[] = $path;
            }
        }

        return $filtered;
    }

    /**
     * Run a list of migration files
     *
     * @param array $migrations
     * @param string|null $connection
     */
    protected function runMigrationList(array $migrations, ?string $connection = null): void
    {
        foreach ($migrations as $file) {
            $this->runMigration($file, $connection);
        }
    }

    /**
     * Run a single migration file
     *
     * @param string $file
     * @param string|null $connection
     * @return void
     * @throws \RuntimeException
     */
    protected function runMigration(string $file, ?string $connection = null): void
    {
        try {
            foreach ($this->migrations as $path => $migration) {
                if (basename($path) === $file) {
                    $migration->up();
                    $this->repository->log($file, $connection);
                    return;
                }
            }

            $path = $this->migrationPath . '/' . $file;
            if (!file_exists($path)) {
                throw new \RuntimeException("Migration file not found: {$path}");
            }

            $migration = require $path;

            $migration->up();
            $this->repository->log($file, $connection);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Resolve a migration file into a Migration instance
     *
     * @param string $file
     * @return Migration
     * @throws \RuntimeException
     */
    protected function resolve(string $file): Migration
    {
        $path = $this->migrationPath . '/' . $file;

        if (!file_exists($path)) {
            throw new \RuntimeException("Migration file not found: {$path}");
        }

        $migration = require $path;

        if (!$migration instanceof Migration) {
            throw new \RuntimeException("Migration {$file} must return an instance of Migration");
        }

        return $migration;
    }

    /**
     * Convert a migration file name to a class name
     *
     * @param string $file
     * @return string
     */
    protected function getMigrationClass(string $file): string
    {
        $file = str_replace('.php', '', $file);

        return str()->camel($file);
    }
}
