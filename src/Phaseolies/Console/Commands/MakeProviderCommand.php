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
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            [$name, $parts, $className] = $this->splitGeneratedName((string) $this->argument('name'));
            $namespace = 'App\\Providers' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = $this->generatedFilePath('app/Providers', $name);

            // Check if provider already exists
            if (file_exists($filePath)) {
                $this->displayError('Provider already exists at:');
                $this->line('<fg=white>' . $this->relativePath($filePath) . '</>');
                return Command::FAILURE;
            }

            // Create directory if needed
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            // Generate and save provider class
            $content = $this->generateProviderContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Provider created successfully');
            $this->line('<fg=yellow>📦 File:</> <fg=white>' . $this->relativePath($filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Class:</> <fg=white>' . $className . '</>');

            return Command::SUCCESS;
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
// use Phaseolies\Providers\GhostableProvider;

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
