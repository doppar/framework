<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class MakeProviderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:provider {name}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new service provider class';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $name = $this->argument('name');
            $namespace = 'App\\Providers';
            $filePath = base_path('app/Providers/' . $name . '.php');

            // Check if provider already exists
            if (file_exists($filePath)) {
                $this->displayError('Provider already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return 1;
            }

            // Create directory if needed
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            // Generate and save provider class
            $content = $this->generateProviderContent($namespace, $name);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Provider created successfully');
            $this->line('<fg=yellow>📦 File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Class:</> <fg=white>' . $name . '</>');

            return 0;
        });
    }

    /**
     * Generate provider class content.
     */
    protected function generateProviderContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Providers\ServiceProvider;

class {$className} extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}

EOT;
    }
}
