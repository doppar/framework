<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use RuntimeException;

class MakeConsoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:schedule {name}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new console command class';

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
            $namespace = 'App\\Schedule\\Commands' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Schedule/Commands/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            if (file_exists($filePath)) {
                $this->line('<bg=red;options=bold> ERROR </> Command already exists at:');
                $this->newLine();
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                $this->newLine();
                return 1;
            }

            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            $content = $this->generateCommandContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->line('<bg=green;options=bold> SUCCESS </> Command created successfully');
            $this->newLine();
            $this->line('<fg=yellow>📁 File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Command Name:</> <fg=white>doppar:' . $this->convertToKebabCase($className) . '</>');

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
     * Generate the command class content.
     */
    protected function generateCommandContent(string $namespace, string $className): string
    {
        return $this->generateRegularControllerContent($namespace, $className);
    }

    /**
     * Convert class name to kebab-case for command name.
     */
    protected function convertToKebabCase(string $input): string
    {
        $input = str_replace('Command', '', $input);
        $output = preg_replace('/(?<!^)([A-Z])/', '-$1', $input);
        return strtolower($output);
    }

    /**
     * Generate standard command class content.
     */
    protected function generateRegularControllerContent(string $namespace, string $className): string
    {
        $commandName = $this->convertToKebabCase($className);

        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Console\Schedule\Command;

class {$className} extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected \$name = 'doppar:{$commandName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected \$description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return 0;
    }
}

EOT;
    }
}
