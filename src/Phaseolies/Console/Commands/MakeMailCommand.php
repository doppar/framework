<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use RuntimeException;

class MakeMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:mail {name}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new Mailable class';

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

            // Ensure class name ends with Mail
            if (!str_ends_with($className, 'Mail')) {
                $className .= 'Mail';
            }

            $namespace = 'App\\Mail' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Mail/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            // Check if Mailable already exists
            if (file_exists($filePath)) {
                $this->line('<bg=red;options=bold> ERROR </> Mailable already exists at:');
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

            // Generate and save Mailable class
            $content = $this->generateMailContent($namespace, $className);
            file_put_contents($filePath, $content);

            $this->line('<bg=green;options=bold> SUCCESS </> Mailable created successfully');
            $this->newLine();
            $this->line('<fg=yellow>📧 File:</> <fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
            $this->newLine();
            $this->line('<fg=yellow>✉️  Class:</> <fg=white>' . $className . '</>');

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
     * Generate Mailable class content.
     */
    protected function generateMailContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Support\Mail\Mailable;
use Phaseolies\Support\Mail\Mailable\Subject;
use Phaseolies\Support\Mail\Mailable\Content;

class {$className} extends Mailable
{
    /**
     * Create a new message instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Define mail subject
     * @return Phaseolies\Support\Mail\Mailable\Subject
     */
    public function subject(): Subject
    {
        return new Subject(
            subject: 'New Mail'
        );
    }

    /**
     * Set the message body and data
     * @return Phaseolies\Support\Mail\Mailable\Content
     */
    public function content(): Content
    {
        return new Content(
            view: 'Optional view.name',
            data: 'Optional data'
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachment(): array
    {
        return [];
    }
}

EOT;
    }
}
