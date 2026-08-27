<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class MakeLauncherCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:launcher {name}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new service launcher class';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            [$name, $parts, $className] = $this->splitGeneratedName((string) $this->argument('name'));
            $namespace = 'App\\Launchers' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = $this->generatedFilePath('src/Launchers', $name);

            // Check if launcher already exists
            if (file_exists($filePath)) {
                $this->displayError('Launcher already exists at:');
                $this->line('<fg=white>' . $this->relativePath($filePath) . '</>');
                return Command::FAILURE;
            }

            // Create directory if needed
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            // Generate and save Launcher class
            $content = $this->generateProviderContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Launcher created successfully');
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

use Phaseolies\Launchers\ServiceLauncher;
// use Phaseolies\Launchers\GhostableLauncher;

class {$className} extends ServiceLauncher
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
     * Launch any application services.
     *
     * @return void
     */
    public function launch(): void
    {
        //
    }
}

EOT;
    }
}
