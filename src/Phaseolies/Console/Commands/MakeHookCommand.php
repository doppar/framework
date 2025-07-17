<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeHookCommand extends Command
{
    protected static $defaultName = 'make:hook';

    protected function configure()
    {
        $this
            ->setName('make:hook')
            ->setDescription('Creates a new model hook class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the hook class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $parts = explode('/', $name);
        $className = array_pop($parts);
        $namespace = 'App\\Hooks' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
        $filePath = base_path() . '/app/Hooks/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php';

        // Check if the hook already exists
        if (file_exists($filePath)) {
            $output->writeln('<error>Hook already exists</error>');
            return Command::FAILURE;
        }

        // Create directory if needed
        $directoryPath = dirname($filePath);
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        // Generate and save the hook class
        $content = $this->generateHookContent($namespace, $className);
        file_put_contents($filePath, $content);

        $output->writeln('<info>Hook created successfully:</info> ' . $filePath);
        return Command::SUCCESS;
    }

    protected function generateHookContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Database\Eloquent\Model;

class {$className}
{
    /**
     * Handle the incoming model hook
     *
     * @param Model \$model
     * @return void
     */
    public function handle(Model \$model): void
    {
        //
    }
}
EOT;
    }
}
