<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class MakeHookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:hook {name}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new model hook class';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $name = $this->argument('name');
            $parts = explode('/', $name);
            $className = array_pop($parts);
            $namespace = 'App\\Hooks' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Hooks/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            // Check if hook already exists
            if (file_exists($filePath)) {
                $this->displayError('Hook already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return Command::FAILURE;
            }

            // Create directory if needed
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            // Generate and save hook class
            $content = $this->generateHookContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Hook created successfully');
            $this->line('<fg=yellow>📁 File:</> <fg=white>' . str_replace(base_path('/'), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Hook Class:</> <fg=white>' . $className . '</>');

            return Command::SUCCESS;
        });
    }

    /**
     * Generate hook class content.
     */
    protected function generateHookContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Database\Entity\Model;

class {$className}
{
    /**
     * Handle the incoming model hook
     *
     * @param Model \$model
     * @return void
     */
    public function handle(Model \$model): void
    {
        //
    }
}

EOT;
    }
}