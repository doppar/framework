<?php

namespace Phaseolies\Console\Schedule;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

abstract class Command extends SymfonyCommand
{
    /**
     * The name and name of the console command.
     *
     * @var string
     */
    protected $name;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description;

    /**
     * The input interface implementation.
     *
     * @var InputInterface
     */
    protected $input;

    /**
     * The output interface implementation.
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->parseSignature();
    }

    /**
     * Parse the input
     *
     * @return void
     */
    protected function parseSignature(): void
    {
        if (empty($this->name)) {
            throw new \LogicException('Command must have a name');
        }

        $name = preg_replace('/\s+/', ' ', trim($this->name));

        preg_match('/^\S+/', $name, $nameMatch);
        $this->setName($nameMatch[0]);

        preg_match_all('/{([^}]+)}/', $name, $matches);
        $definitions = $matches[1];

        foreach ($definitions as $definition) {
            $description = '';
            if (strpos($definition, ':') !== false) {
                [$definition, $description] = explode(':', $definition, 2);
                $description = trim($description);
            }

            $definition = trim($definition);

            if (preg_match('/^(\w+)(\?)?$/', $definition, $m)) {
                $this->addArgument(
                    $m[1],
                    !empty($m[2]) ? InputArgument::OPTIONAL : InputArgument::REQUIRED,
                    $description
                );
            }

            elseif (preg_match('/^(?:-([a-zA-Z])\|)?--(\w+)(?:=(.*))?$/', $definition, $m)) {
                $shortcut = $m[1] ?? null;
                $name = $m[2];
                $default = $m[3] ?? null;

                $mode = $default !== null ? InputOption::VALUE_REQUIRED : InputOption::VALUE_NONE;

                $this->addOption($name, $shortcut, $mode, $description, $default);
            }
        }

        if ($this->description) {
            $this->setDescription($this->description);
        }
    }

    /**
     * Get the command name from name.
     *
     * @return string
     */
    protected function getCommandName(): string
    {
        if (empty($this->name)) {
            throw new \LogicException(sprintf(
                'The command defined in "%s" cannot have an empty name.',
                static::class
            ));
        }

        return trim(explode(' ', $this->name)[0]);
    }

    /**
     * Execute the console command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        return $this->handle();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    abstract protected function handle(): int;

    /**
     * Get the value of a command argument.
     *
     * @param string|null $key
     * @return string|array|null
     */
    protected function argument($key = null)
    {
        if (is_null($key)) {
            return $this->input->getArguments();
        }

        return $this->input->getArgument($key);
    }

    /**
     * Get the value of a command option.
     *
     * @param string|null $key
     * @return string|array|bool|null
     */
    protected function option($key = null)
    {
        if (is_null($key)) {
            return $this->input->getOptions();
        }

        return $this->input->getOption($key);
    }

    /**
     * Write a string as information output.
     *
     * @param string $string
     * @return void
     */
    protected function info($string): void
    {
        $this->output->writeln("<info>{$string}</info>");
    }

    /**
     * Write a string as error output.
     *
     * @param string $string
     * @return void
     */
    protected function error($string): void
    {
        $this->output->writeln("<error>{$string}</error>");
    }

    /**
     * Write a string as comment output.
     *
     * @param string $string
     * @return void
     */
    protected function comment($string): void
    {
        $this->output->writeln("<comment>{$string}</comment>");
    }

    /**
     * Write a string as standard output.
     *
     * @param string $string
     * @param string|null $style
     * @return void
     */
    protected function line(string $string, ?string $style = null): void
    {
        $styled = $style ? "<{$style}>{$string}</{$style}>" : $string;

        $this->output->writeln($styled);
    }

    /**
     * Write a blank line to the output.
     *
     * @param int $count Number of newlines to write
     * @return void
     */
    protected function newLine($count = 1): void
    {
        $this->output->write(str_repeat(PHP_EOL, $count));
    }
}
