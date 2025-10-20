<?php

namespace Phaseolies\Database\Migration;

class MigrationCreator
{
    /**
     * @var string Path to the stub templates
     */
    protected string $stubPath;

    /**
     * Initializes the path to the migration stubs directory.
     */
    public function __construct()
    {
        $this->stubPath = __DIR__ . '/stubs';
    }

    /**
     * Create a new migration file.
     *
     * @param string $name
     * @param string $path
     * @param string|null $table
     * @param bool $create
     * @return string
     */
    public function create(string $name, string $path, ?string $table = null, bool $create = false): string
    {
        $this->ensureMigrationDirectoryExists($path);

        $name = $this->getMigrationName($name);
        $file = $path . '/' . $name . '.php';
        $stub = $this->getStub($table, $create);

        $this->populateStub($name, $stub, $table);

        file_put_contents($file, $stub);

        return $file;
    }

    /**
     * Ensure the migration directory exists. If not, create it.
     *
     * @param string $path
     * @return void
     */
    protected function ensureMigrationDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Generate a timestamped migration file name.
     *
     * @param string $name
     * @return string
     */
    protected function getMigrationName(string $name): string
    {
        return date('Y_m_d_His') . '_' . $this->snakeCase($name);
    }

    /**
     * Get the appropriate stub content based on migration context.
     *
     * @param string|null $table
     * @param bool $create
     * @return string
     */
    protected function getStub(?string $table, bool $create): string
    {
        if (is_null($table)) {
            return file_get_contents($this->stubPath . '/migration.stub');
        }

        if ($create) {
            return file_get_contents($this->stubPath . '/migration.create.stub');
        }

        return file_get_contents($this->stubPath . '/migration.update.stub');
    }

    /**
     * Replace placeholders in the stub with actual values.
     *
     * @param string $name
     * @param string $stub
     * @param string|null $table
     * @return void
     */
    protected function populateStub(string $name, string &$stub, ?string $table): void
    {
        $file = str_replace('.php', '', $name);

        if ($table) {
            $className = $this->pascalCase(
                preg_replace('/^\d+_/', '', $file) // Remove timestamp prefix
            );
        } else {
            $className = $this->pascalCase($file);
        }

        // Replace class name placeholders
        $stub = str_replace(['DummyClass', '{{ class }}', '{{class}}'], $className, $stub);

        // Replace table name placeholders if table name is given
        if (!is_null($table)) {
            $stub = str_replace(['DummyTable', '{{ table }}', '{{table}}'], $table, $stub);
        }
    }

    /**
     * Convert a string to PascalCase.
     *
     * @param string $input
     * @return string
     */
    protected function pascalCase(string $input): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $input)));
    }

    /**
     * Convert a string to snake_case.
     *
     * @param string $input
     * @return string
     */
    protected function snakeCase(string $input): string
    {
        return str()->snake($input);
    }
}
