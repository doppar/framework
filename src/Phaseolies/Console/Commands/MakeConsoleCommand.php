<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;

class MakeConsoleCommand extends Command
{
    protected static $defaultName = 'make:schedule';

    protected function configure()
    {
        $this
            ->setName('make:schedule')
            ->setDescription('Creates a new console command class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the console class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        $parts = explode('/', $name);

        $className = array_pop($parts);

        $namespace = 'App\\Schedule\\Commands' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');

        $filePath = base_path() . '/app/Schedule/Commands/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php';

        if (file_exists($filePath)) {
            $output->writeln('<error>Command already exists!</error>');
            return Command::FAILURE;
        }

        $directoryPath = dirname($filePath);
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $content = $this->generateCommandContent($namespace, $className);

        file_put_contents($filePath, $content);

        $output->writeln('<info>Command created successfully</info>');

        return Command::SUCCESS;
    }

    protected function generateCommandContent(string $namespace, string $className): string
    {
        return $this->generateRegularControllerContent($namespace, $className);
    }

    protected function convertToKebabCase(string $input): string
    {
        $input = str_replace('Command', '', $input);

        $output = preg_replace('/(?<!^)([A-Z])/', '-$1', $input);

        return strtolower($output);
    }

    protected function generateRegularControllerContent(string $namespace, string $className): string
    {
        $commandName = $this->convertToKebabCase($className);

        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Console\Schedule\Command;

class {$className} extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected \$name = 'doppar:{$commandName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected \$description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return 0;
    }
}

EOT;
    }
}
