<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class MakeAuthorizerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:authorizer {name} {--m=} {--model=}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Creates a new authorizer class.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $name = $this->argument('name');
            $model = $this->option('model') ?? $this->option('m');

            $parts = explode('/', $name);
            $className = array_pop($parts);

            $namespace = 'App\\Authorizers' . (count($parts) > 0 ? '\\' . implode('\\', $parts) : '');
            $filePath = base_path('app/Authorizers/' . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.php');

            if (file_exists($filePath)) {
                $this->displayError('Authorizer already exists at:');
                $this->line('<fg=white>' . str_replace(base_path(), '', $filePath) . '</>');
                return Command::FAILURE;
            }

            $directoryPath = dirname($filePath);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            $content = $this->generateAuthorizerContent($namespace, $className, $model);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Authorizer created successfully');
            $this->line('<fg=yellow>🛡️  File:</> <fg=white>' . str_replace(base_path('/'), '', $filePath) . '</>');
            $this->newLine();

            return Command::SUCCESS;
        });
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
