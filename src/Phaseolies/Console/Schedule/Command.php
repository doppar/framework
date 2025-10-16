<?php

namespace Phaseolies\Console\Schedule;

use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\ProgressBar;
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
            } elseif (preg_match('/^(?:-([a-zA-Z])\|)?--(\w+)(?:=(.*))?$/', $definition, $m)) {
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

    /**
     * Execute command with timing and error handling.
     *
     * @param callable $callback
     * @return int
     */
    protected function executeWithTiming(callable $callback): int
    {
        $startTime = microtime(true);
        $this->newLine();

        try {
            $result = $callback();
            $this->displayExecutionTime($startTime);
            return $result ?? 0;
        } catch (\RuntimeException $e) {
            $this->displayError($e->getMessage());
            $this->displayExecutionTime($startTime);
            return 1;
        }
    }

    /**
     * Display a success message with standard formatting.
     *
     * @param string $message
     * @return void
     */
    protected function displaySuccess(string $message): void
    {
        $this->line("<bg=green;options=bold> SUCCESS </> {$message}");

        $this->newLine();
    }

    /**
     * Display an error message with standard formatting.
     *
     * @param string $message
     * @return void
     */
    protected function displayError(string $message): void
    {
        $this->line("<bg=red;options=bold> ERROR </> {$message}");

        $this->newLine();
    }

    /**
     * Display an warning message with standard formatting.
     *
     * @param string $message
     * @return void
     */
    protected function displayWarning(string $message): void
    {
        $this->line("<bg=yellow;options=bold> WARNING </> {$message}");

        $this->newLine();
    }
    /**
     * Display an info message with standard formatting.
     *
     * @param string $message
     * @return void
     */
    protected function displayInfo(string $message): void
    {
        $this->line("<fg=yellow> {$message}</>");

        $this->newLine();
    }

    /**
     * Display execution time with standard formatting.
     *
     * @param float $startTime
     * @return void
     */
    protected function displayExecutionTime(float $startTime): void
    {
        $executionTime = microtime(true) - $startTime;

        $this->newLine();

        $this->line(sprintf(
            "<fg=yellow>⏱ Time:</> <fg=white>%.4fs</> <fg=#6C7280>(%d μs)</>",
            $executionTime,
            (int) ($executionTime * 1000000)
        ));

        $this->newLine();
    }

    /**
     * Execute operation with timing and optional success message.
     *
     * @param callable $operation
     * @param string|null $successMessage
     * @return int
     */
    protected function withTiming(callable $operation, ?string $successMessage = null): int
    {
        return $this->executeWithTiming(function () use ($operation, $successMessage) {
            $result = $operation();

            if ($successMessage) {
                $this->displaySuccess($successMessage);
            }

            return $result;
        });
    }


    /**
     * Prompt the user for confirmation.
     *
     * @param string $question
     * @param bool $default
     * @return bool
     */
    protected function confirm(string $question, bool $default = true): bool
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "<question>{$question}</question> " . ($default ? '[Y/n]' : '[y/N]') . " ",
            $default
        );

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Prompt the user for input.
     *
     * @param string $question
     * @param string|null $default
     * @return string
     */
    protected function ask(string $question, ?string $default = null): string
    {
        $helper = $this->getHelper('question');

        $questionText = "<question>{$question}</question>";
        if ($default !== null) {
            $questionText .= " [{$default}]";
        }
        $questionText .= " ";

        $question = new Question($questionText, $default);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Prompt the user for input but hide the answer.
     *
     * @param string $question
     * @return string
     */
    protected function secret(string $question): string
    {
        $helper = $this->getHelper('question');
        $question = new Question("<question>{$question}</question> ");
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Give the user a single choice from an array of answers.
     *
     * @param string $question
     * @param array $choices
     * @param mixed $default
     * @return mixed
     */
    protected function choice(string $question, array $choices, $default = null)
    {
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion("<question>{$question}</question>", $choices, $default);
        $question->setErrorMessage('Choice %s is invalid.');

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Give the user multiple choices from an array of answers.
     *
     * @param string $question
     * @param array $choices
     * @param mixed $default
     * @return array
     */
    protected function multipleChoice(string $question, array $choices, $default = null): array
    {
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion("<question>{$question}</question>", $choices);
        $question->setMultiselect(true);
        $question->setErrorMessage('Choice %s is invalid.');

        // For multiselect, default as null
        if ($default !== null) {
            $question->setDefault($default);
        }

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Create a progress bar instance.
     *
     * @param int $max
     * @return \Symfony\Component\Console\Helper\ProgressBar
     */
    protected function createProgressBar(int $max = 0)
    {
        return new ProgressBar($this->output, $max);
    }

    /**
     * Create a table instance.
     *
     * @return \Symfony\Component\Console\Helper\Table
     */
    protected function createTable()
    {
        return new Table($this->output);
    }
}
