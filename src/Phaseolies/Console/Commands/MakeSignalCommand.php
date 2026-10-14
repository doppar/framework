<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class MakeSignalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:signal {name} {--settle} {--connection=}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new signal class';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            [$name, $parts, $className] = $this->splitGeneratedName((string) $this->argument('name'));
            $connection = $this->option('connection');
            $connection = is_string($connection) && $connection !== '' ? $connection : null;

            if ($className === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $className)) {
                $this->displayError('The signal name must be a valid class name.');
                return Command::FAILURE;
            }

            if ($connection !== null && !preg_match('/^[A-Za-z0-9_.\-]+$/', $connection)) {
                $this->displayError('The connection name may only contain letters, numbers, dots, dashes and underscores.');
                return Command::FAILURE;
            }

            $namespace = 'App\\Signals' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = $this->generatedFilePath('src/Signals', $name);

            // Check if signal already exists
            if (file_exists($filePath)) {
                $this->displayError('Signal already exists at:');
                $this->line('<fg=white>' . $this->relativePath($filePath) . '</>');
                return Command::FAILURE;
            }

            // Create directory if needed
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            // A connection only makes sense for a signal that waits for a transaction
            $settle = (bool) $this->option('settle') || $connection !== null;

            // Generate and save signal class
            $content = $this->generateSignalContent($namespace, $className, $settle, $connection);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Signal created successfully');
            $this->line('<fg=yellow>📁 File:</> <fg=white>' . $this->relativePath($filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Signal Class:</> <fg=white>' . $className . '</>');

            if ($settle) {
                $this->newLine();
                $this->line('<fg=yellow>⏳ Delivery:</> <fg=white>after the transaction commits'
                    . ($connection !== null ? " (connection: {$connection})" : '') . '</>');
            }

            return Command::SUCCESS;
        });
    }

    /**
     * Generate signal class content.
     *
     * @param string $namespace
     * @param string $className
     * @param bool $settle
     * @param string|null $connection
     * @return string
     */
    protected function generateSignalContent(
        string $namespace,
        string $className,
        bool $settle = false,
        ?string $connection = null
    ): string {
        $import = '';
        $attribute = '';

        if ($settle) {
            $import = "\nuse Phaseolies\\Signals\\Attributes\\DeliverOnSettle;\n";
            $attribute = $connection !== null
                ? "#[DeliverOnSettle(connection: '{$connection}')]\n"
                : "#[DeliverOnSettle]\n";
        }

        return <<<EOT
<?php

namespace {$namespace};
{$import}
{$attribute}class {$className}
{
    /**
     * Create a new signal instance
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
}

EOT;
    }
}
