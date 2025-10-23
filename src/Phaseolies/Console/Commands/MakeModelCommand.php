<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Database\Migration\MigrationCreator;

class MakeModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:model {name} {--m}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new model class';

    /**
     * The migration creator instance.
     *
     * @var MigrationCreator
     */
    protected MigrationCreator $creator;

    /**
     * Create a new command instance.
     *
     * @param MigrationCreator $creator
     */
    public function __construct(MigrationCreator $creator)
    {
        parent::__construct();

        $this->creator = $creator;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $name = $this->argument('name');
            $withMigration = $this->option('m');
            $parts = explode('/', $name);
            $className = array_pop($parts);
            $namespace = 'App\\Models' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Models/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            // Check if model already exists
            if (file_exists($filePath)) {
                $this->displayError('Model already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return Command::FAILURE;
            }

            // Create directory if needed
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            // Generate and save model class
            $content = $this->generateModelContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Model created successfully');
            $this->line('<fg=yellow>📦 File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Class:</> <fg=white>' . $className . '</>');

            // Create migration if requested
            if ($withMigration) {
                $tableName = str()->snake($className);
                $migrationName = "create_{$tableName}_table";
                $migrationFile = $this->createMigration($migrationName, $tableName);

                $this->newLine();
                $this->line('<bg=blue;options=bold> MIGRATION </> Created migration:');
                $this->newLine();
                $this->line('<fg=white>' . str_replace(base_path(), '', $migrationFile) . '</>');
            }

            return Command::SUCCESS;
        });
    }

    /**
     * Generate model class content.
     */
    protected function generateModelContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Database\Entity\Model;

class {$className} extends Model
{
    //
}

EOT;
    }

    /**
     * Create a migration for the model.
     */
    protected function createMigration(string $name, string $table): string
    {
        return $this->creator->create(
            $name,
            $this->getMigrationPath(),
            $table,
            true
        );
    }

    /**
     * Get the path to the migration directory.
     */
    protected function getMigrationPath(): string
    {
        return base_path('database/migrations');
    }
}