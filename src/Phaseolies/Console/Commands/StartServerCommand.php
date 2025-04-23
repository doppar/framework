<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class StartServerCommand extends Command
{
    protected static $defaultName = 'start';
    protected static $defaultDescription = 'Start the Phaseolies development server.';
    protected const DEFAULT_PORT = 8000;
    protected const MAX_PORT_ATTEMPTS = 10;

    protected function configure(): void
    {
        $this
            ->setName('start')
            ->setDescription(self::$defaultDescription)
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_REQUIRED,
                'The port to run the server on',
                self::DEFAULT_PORT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $port = $this->determineAvailablePort(
            (int)$input->getOption('port'),
            $output
        );

        $output->writeln("<info>Starting server on http://localhost:$port</info>");

        $this->startServer($port, $output);

        return Command::SUCCESS;
    }

    private function determineAvailablePort(int $desiredPort, OutputInterface $output): int
    {
        $attempts = 0;
        $currentPort = $desiredPort;

        while ($attempts < self::MAX_PORT_ATTEMPTS) {
            if (!$this->isPortInUse($currentPort)) {
                if ($currentPort !== $desiredPort) {
                    $output->writeln(
                        "<comment>Port $desiredPort is in use. Using port $currentPort instead.</comment>"
                    );
                }
                return $currentPort;
            }

            $currentPort++;
            $attempts++;
        }

        throw new \RuntimeException(
            "Unable to find an available port after $attempts attempts. " .
                "Please specify a different port or close the conflicting application."
        );
    }

    private function startServer(int $port, OutputInterface $output): void
    {
        $process = new Process([
            'php',
            '-S',
            "localhost:$port",
            '-t',
            'public',
            'server.php'
        ]);

        $process->setTimeout(null);
        $process->start();

        $process->wait(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
    }

    private function isPortInUse(int $port): bool
    {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);

        if ($socket) {
            fclose($socket);
            return true;
        }

        return false;
    }
}
