<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class MakeWatcherCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:watcher {name}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new model property watcher listener class';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $name      = $this->argument('name');
            $parts     = explode('/', $name);
            $className = array_pop($parts);
            $namespace = 'App\\Watchers' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath  = base_path('app/Watchers/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            if (file_exists($filePath)) {
                $this->displayError('Watcher already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return Command::FAILURE;
            }

            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            file_put_contents($filePath, $this->generateWatcherContent($namespace, $className));

            $this->displaySuccess('Watcher created successfully');
            $this->line('<fg=yellow>👁️  File:</> <fg=white>' . str_replace(base_path('/'), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Class:</> <fg=white>' . $className . '</>');

            return Command::SUCCESS;
        });
    }

    /**
     * Generate watcher listener class content.
     */
    protected function generateWatcherContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Database\Entity\Model;

class {$className}
{
    /**
     * Handle the watched property change.
     *
     * @param mixed \$old
     * @param mixed \$new
     * @param Model \$model
     * @return void
     */
    public function handle(mixed \$old, mixed \$new, Model \$model): void
    {
        //
    }
}

EOT;
    }
}
