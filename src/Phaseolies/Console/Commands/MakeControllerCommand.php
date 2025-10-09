<?php

namespace Phaseolies\Console\Commands;

use RuntimeException;
use Phaseolies\Support\Facades\Str;
use Phaseolies\Console\Schedule\Command;

class MakeControllerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:controller {name} {--invokable} {--bundle} {--api} {--complete}';

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
            $name = str()->suffixAppend($this->argument('name'), 'Controller');
            $routeName = strtolower(Str::removeSuffix($name, 'Controller'));
            $isInvokable = $this->option('invokable');
            $isResource = $this->option('bundle');
            $isApi = $this->option('api');
            $isComplete = $this->option('complete');

            // Validate options
            if ($error = $this->validateControllerOptions($isInvokable, $isResource, $isApi, $isComplete)) {
                $this->displayError($error);
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

            $stub = $this->getStub($isInvokable, $isResource, $isApi, $isComplete);
            $content = $this->replacePlaceholders($stub, $namespace, $className, $routeName);

            file_put_contents($filePath, $content);

            $this->displaySuccess('Controller created successfully');
            $this->line('<fg=yellow>📁 File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();

            $type = match (true) {
                $isInvokable => 'Invokable',
                $isResource => 'Resource',
                $isApi => 'API Resource',
                $isComplete => 'Complete',
                default => 'Standard'
            };

            $this->line('<fg=yellow>📌 Type:</> <fg=white>' . $type . ' controller</>');

            // If complete, also publish the layout view
            if ($isComplete) {
                $layoutStub = $this->getLayoutStub('complete.stub');
                $layoutContent = $this->replacePlaceholders($layoutStub, $namespace, $className, $routeName);
                $layoutDir = base_path('resources/views/' . $routeName);
                if (!is_dir($layoutDir)) {
                    mkdir($layoutDir, 0755, true);
                }
                $layoutPath = $layoutDir . '/default.blade.php';
                file_put_contents($layoutPath, $layoutContent);
                $this->line('<fg=yellow>📁 Layout:</> <fg=white>' . str_replace(base_path(), '', $layoutPath) . '</>');
            }

            return 0;
        });
    }

    /**
     * Validate mutually exclusive controller generation options.
     *
     * Returns an error message string when a conflict is detected, or null when valid.
     */
    protected function validateControllerOptions(bool $isInvokable, bool $isResource, bool $isApi, bool $isComplete): ?string
    {
        if ($isInvokable && ($isResource || $isApi || $isComplete)) {
            return 'A controller cannot be both invokable and bundle/api/complete.';
        }

        if ($isResource && ($isApi || $isComplete)) {
            return 'A controller cannot be both bundle and API/complete.';
        }

        if ($isComplete && ($isInvokable || $isResource || $isApi)) {
            return 'A controller cannot be both complete and invokable/bundle/api.';
        }

        return null;
    }

    /**
     * Get the appropriate stub content.
     *
     * @return string
     * @throws RuntimeException
     */
    protected function getStub(bool $isInvokable, bool $isResource, bool $isApi, bool $isComplete): string
    {
        $stubName = match (true) {
            $isInvokable => 'invokable.stub',
            $isResource => 'resource.stub',
            $isApi => 'api.stub',
            $isComplete => 'complete.stub',
            default => 'plain.stub'
        };

        $stubPath = __DIR__ . '/stubs/controller/' . $stubName;

        if (!file_exists($stubPath)) {
            throw new RuntimeException("Stub file not found: {$stubPath}");
        }

        $content = file_get_contents($stubPath);
        if ($content === false) {
            throw new RuntimeException("Failed to read stub file: {$stubPath}");
        }

        return $content;
    }
    /**
     * Replace placeholders in the stub.
     */
    protected function replacePlaceholders(string $stub, string $namespace, string $className, string $routeName): string
    {
        return str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ routeName }}'],
            [$namespace, $className, $routeName],
            $stub
        );
    }

    /**
     * Get a layout stub content by name from controller/layouts stubs.
     *
     * @throws RuntimeException
     */
    protected function getLayoutStub(string $stubName): string
    {
        $stubPath = __DIR__ . '/stubs/controller/layouts/' . $stubName;

        if (!file_exists($stubPath)) {
            throw new RuntimeException("Layout stub file not found: {$stubPath}");
        }

        $content = file_get_contents($stubPath);
        if ($content === false) {
            throw new RuntimeException("Failed to read layout stub file: {$stubPath}");
        }

        return $content;
    }
}
