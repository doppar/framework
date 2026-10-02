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
use Phaseolies\Support\Facades\Log;
use Phaseolies\DI\Container;

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

            if (str_starts_with($definition, '-')) {
                $this->addOptionFromDefinition($definition, $description);
            } else {
                $this->addArgumentFromDefinition($definition, $description);
            }
        }

        if ($this->description) {
            $this->setDescription($this->description);
        }
    }

    /**
     * Register an argument from its signature definition:
     *
     *  name            required
     *  name?           optional
     *  name=guest      optional, with a default
     *  name*           required, accepts several values
     *  name?*          optional, accepts several values
     *
     * @param string $definition
     * @param string $description
     * @return void
     * @throws \LogicException
     */
    protected function addArgumentFromDefinition(string $definition, string $description): void
    {
        if (!preg_match('/^(\w+)(\?)?(\*)?(?:=(.*))?$/s', $definition, $m)) {
            throw $this->invalidDefinition($definition);
        }

        $name = $m[1];
        $optional = !empty($m[2]);
        $array = !empty($m[3]);
        $hasDefault = isset($m[4]);

        if ($array && $hasDefault) {
            throw $this->invalidDefinition($definition, 'an array argument cannot have a default');
        }

        $mode = ($optional || $hasDefault) ? InputArgument::OPTIONAL : InputArgument::REQUIRED;

        if ($array) {
            $mode |= InputArgument::IS_ARRAY;
        }

        $this->addArgument($name, $mode, $description, $hasDefault ? $m[4] : null);
    }

    /**
     * Register an option from its signature definition:
     *
     *  --force         flag
     *  -f|--force      flag with a shortcut
     *  --queue=        takes a value
     *  --queue=default takes a value, with a default
     *  --tag=*         takes a value and can be repeated: --tag=a --tag=b
     *  --cache!        negatable: --cache and --no-cache
     *  --cache!=true   negatable, defaulting to true
     *
     * @param string $definition
     * @param string $description
     * @return void
     * @throws \LogicException
     */
    protected function addOptionFromDefinition(string $definition, string $description): void
    {
        if (!preg_match('/^(?:-([a-zA-Z])\|)?--([\w-]+)(!)?(?:=(.*))?$/s', $definition, $m)) {
            throw $this->invalidDefinition($definition);
        }

        $shortcut = $m[1] !== '' ? $m[1] : null;
        $name = $m[2];
        $negatable = !empty($m[3]);
        $default = $m[4] ?? null;

        if ($negatable) {
            if ($default === null) {
                $this->addOption($name, $shortcut, InputOption::VALUE_NONE | InputOption::VALUE_NEGATABLE, $description);

                return;
            }

            if (!in_array($default, ['true', 'false'], true)) {
                throw $this->invalidDefinition($definition, 'a negatable option can only default to true or false');
            }

            $this->addOption($name, $shortcut, InputOption::VALUE_NONE | InputOption::VALUE_NEGATABLE, $description, $default === 'true');

            return;
        }

        if ($default === null) {
            $this->addOption($name, $shortcut, InputOption::VALUE_NONE, $description);

            return;
        }

        if ($default === '*') {
            $this->addOption($name, $shortcut, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, $description, []);

            return;
        }

        $this->addOption($name, $shortcut, InputOption::VALUE_REQUIRED, $description, $default);
    }

    /**
     * @param string $definition
     * @param string|null $reason
     * @return \LogicException
     */
    private function invalidDefinition(string $definition, ?string $reason = null): \LogicException
    {
        return new \LogicException(sprintf(
            'Invalid definition "{%s}" in the signature of [%s]%s.',
            $definition,
            static::class,
            $reason ? ': ' . $reason : ''
        ));
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

        if (!method_exists($this, 'handle')) {
            throw new \LogicException(
                sprintf('Command [%s] must have a handle() method.', static::class)
            );
        }

        try {
            $container = Container::getInstance();
            $result = $container->call([$this, 'handle']);

            return is_int($result) ? $result : self::SUCCESS;
        } catch (\Throwable $e) {
            // Logging must never hide the error being logged.
            try {
                Log::error($e);
            } catch (\Throwable) {
            }

            // Rethrown as is, so the real class, code and origin are reported instead of this line.
            throw $e;
        }
    }

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
     * @param int $count
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
     * Normalize a generated class/path name so both "/" and "\" work across platforms.
     *
     * @param string $name
     * @return string
     */
    protected function normalizeGeneratedName(string $name): string
    {
        $normalized = trim(str_replace('\\', '/', $name));
        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;

        return trim($normalized, '/');
    }

    /**
     * Split a generated class/path name into normalized name, path parts and final class name.
     *
     * @param string $name
     * @return array{string,array<int,string>,string}
     */
    protected function splitGeneratedName(string $name): array
    {
        $normalized = $this->normalizeGeneratedName($name);
        $parts = $normalized === '' ? [] : explode('/', $normalized);
        $className = array_pop($parts) ?? '';

        return [$normalized, $parts, $className];
    }

    /**
     * Build an absolute file path for a generated class file.
     *
     * @param string $baseDirectory
     * @param string $name
     * @param string $extension
     * @return string
     */
    protected function generatedFilePath(string $baseDirectory, string $name, string $extension = '.php'): string
    {
        $normalizedName = $this->normalizeGeneratedName($name);
        $relativePath = trim($baseDirectory, '/\\');

        if ($normalizedName !== '') {
            $relativePath .= '/' . $normalizedName;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath . $extension));
    }

    /**
     * Convert an absolute path into a forward-slash relative path with no leading slash.
     *
     * @param string $path
     * @param string|null $basePath
     * @return string
     */
    protected function relativePath(string $path, ?string $basePath = null): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedBase = rtrim(str_replace('\\', '/', $basePath ?? base_path()), '/');

        if ($normalizedPath === $normalizedBase) {
            return '';
        }

        if (str_starts_with($normalizedPath, $normalizedBase . '/')) {
            return substr($normalizedPath, strlen($normalizedBase) + 1);
        }

        return ltrim($normalizedPath, '/');
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
     * Determine if the command is running in an interactive environment.
     *
     * @return bool
     */
    protected function isInteractive(): bool
    {
        return $this->input->isInteractive();
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
