<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Session\SessionManager;

class SessionTableCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'session:table';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a migration for the session database table.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function (): int {
            $table = (string) SessionManager::config()['table'];

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?\z/', $table) !== 1) {
                $this->displayError("Invalid session table name [{$table}].");

                return Command::FAILURE;
            }

            $directory = $this->migrationPath();
            $name = 'create_' . str_replace('.', '_', $table) . '_table';

            if ($existing = $this->existingMigration($directory, $name)) {
                $this->displayError('A migration for the session table already exists.');
                $this->line("<fg=yellow>📁 File:</> <fg=white>{$existing}</>");

                return Command::FAILURE;
            }

            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                $this->displayError("Unable to create the migration directory [{$directory}].");

                return Command::FAILURE;
            }

            $file = $directory . '/' . date('Y_m_d_His') . '_' . $name . '.php';
            $stub = (string) file_get_contents(__DIR__ . '/stubs/session/create_sessions_table.stub');

            file_put_contents($file, str_replace('{{ table }}', $table, $stub));

            $this->displaySuccess('Session table migration created successfully.');
            $this->line("<fg=yellow>📁 File:</> <fg=white>{$file}</>");

            return Command::SUCCESS;
        });
    }

    /**
     * Path of the application's migrations.
     *
     * @return string
     */
    protected function migrationPath(): string
    {
        return base_path('schema/migrations');
    }

    /**
     * An existing migration with the same name, if any.
     *
     * @param string $directory
     * @param string $name
     * @return string|null
     */
    protected function existingMigration(string $directory, string $name): ?string
    {
        if (!is_dir($directory)) {
            return null;
        }

        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '_' . $name . '.php')) {
                return $file->getPathname();
            }
        }

        return null;
    }
}
