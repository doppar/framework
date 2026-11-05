<?php

namespace Phaseolies\Console\Support;

use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Formatter\OutputFormatter;

trait InteractsWithMigrations
{
    /**
     * Resolve the connection the command should operate on
     *
     * @return string
     */
    protected function resolveConnection(): string
    {
        return $this->option('connection') ?: config('database.default');
    }

    /**
     * Read an integer option, or null when it was not given
     *
     * @param string $name
     * @return int|null
     */
    protected function integerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        if (!ctype_digit((string) $value)) {
            throw new \RuntimeException("The --{$name} option must be a positive integer.");
        }

        return (int) $value;
    }

    /**
     * Ask before a destructive command runs in production (or always, when
     * $always is set). --force skips the question.
     *
     * @param string $warning
     * @param bool $always
     * @return bool
     */
    protected function confirmToProceed(string $warning, bool $always = false): bool
    {
        $pretending = $this->input->hasOption('pretend') && $this->option('pretend');

        if ($this->option('force') || $pretending) {
            return true;
        }

        if (!$always && config('app.env', 'production') !== 'production') {
            return true;
        }

        $this->displayWarning($warning);

        if (!$this->isInteractive()) {
            $this->displayError('Refusing to continue without a terminal. Pass --force to run it anyway.');

            return false;
        }

        if (!$this->confirm('Are you sure you want to proceed?', false)) {
            $this->displayInfo('Command cancelled');

            return false;
        }

        $this->newLine();

        return true;
    }

    /**
     * Build the callback the Migrator reports progress to
     *
     * @return \Closure
     */
    protected function progressReporter(): \Closure
    {
        return function (string $event, string $migration, array $info): void {
            switch ($event) {
                case 'ran':
                    $this->progressLine($migration, $info['time'], 'DONE', 'green');
                    break;
                case 'rolled_back':
                    $this->progressLine($migration, $info['time'], 'ROLLED BACK', 'yellow');
                    break;
                case 'pretend':
                    $this->pretendLines($migration, $info['queries']);
                    break;
            }
        };
    }

    /**
     * Print "name ........ 12ms DONE", padded to the terminal width
     *
     * @param string $migration
     * @param int $milliseconds
     * @param string $label
     * @param string $color
     * @return void
     */
    protected function progressLine(string $migration, int $milliseconds, string $label, string $color): void
    {
        $name = preg_replace('/\.php$/', '', $migration);
        $time = $milliseconds . 'ms';
        $dots = max(1, (new Terminal())->getWidth() - mb_strlen($name) - mb_strlen($time) - mb_strlen($label) - 6);

        $this->line(sprintf(
            '  <fg=white>%s</> <fg=gray>%s</> <fg=gray>%s</> <fg=%s;options=bold>%s</>',
            $name,
            str_repeat('.', $dots),
            $time,
            $color,
            $label
        ));
    }

    /**
     * Print the SQL a migration would have executed
     *
     * @param string $migration
     * @param array $queries
     * @return void
     */
    protected function pretendLines(string $migration, array $queries): void
    {
        $this->line('  <fg=white;options=bold>' . preg_replace('/\.php$/', '', $migration) . '</>');

        if ($queries === []) {
            $this->line('    <fg=gray>(no statements)</>');
        }

        foreach ($queries as $query) {
            $this->line('    <fg=cyan>' . OutputFormatter::escape(rtrim(trim($query['sql']), ';')) . ';</>');

            if (!empty($query['bindings'])) {
                $this->line('    <fg=gray>bindings ' . OutputFormatter::escape(json_encode($query['bindings'])) . '</>');
            }
        }

        $this->newLine();
    }

    /**
     * Run the database seeders through the db:seed command
     *
     * @return int
     */
    protected function runSeeders(): int
    {
        $this->newLine();
        $this->line('<fg=yellow>🌱 Seeding database</>');

        return $this->getApplication()->find('db:seed')->run(new ArrayInput([]), $this->output);
    }
}
