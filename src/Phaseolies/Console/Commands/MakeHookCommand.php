<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use RuntimeException;

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
        $startTime = microtime(true);
        $this->newLine();

        try {
            $name = $this->argument('name');
            $parts = explode('/', $name);
            $className = array_pop($parts);
            $namespace = 'App\\Hooks' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Hooks/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            // Check if hook already exists
            if (file_exists($filePath)) {
                $this->line('<bg=red;options=bold> ERROR </> Hook already exists at:');
                $this->newLine();
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                $this->newLine();
                return 1;
            }

            // Create directory if needed
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            // Generate and save hook class
            $content = $this->generateHookContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->line('<bg=green;options=bold> SUCCESS </> Hook created successfully');
            $this->newLine();
            $this->line('<fg=yellow>📁 File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Hook Class:</> <fg=white>' . $className . '</>');

            $executionTime = microtime(true) - $startTime;
            $this->newLine();
            $this->line(sprintf(
                "<fg=yellow>⏱ Time:</> <fg=white>%.4fs</> <fg=#6C7280>(%d μs)</>",
                $executionTime,
                (int) ($executionTime * 1000000)
            ));
            $this->newLine();

            return 0;
        } catch (RuntimeException $e) {
            $this->line('<bg=red;options=bold> ERROR </> ' . $e->getMessage());
            $this->newLine();
            return 1;
        }
    }

    /**
     * Generate hook class content.
     */
    protected function generateHookContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Database\Eloquent\Model;

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