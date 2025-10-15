<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Application;
use Phaseolies\Console\Schedule\Command;
use Symfony\Component\Process\Process;

class DopparInstallCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'package:install';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Install doppar packages interactively with user prompts';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        if ($this->confirm('Do you want to install authentication?', false)) {
            $this->installAuthentication();
        }

        if ($this->confirm('Do you want to install dopapr axios?', false)) {
            $this->installAxios();
        }

        $this->info('Doppar installation completed.');
        $this->newLine();
        $this->info('Version: v' . Application::VERSION);

        return 0;
    }

    /**
     * Install authentication using php pool make:auth
     *
     * @return void
     */
    protected function installAuthentication(): void
    {
        $this->info('Installing authentication...');

        $process = new Process(['php', 'pool', 'make:auth']);
        $process->setTimeout(300);

        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        if ($process->isSuccessful()) {
            $this->info('Authentication installed successfully.');
        }
    }

    /**
     * Install doppar axios
     *
     * @return void
     */
    protected function installAxios(): void
    {
        $this->info('Installing doppar axios...');

        $process1 = new Process(['composer', 'require', 'doppar/axios']);
        $process1->setTimeout(300);

        $process1->run(function ($type, $buffer) {
            echo $buffer;
        });

        $this->info('Axios installed successfully.');
    }
}
