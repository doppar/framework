<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MakePolicyCommand extends Command
{
    protected static $defaultName = 'make:authorizer';

    protected function configure()
    {
        $this
            ->setName('make:authorizer')
            ->setDescription('Creates a new authorizer class.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the authorizer class.')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'The model that the authorizer applies to.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $model = $input->getOption('model');


        $parts = explode('/', $name);
        $className = array_pop($parts);

        $namespace = 'App\\Authorizers' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');

        $filePath = base_path() . '/app/Authorizers/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php';

        if (file_exists($filePath)) {
            $output->writeln('<error>Authorizer already exists!</error>');
            return Command::FAILURE;
        }

        $directoryPath = dirname($filePath);
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $content = $this->generateAuthorizerContent($namespace, $className, $model);

        file_put_contents($filePath, $content);

        $output->writeln('<info>Authorizer created successfully.</info>');

        return Command::SUCCESS;
    }

    protected function generateAuthorizerContent(string $namespace, string $className, ?string $model): string
    {
        $modelClass = $model ? 'App\\Models\\' . $model : 'mixed';
        $modelVar = lcfirst($model ?? 'model');
        $userVar = 'user';

        $methods = $model ? $this->getModelPolicyMethods($model, $modelVar, $userVar) : $this->getGenericPolicyMethods();

        return <<<EOT
<?php

namespace {$namespace};

use App\Models\User;
use {$modelClass};

class {$className}
{
    {$methods}
}
EOT;
    }

    protected function getModelPolicyMethods(string $model, string $modelVar, string $userVar): string
    {
        $modelType = 'App\\Models\\' . $model;

        return <<<EOT
    /**
     * Determine whether the user can view any models.
     *
     * @param \App\Models\User \${$userVar}
     * @return bool
     */
    public function viewAny(User \${$userVar}): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param \App\Models\User \${$userVar}
     * @param {$modelType} \${$modelVar}
     * @return bool
     */
    public function view(User \${$userVar}, {$model} \${$modelVar}): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     *
     * @param \App\Models\User \${$userVar}
     * @return bool
     */
    public function create(User \${$userVar}): bool
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param \App\Models\User \${$userVar}
     * @param {$modelType} \${$modelVar}
     * @return bool
     */
    public function update(User \${$userVar}, {$model} \${$modelVar}): bool
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param \App\Models\User \${$userVar}
     * @param {$modelType} \${$modelVar}
     * @return bool
     */
    public function delete(User \${$userVar}, {$model} \${$modelVar}): bool
    {
        //
    }
EOT;
    }

    protected function getGenericPolicyMethods(): string
    {
        return <<<EOT
    /**
     * Determine whether the user can perform the action.
     *
     * @param \App\Models\User \$user
     * @return bool
     */
    public function performAction(User \$user): bool
    {
        //
    }
EOT;
    }
}
