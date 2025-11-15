<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class MakeJobCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:job {name}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new Job class';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function () {
            $name = $this->argument('name');
            $parts = explode('/', $name);
            $className = array_pop($parts);

            // Ensure class name ends with Job
            if (!str_ends_with($className, 'Job')) {
                $className .= 'Job';
            }

            $namespace = 'App\\Jobs' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Jobs/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            // Check if Job already exists
            if (file_exists($filePath)) {
                $this->displayError('Job already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return Command::FAILURE;
            }

            // Create directory if needed
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            // Generate and save Job class
            $content = $this->generateJobContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Job created successfully');
            $this->line('<fg=yellow>📦 File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>⚙️  Class:</> <fg=white>' . $className . '</>');

            return Command::SUCCESS;
        });
    }

    /**
     * Generate Job class content.
     */
    protected function generateJobContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Doppar\Queue\Job;
use Doppar\Queue\Dispatchable;
// use Doppar\Queue\Attributes\Queueable;

// #[Queueable(tries: 3, retryAfter: 60, delayFor: 300, onQueue: 'default')]
class {$className} extends Job
{
    use Dispatchable;

    /**
     * Create a new job instance.
     */
    public function __construct(){}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        //
    }

    /**
     * Handle a job failure.
     *
     * @param \\Throwable \$exception
     * @return void
     */
    public function failed(\\Throwable \$exception): void
    {
        //
    }
}

EOT;
    }
}
