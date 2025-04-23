<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeRequestCommand extends Command
{
    protected static $defaultName = 'make:request';

    protected function configure()
    {
        $this
            ->setName('make:request')
            ->setDescription('Creates a new form request class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the request class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        $parts = explode('/', $name);

        $className = array_pop($parts);

        if (!str_ends_with($className, 'Request')) {
            $className .= 'Request';
        }

        $namespace = 'App\\Http\\Validations' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');

        $filePath = base_path() . '/app/Http/Validations/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php';

        if (file_exists($filePath)) {
            $output->writeln('<error>Request already exists!</error>');
            return Command::FAILURE;
        }

        $directoryPath = dirname($filePath);
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $content = $this->generateRequestContent($namespace, $className);

        file_put_contents($filePath, $content);

        $output->writeln('<info>Request created successfully</info>');

        return Command::SUCCESS;
    }

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
