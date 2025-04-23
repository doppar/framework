<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeProviderCommand extends Command
{
    protected static $defaultName = 'make:provider';

    protected function configure()
    {
        $this
            ->setName('make:provider')
            ->setDescription('Creates a new service provider class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the provider class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        $namespace = 'App\\Providers';
        $filePath = base_path() . '/app/Providers/' . $name . '.php';

        if (file_exists($filePath)) {
            $output->writeln('<error>Provider already exists!</error>');
            return Command::FAILURE;
        }

        $directoryPath = dirname($filePath);
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $content = $this->generateProviderContent($namespace, $name);

        file_put_contents($filePath, $content);

        $output->writeln('<info>Provider created successfully</info>');

        return Command::SUCCESS;
    }

    protected function generateProviderContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Providers\ServiceProvider;

class {$className} extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

     /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
EOT;
    }
}
