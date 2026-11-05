<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class MakeRuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:rule {name : The name of the rule class}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description =  'Create a new validation rule class';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            [$name, $parts, $className] = $this->splitGeneratedName((string) $this->argument('name'));
            $namespace = 'App\\Http\\Validations\\Rules' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = $this->generatedFilePath('src/Http/Validations/Rules', $name);

            // Check if rule already exists
            if (file_exists($filePath)) {
                $this->displayError('Rule already exists at:');
                $this->line('<fg=white>' . $this->relativePath($filePath) . '</>');
                return Command::FAILURE;
            }

            // Create directory if needed
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            // Generate and save rule class
            $content = $this->generateRuleContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Rule created successfully');
            $this->line('<fg=yellow>🛡️  File:</> <fg=white>' . $this->relativePath($filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>🔒 Class:</> <fg=white>' . $className . '</>');

            return Command::SUCCESS;
        });
    }

    /**
     * Generate rule class content.
     */
    protected function generateRuleContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Http\Validation\Contracts\RuleInterface;

class {$className} implements RuleInterface
{
    /**
     * Determine if the given field value passes the domain validation rule.
     *
     * @param string \$field The name of the field being validated.
     * @param mixed \$value The value of the field to validate.
     * @param array \$input The full input data array.
     * @return bool
     */
    public function passes(string \$field, mixed \$value, array \$input): bool
    {
        //
    }

     /**
     * Get the validation error message for the rule.
     *
     * @param string \$field The name of the field being validated.
     * @return string
     */
    public function message(string \$field): string
    {
        //
    }
}

EOT;
    }
}
