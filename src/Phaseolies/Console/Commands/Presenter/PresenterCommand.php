<?php

namespace Phaseolies\Console\Commands\Presenter;

use Phaseolies\Console\Schedule\Command;

class PresenterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:presenter {name}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new presenter class';

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
            $namespace = 'App\\Http\\Presenters' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Http/Presenters/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            if (file_exists($filePath)) {
                $this->displayError('Presenters already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return Command::FAILURE;
            }

            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            $content = $this->generatePresentersContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Presenters created successfully');
            $this->line('<fg=yellow>📁 File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();

            return Command::SUCCESS;
        });
    }

    /**
     * Generate controller content based on type.
     */
    protected function generatePresentersContent(string $namespace, string $className): string
    {
        return $this->generateRegularPresentersContent($namespace, $className);
    }

    /**
     * Generate standard controller content.
     */
    protected function generateRegularPresentersContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Support\Presenter\Presenter;

class {$className} extends Presenter
{
    /**
     * Transform the underlying model instance into an array
     *
     * @return array<string, mixed>
     */
    protected function toArray(): array
    {
        return [
            //
        ];
    }
}

EOT;
    }
}
