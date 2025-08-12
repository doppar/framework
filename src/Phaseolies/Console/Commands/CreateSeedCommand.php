<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class CreateSeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:seeder {name}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Creates a new seeder class.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $name = $this->argument('name');
            $filePath = base_path('database/seeders/' . $name . '.php');

            if (file_exists($filePath)) {
                $this->displayError('Seed file already exists!');
                return 1;
            }

            $content = $this->generateSeedContent($name);
            file_put_contents($filePath, $content);

            $this->displaySuccess('Seed file created successfully');
            $this->line("<fg=yellow>📁 File:</> <fg=white>{$filePath}</>");
            return 0;
        });
    }

    /**
     * Generate the content for the seeder class.
     *
     * @param string \$className
     * @return string
     */
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
