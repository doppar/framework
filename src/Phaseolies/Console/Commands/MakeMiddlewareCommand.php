<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use RuntimeException;

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
        $startTime = microtime(true);
        $this->newLine();

        try {
            $name = $this->argument('name');
            $parts = explode('/', $name);
            $className = array_pop($parts);
            $namespace = 'App\\Http\\Middleware' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Http/Middleware/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            // Check if middleware already exists
            if (file_exists($filePath)) {
                $this->line('<bg=red;options=bold> ERROR </> Middleware already exists at:');
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

            // Generate and save middleware class
            $content = $this->generateMiddlewareContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->line('<bg=green;options=bold> SUCCESS </> Middleware created successfully');
            $this->newLine();
            $this->line('<fg=yellow>🛡️  File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>🔒 Class:</> <fg=white>' . $className . '</>');

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