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
    protected $name = 'make:controller {name} {--i}';

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
        $startTime = microtime(true);
        $this->newLine();

        try {
            $name = $this->argument('name');
            $isInvokable = $this->option('i');

            $parts = explode('/', $name);
            $className = array_pop($parts);
            $namespace = 'App\\Http\\Controllers' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Http/Controllers/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            if (file_exists($filePath)) {
                $this->line('<bg=red;options=bold> ERROR </> Controller already exists at:');
                $this->newLine();
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                $this->newLine();
                return 1;
            }

            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            $content = $this->generateControllerContent($namespace, $className, $isInvokable);
            file_put_contents($filePath, $content);

            $this->line('<bg=green;options=bold> SUCCESS </> Controller created successfully');
            $this->newLine();
            $this->line('<fg=yellow>📁 File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Type:</> <fg=white>' . ($isInvokable ? 'Invokable' : 'Standard') . ' controller</>');

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
     * Generate controller content based on type.
     */
    protected function generateControllerContent(string $namespace, string $className, bool $isInvokable): string
    {
        return $isInvokable
            ? $this->generateInvokableControllerContent($namespace, $className)
            : $this->generateRegularControllerContent($namespace, $className);
    }

    /**
     * Generate standard controller content.
     */
    protected function generateRegularControllerContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use App\Http\Controllers\Controller;

class {$className} extends Controller
{
    //
}

EOT;
    }

    /**
     * Generate invokable controller content.
     */
    protected function generateInvokableControllerContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Http\Request;
use App\Http\Controllers\Controller;

class {$className} extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request \$request)
    {
        //
    }
}

EOT;
    }
}
