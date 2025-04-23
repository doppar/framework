<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeModelCommand extends Command
{
    protected static $defaultName = 'make:model';

    protected function configure()
    {
        $this
            ->setName('make:model')
            ->setDescription('Creates a new model class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the model class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // Split the name by slashes to handle subfolders and class name
        $parts = explode('/', $name);

        // The class name is the last part (after the last slash)
        $className = array_pop($parts);

        // The namespace is the remaining parts joined by backslashes
        $namespace = 'App\\Models' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');

        // Construct the file path by replacing slashes with directory separators
        $filePath = base_path() . '/app/Models/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php';

        // Check if the model already exists
        if (file_exists($filePath)) {
            $output->writeln('<error>Model already exists</error>');
            return Command::FAILURE;
        }

        // Create necessary directories if they don't exist
        $directoryPath = dirname($filePath);

        if (!is_dir($directoryPath)) {
            $result = mkdir($directoryPath, 0755, true);
        }

        // Generate the content for the new model
        $content = $this->generateModelContent($namespace, $className);

        // Write the new model file to disk
        file_put_contents($filePath, $content);

        // Inform the user that the model has been created
        $output->writeln('<info>Model created successfully</info>');

        return Command::SUCCESS;
    }

    protected function generateModelContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Database\Eloquent\Model;

class {$className} extends Model
{
    //
}
EOT;
    }
}
