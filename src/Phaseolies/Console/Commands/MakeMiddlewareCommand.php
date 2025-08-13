<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class MakeMiddlewareCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:middleware {name}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new middleware class';

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
            $namespace = 'App\\Http\\Middleware' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Http/Middleware/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            // Check if middleware already exists
            if (file_exists($filePath)) {
                $this->displayError('Middleware already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return 1;
            }

            // Create directory if needed
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            // Generate and save middleware class
            $content = $this->generateMiddlewareContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Middleware created successfully');
            $this->line('<fg=yellow>🛡️  File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>🔒 Class:</> <fg=white>' . $className . '</>');

            return 0;
        });
    }

    /**
     * Generate middleware class content.
     */
    protected function generateMiddlewareContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Closure;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Middleware\Contracts\Middleware;

class {$className} implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request \$request
     * @param \Closure(\Phaseolies\Http\Request): Response \$next
     * @return Response
     */
    public function __invoke(Request \$request, Closure \$next): Response
    {
        return \$next(\$request);
    }
}

EOT;
    }
}