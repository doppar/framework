<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class MakeRequestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:request {name}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new form request class';

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

            // Ensure class name ends with Request
            if (!str_ends_with($className, 'Request')) {
                $className .= 'Request';
            }

            $namespace = 'App\\Http\\Validations' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Http/Validations/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            // Check if request already exists
            if (file_exists($filePath)) {
                $this->displayError('Request already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return Command::FAILURE;
            }

            // Create directory if needed
            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            // Generate and save request class
            $content = $this->generateRequestContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Request created successfully');
            $this->line('<fg=yellow>📁 File:</> <fg=white>' . str_replace(base_path('/'), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Class:</> <fg=white>' . $className . '</>');

            return Command::SUCCESS;
        });
    }

    /**
     * Generate request class content.
     */
    protected function generateRequestContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Http\Validation\FormRequest;

class {$className} extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }
}

EOT;
    }
}
