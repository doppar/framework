<?php

namespace Phaseolies\Database\Migration;

class Migrator
{
    /**
     * Path to the directory containing migration files
     * @var string
     */
    protected string $migrationPath;

    /**
     * Migration repository instance for tracking run migrations
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
     * Constructor
     * @param MigrationRepository $repository Repository for tracking migrations
     * @param string $migrationPath Path to migration files directory
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
     * @return array List of migration files that were executed
     */
    public function run(): array
    {
        $this->ensureMigrationTableExists();

        $files = $this->getMigrationFiles();
        $ran = $this->repository->getRan();

        $migrations = array_diff($files, $ran);
        $migrations = array_unique($migrations);

        if (empty($migrations)) {
            return [];
        }

        $this->runMigrationList($migrations);

        return $migrations;
    }

    /**
     * Ensure the migration tracking table exists in the database
     * Creates it if it doesn't exist
     */
    protected function ensureMigrationTableExists(): void
    {
        if (!$this->repository->exists()) {
            $this->repository->create();
        }
    }

    /**
     * Get all migration files from the migration path
     *
     * @return array List of migration file names
     */
    protected function getMigrationFiles(): array
    {
        $files = glob($this->migrationPath . '/*.php') ?: [];

        $packageMigrations = app('migrator')->migrationPaths;

        $allFiles = array_merge($files, $packageMigrations);

        return array_map('basename', $allFiles);
    }

    /**
     * Run a list of migration files
     *
     * @param array $migrations List of migration file names to run
     */
    protected function runMigrationList(array $migrations): void
    {
        foreach ($migrations as $file) {
            $this->runMigration($file);
        }
    }

    /**
     * Run a single migration file
     *
     * @param string $file Migration file name
     * @throws \Throwable If migration fails
     */
    protected function runMigration(string $file): void
    {
        try {
            foreach (app('migrator')->migrations as $path => $migration) {
                if (basename($path) === $file) {
                    $migration->up();
                    $this->repository->log($file);
                    return;
                }
            }

            $path = $this->migrationPath . '/' . $file;
            if (!file_exists($path)) {
                throw new \RuntimeException("Migration file not found: {$path}");
            }

            $migration = require $path;
            $migration->up();
            $this->repository->log($file);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Resolve a migration file into a Migration instance
     *
     * @param string $file Migration file name
     * @return Migration Migration instance
     * @throws \RuntimeException If file doesn't exist or invalid migration
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
     * (Note: This method appears unused in the current class implementation)
     * @param string $file Migration file name
     * @return string Class name
     */
    protected function getMigrationClass(string $file): string
    {
        $file = str_replace('.php', '', $file);

        return str()->camel($file);
    }
}
