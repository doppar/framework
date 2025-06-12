<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Phaseolies\Database\Migration\MigrationCreator;

class MakeModelCommand extends Command
{
    protected static $defaultName = 'make:model';

    protected MigrationCreator $creator;

    public function __construct(MigrationCreator $creator)
    {
        parent::__construct();
        $this->creator = $creator;
    }

    protected function configure()
    {
        $this
            ->setName('make:model')
            ->setDescription('Creates a new model class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the model class.')
            ->addOption('migration', 'm', InputOption::VALUE_NONE, 'Create a new migration file for the model');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $withMigration = $input->getOption('migration');
        $parts = explode('/', $name);
        $className = array_pop($parts);
        $namespace = 'App\\Models' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
        $filePath = base_path() . '/app/Models/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php';

        // Check if the model already exists
        if (file_exists($filePath)) {
            $output->writeln('<error>Model already exists</error>');
            return Command::FAILURE;
        }

        $directoryPath = dirname($filePath);

        if (!is_dir($directoryPath)) {
            $result = mkdir($directoryPath, 0755, true);
        }

        $content = $this->generateModelContent($namespace, $className);
        file_put_contents($filePath, $content);
        $output->writeln('<info>Model created successfully</info>');

        if ($withMigration) {
            $tableName = str()->snake($className);
            $migrationName = "create_{$tableName}_table";

            $this->createMigration($migrationName, $tableName, $output);
        }

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

    protected function createMigration(string $name, string $table, OutputInterface $output): void
    {
        $file = $this->creator->create(
            $name,
            $this->getMigrationPath(),
            $table,
            true
        );

        $output->writeln("<info>Migration created:</info> {$file}");
    }

    protected function getMigrationPath(): string
    {
        return base_path('database/migrations');
    }
}
