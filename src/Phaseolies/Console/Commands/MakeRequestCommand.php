<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use RuntimeException;

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
        $startTime = microtime(true);
        $this->newLine();

        try {
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
                $this->line('<bg=red;options=bold> ERROR </> Request already exists at:');
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

            // Generate and save request class
            $content = $this->generateRequestContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->line('<bg=green;options=bold> SUCCESS </> Request created successfully');
            $this->newLine();
            $this->line('<fg=yellow>📁 File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>📌 Class:</> <fg=white>' . $className . '</>');

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
