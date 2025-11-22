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
    protected $name = 'make:controller {name} {--invokable} {--bundle} {--api} {--complete} {--c}';

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
            [$name, $routeName, $isInvokable, $isResource, $isApi, $isComplete] = $this->parseFlags();

            // Validate options
            if ($error = $this->validateControllerOptions($isInvokable, $isResource, $isApi, $isComplete)) {
                $this->displayError($error);
                return Command::FAILURE;
            }

            [$namespace, $filePath, $className] = $this->resolveNamespacesAndPaths($name, $isApi);

            if (file_exists($filePath)) {
                $this->displayError('Controller already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return Command::FAILURE;
            }

            $this->createDirIfMissing(dirname($filePath));

            $stub = $this->getStub($isInvokable, $isResource, $isApi, $isComplete);
            $content = $this->replacePlaceholders($stub, $namespace, $className, $routeName);

            $this->writeFile($filePath, $content);

            $this->displaySuccess('Controller created successfully');
            $this->outputFilePath('📁 File', $filePath);

            if ($isComplete) {
                $this->generateLayout($namespace, $className, $routeName);
            }

            $this->newLine();
            $this->outputType($isInvokable, $isResource, $isApi, $isComplete);

            return Command::SUCCESS;
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
     * Replace placeholders in the stub with actual values.
     */
    protected function replacePlaceholders(
        string $stub,
        string $namespace,
        string $className,
        string $routeName
    ): string {
        $baseNamespace = 'App\\Http\\Controllers';
        $basePath = 'app/Http/Controllers/';

        // Normalize route view name
        $routeView = str_replace(['\\', '/'], '.', $routeName);

        // Extract sub-namespace relative to base controller namespace
        $subNamespace = match (true) {
            str_starts_with($namespace, $baseNamespace . '\\') => substr($namespace, strlen($baseNamespace . '\\')),
            $namespace === $baseNamespace => '',
            default => $this->extractSubNamespace($namespace),
        };

        // Convert sub-namespace to relative path
        $relativePath = $subNamespace
            ? str_replace('\\', '/', $subNamespace) . '/'
            : '';

        $controllerPath = "{$basePath}{$relativePath}{$className}.php";

        return strtr($stub, [
            '{{ namespace }}'      => $namespace,
            '{{ class }}'          => $className,
            '{{ routeName }}'      => $routeName,
            '{{ routeView }}'      => $routeView,
            '{{ routingName }}'    => $routeView,
            '{{ controllerPath }}' => $controllerPath,
        ]);
    }

    /**
     * Extract sub-namespace after the last "Controllers" segment.
     */
    protected function extractSubNamespace(string $namespace): string
    {
        $pos = strrpos($namespace, 'Controllers');
        return $pos !== false
            ? ltrim(substr($namespace, $pos + strlen('Controllers')), '\\')
            : '';
    }

    /**
     * Parse CLI flags and derive base names.
     *
     * @return array{string,string,bool,bool,bool,bool} [$name,$routeName,$isInvokable,$isResource,$isApi,$isComplete]
     */
    protected function parseFlags(): array
    {
        $name = str()->suffixAppend($this->argument('name'), 'Controller');
        $routeName = strtolower(str()->removeSuffix($name, 'Controller'));
        $isInvokable = $this->option('invokable');
        $isResource = $this->option('bundle');
        $isApi = $this->option('api');
        $isComplete = $this->option('complete') || $this->option('c');

        return [$name, $routeName, $isInvokable, $isResource, $isApi, $isComplete];
    }

    /**
     * Resolve namespace, file path and class name for the controller.
     *
     * @return array{string,string,string} [$namespace,$filePath,$className]
     */
    protected function resolveNamespacesAndPaths(string $name, bool $isApi): array
    {
        $parts = explode('/', $name);
        $className = array_pop($parts);
        $baseNamespace = 'App\\Http\\Controllers' . ($isApi ? '\\API' : '');
        $namespace = $baseNamespace . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');

        $filePath = base_path('app/Http/Controllers/' .
            ($isApi ? 'API/' : '') .
            str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

        return [$namespace, $filePath, $className];
    }

    /**
     * Ensure directory exists.
     */
    protected function createDirIfMissing(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Write content to file (create or overwrite).
     */
    protected function writeFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
    }

    /**
     * Generate the layout file when --complete is used.
     */
    protected function generateLayout(string $namespace, string $className, string $routeName): void
    {
        $layoutStub = $this->getLayoutStub('complete.stub');
        $layoutContent = $this->replacePlaceholders($layoutStub, $namespace, $className, $routeName);
        $layoutDir = base_path('resources/views/' . $routeName);
        $this->createDirIfMissing($layoutDir);
        $layoutPath = $layoutDir . '/default.odo.php';
        $this->writeFile($layoutPath, $layoutContent);
        $this->outputFilePath('📁 Layout', $layoutPath);
    }

    /**
     * Output a labeled file path relative to base path.
     */
    protected function outputFilePath(string $label, string $path): void
    {
        $this->line('<fg=yellow>' . $label . ':</> <fg=white>' . str_replace(base_path(), '', $path) . '</>');
    }

    /**
     * Output the controller type label.
     */
    protected function outputType(bool $isInvokable, bool $isResource, bool $isApi, bool $isComplete): void
    {
        $type = match (true) {
            $isInvokable => 'Invokable',
            $isResource => 'Resource',
            $isApi => 'API Resource',
            $isComplete => 'Complete',
            default => 'Standard'
        };

        $this->line('<fg=yellow>📌 Type:</> <fg=white>' . $type . ' controller</>');
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
