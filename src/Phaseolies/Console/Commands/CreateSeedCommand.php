<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateSeedCommand extends Command
{
    protected static $defaultName = 'make:seeder';

    protected function configure()
    {
        $this
            ->setName('make:seeder')
            ->setDescription('Creates a new seeder class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the seeder class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        $filePath = base_path('database/seeders/' . $name . '.php');

        if (file_exists($filePath)) {
            $output->writeln('<error>Seed file already exists!</error>');
            return Command::FAILURE;
        }

        $content = $this->generateSeedContent($name);

        file_put_contents($filePath, $content);

        $output->writeln('<info>Seed file created successfully</info>');

        return Command::SUCCESS;
    }

    protected function generateSeedContent(string $className): string
    {
        return <<<EOT
<?php

namespace Database\Seeders;

use Phaseolies\Database\Migration\Seeder;

class {$className} extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {

    }
}
EOT;
    }
}
