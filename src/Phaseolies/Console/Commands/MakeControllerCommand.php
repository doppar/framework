<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MakeControllerCommand extends Command
{
    protected static $defaultName = 'make:controller';

    protected function configure()
    {
        $this
            ->setName('make:controller')
            ->setDescription('Creates a new controller class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the controller class.')
            ->addOption('invok', null, InputOption::VALUE_NONE, 'Create an invokable controller.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $isInvokable = $input->getOption('invok');

        $parts = explode('/', $name);

        $className = array_pop($parts);

        $namespace = 'App\\Http\\Controllers' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');

        $filePath = base_path() . '/app/Http/Controllers/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php';

        if (file_exists($filePath)) {
            $output->writeln('<error>Controller already exists!</error>');
            return Command::FAILURE;
        }

        $directoryPath = dirname($filePath);
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $content = $this->generateControllerContent($namespace, $className, $isInvokable);

        file_put_contents($filePath, $content);

        $output->writeln('<info>Controller created successfully</info>');

        return Command::SUCCESS;
    }

    protected function generateControllerContent(string $namespace, string $className, bool $isInvokable): string
    {
        if ($isInvokable) {
            return $this->generateInvokableControllerContent($namespace, $className);
        }

        return $this->generateRegularControllerContent($namespace, $className);
    }

    protected function generateRegularControllerContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use App\Http\Controllers\Controller;

class {$className} extends Controller
{
    //
}
EOT;
    }

    protected function generateInvokableControllerContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Phaseolies\Http\Request;
use App\Http\Controllers\Controller;

class {$className} extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request \$request)
    {
        //
    }
}
EOT;
    }
}
