<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Support\Facades\Hash;

class MakePasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:password {password}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Generate a hashed password';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function () {
            $password = $this->argument('password');

            if (empty($password)) {
                $this->displayError('Password is required');
                return Command::FAILURE;
            }
            if (!is_string($password)) {
                $this->displayError('Password must be a string');
                return Command::FAILURE;
            }

            $hashedPassword = self::hash($password);

            $this->displaySuccess('Password hashed successfully');
            $this->line("<fg=yellow>🔑 Key:</> <fg=white>$hashedPassword</>");

            return Command::SUCCESS;
        });
    }

    /**
     * Hash a password using the configured algorithm and options.
     *
     * @param string $password
     * @return string
     */
    public static function hash(string $password): string
    {
        return (string) Hash::make($password);
    }
}
