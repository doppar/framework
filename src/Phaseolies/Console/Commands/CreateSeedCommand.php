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
        $startTime = microtime(true);
        $this->newLine();

        $name = $this->argument('name');
        $filePath = base_path('database/seeders/' . $name . '.php');

        if (file_exists($filePath)) {
            $this->line('<bg=red;options=bold> ERROR </> Seed file already exists!');
            $this->newLine();
            return 1;
        }

        $content = $this->generateSeedContent($name);
        file_put_contents($filePath, $content);

        $this->line('<bg=green;options=bold> SUCCESS </> Seed file created successfully');
        $this->newLine();
        $this->line("<fg=yellow>📁 File:</> <fg=white>{$filePath}</>");

        $executionTime = microtime(true) - $startTime;
        $this->newLine();
        $this->line(sprintf(
            "<fg=yellow>⏱ Time:</> <fg=white>%.4fs</> <fg=#6C7280>(%d μs)</>",
            $executionTime,
            (int) ($executionTime * 1000000)
        ));
        $this->newLine();

        return 0;
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
