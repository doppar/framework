<?php

namespace Phaseolies\Console\Commands\Migrations;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Support\InteractsWithMigrations;
use Phaseolies\Database\Migration\Migrator;

class MigrateStatusCommand extends Command
{
    use InteractsWithMigrations;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'migrate:status {--connection=} {--pending} {--json}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Show which migrations have run. With --pending, exit 1 when some are still pending';

    /**
     * The migrator instance.
     *
     * @var Migrator
     */
    protected Migrator $migrator;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->migrator = app('migrator');
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $connection = $this->resolveConnection();

        try {
            $rows = $this->migrator->status($connection);
        } catch (\Throwable $e) {
            $this->displayError($e->getMessage());

            return Command::FAILURE;
        }

        $pending = array_values(array_filter($rows, fn($row) => !$row['ran']));
        $problems = array_values(array_filter($rows, fn($row) => $row['modified'] || $row['missing']));

        // Both flags are meant for CI and deploy scripts, so the exit code carries the answer.
        $exit = $this->option('pending') && $pending !== [] ? Command::FAILURE : Command::SUCCESS;

        if ($this->option('json')) {
            $this->output->writeln(json_encode([
                'connection' => $connection,
                'migrations' => $rows,
                'pending' => count($pending),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $exit;
        }

        $this->newLine();
        $this->line("<fg=yellow>📊 Migration status on connection: {$connection}</>");
        $this->newLine();

        if ($rows === []) {
            $this->displayInfo('No migrations found');

            return $exit;
        }

        $table = $this->createTable();
        $table->setHeaders(['Migration', 'Status', 'Batch', 'Ran at', 'Time']);

        foreach ($this->option('pending') ? $pending : $rows as $row) {
            $table->addRow([
                preg_replace('/\.php$/', '', $row['migration']),
                $this->statusLabel($row),
                $row['batch'] ?? '-',
                $row['ran_at'] ?? '-',
                $row['execution_time'] === null ? '-' : $row['execution_time'] . 'ms',
            ]);
        }

        $table->render();
        $this->newLine();

        $this->line(sprintf(
            '  <fg=green>%d ran</>, <fg=yellow>%d pending</>%s',
            count($rows) - count($pending),
            count($pending),
            $problems === [] ? '' : sprintf(', <fg=red>%d need attention</>', count($problems))
        ));

        if ($problems !== []) {
            $this->line('  <fg=gray>Modified = the file changed after it ran. Missing = it ran, but the file is gone.</>');
        }

        $this->newLine();

        return $exit;
    }

    /**
     * @param array $row
     * @return string
     */
    private function statusLabel(array $row): string
    {
        return match (true) {
            $row['missing'] => '<fg=red>Ran (file missing)</>',
            $row['modified'] => '<fg=yellow>Ran (modified)</>',
            $row['ran'] => '<fg=green>Ran</>',
            default => '<fg=yellow>Pending</>',
        };
    }
}
