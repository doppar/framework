<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use RuntimeException;

class KeyGenerateCommand extends Command
{
    protected static $defaultName = 'key:generate';

    protected function configure()
    {
        $this
            ->setName('key:generate')
            ->setDescription('Generate a new application key and set it in the .env file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $randomKey = base64_encode(random_bytes(32));

        $envPath = base_path() . '/.env';

        if (!file_exists($envPath)) {
            throw new RuntimeException('.env file not found!');
        }

        $envContent = file_get_contents($envPath);
        $newEnvContent = preg_replace('/^APP_KEY=.*$/m', "APP_KEY=base64:$randomKey", $envContent);

        if (file_put_contents($envPath, $newEnvContent) === false) {
            throw new RuntimeException('Failed to update .env file.');
        }

        $output->writeln("<info>Application key set successfully: base64:$randomKey</info>");

        return Command::SUCCESS;
    }
}
