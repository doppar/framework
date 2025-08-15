<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use RuntimeException;

class MakeControllerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:controller {name} {--invokable} {--bundle} {--api}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new controller class';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function () {
            $name = $this->argument('name');
            $isInvokable = $this->option('invokable');
            $isResource = $this->option('bundle');
            $isApi = $this->option('api');

            // Validate options
            if ($isInvokable && ($isResource || $isApi)) {
                $this->displayError('A controller cannot be both invokable and bundle/api.');
                return 1;
            }

            if ($isResource && $isApi) {
                $this->displayError('A controller cannot be both bundle and API.');
                return 1;
            }

            $parts = explode('/', $name);
            $className = array_pop($parts);
            $baseNamespace = 'App\\Http\\Controllers';

            if ($isApi) {
                $baseNamespace .= '\\API';
            }

            $namespace = $baseNamespace . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');

            $filePath = base_path('app/Http/Controllers/' .
                ($isApi ? 'API/' : '') .
                str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            if (file_exists($filePath)) {
                $this->displayError('Controller already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return 1;
            }

            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            $stub = $this->getStub($isInvokable, $isResource, $isApi);
            $content = $this->replacePlaceholders($stub, $namespace, $className);

            file_put_contents($filePath, $content);

            $this->displaySuccess('Controller created successfully');
            $this->line('<fg=yellow>📁 File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();

            $type = match (true) {
                $isInvokable => 'Invokable',
                $isResource => 'Resource',
                $isApi => 'API Resource',
                default => 'Standard'
            };

            $this->line('<fg=yellow>📌 Type:</> <fg=white>' . $type . ' controller</>');

            return 0;
        });
    }

    /**
     * Get the appropriate stub content.
     */
    protected function getStub(bool $isInvokable, bool $isResource, bool $isApi): string
    {
        $stubName = match (true) {
            $isInvokable => 'invokable.stub',
            $isResource => 'resource.stub',
            $isApi => 'api.stub',
            default => 'plain.stub'
        };

        $stubPath = __DIR__ . '/stubs/controller/' . $stubName;

        if (!file_exists($stubPath)) {
            throw new RuntimeException("Stub file not found: {$stubPath}");
        }

        return file_get_contents($stubPath);
    }

    /**
     * Replace placeholders in the stub.
     */
    protected function replacePlaceholders(string $stub, string $namespace, string $className): string
    {
        return str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $className],
            $stub
        );
    }
}
