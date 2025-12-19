<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class MakeConsoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:command {name}';

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
        return $this->executeWithTiming(function() {
            $name = $this->argument('name');
            $parts = explode('/', $name);
            $className = array_pop($parts);
            $namespace = 'App\\Schedule\\Commands' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Schedule/Commands/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            if (file_exists($filePath)) {
                $this->displayError('Command already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return Command::FAILURE;
            }

            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            $content = $this->generateCommandContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Command created successfully');
            $this->line('<fg=yellow>📁 File:</> <fg=white>' . str_replace(base_path('/'), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Command Name:</> <fg=white>doppar:' . $this->convertToKebabCase($className) . '</>');

            return Command::SUCCESS;
        });
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

        $appName = strtolower(str_replace(' ', '_', config('app.name', 'doppar')));

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
    protected \$name = '{$appName}:{$commandName}';

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
        return Command::SUCCESS;
    }
}

EOT;
    }
}
