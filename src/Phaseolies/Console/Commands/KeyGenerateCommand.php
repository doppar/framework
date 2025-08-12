<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use RuntimeException;

class KeyGenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'key:generate';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Generate a new application key and set it in the .env file';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $randomKey = base64_encode(random_bytes(32));
            $envPath = base_path() . '/.env';

            if (!file_exists($envPath)) {
                throw new RuntimeException('.env file not found!');
            }

            $envContent = file_get_contents($envPath);
            $newEnvContent = preg_replace(
                '/^APP_KEY=.*$/m',
                "APP_KEY=base64:$randomKey",
                $envContent
            );

            if (file_put_contents($envPath, $newEnvContent) === false) {
                throw new RuntimeException('Failed to update .env file.');
            }

            $this->displaySuccess('Application key set successfully');
            $this->line("<fg=yellow>🔑 Key:</> <fg=white>base64:$randomKey</>");
            return 0;
        });

        return 0;
    }
}
