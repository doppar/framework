<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MakeMiddlewareCommand extends Command
{
    protected static $defaultName = 'make:middleware';

    protected function configure()
    {
        $this
            ->setName('make:middleware')
            ->setDescription('Creates a new middleware class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the middleware class.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        $parts = explode('/', $name);
        $className = array_pop($parts);

        $namespace = 'App\\Http\\Middleware' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');

        $filePath = base_path() . '/app/Http/Middleware/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php';

        if (file_exists($filePath)) {
            $output->writeln('<error>Middleware already exists!</error>');
            return Command::FAILURE;
        }

        $directoryPath = dirname($filePath);
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $content = $this->generateRouteMiddlewareContent($namespace, $className);

        file_put_contents($filePath, $content);

        $output->writeln('<info>Middleware created successfully</info>');

        return Command::SUCCESS;
    }

    protected function generateRouteMiddlewareContent(string $namespace, string $className): string
    {
        return <<<EOT
<?php

namespace {$namespace};

use Closure;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Middleware\Contracts\Middleware;

class {$className} implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request \$request
     * @param \Closure(\Phaseolies\Http\Request) \$next
     * @return Response
     */
    public function __invoke(Request \$request, Closure \$next): Response
    {
        return \$next(\$request);
    }
}
EOT;
    }
}
