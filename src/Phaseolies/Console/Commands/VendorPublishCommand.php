<?php

namespace Phaseolies\Console\Commands;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Phaseolies\Console\Schedule\Command;
use Phaseolies\Application;
use FilesystemIterator;
use Symfony\Component\Console\Formatter\OutputFormatter;

class VendorPublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'vendor:publish {--launcher=} {--tag=} {--force}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Publish any publishable assets from vendor packages';

    protected Application $app;

    /**
     * Files copied during the current run.
     *
     * @var int
     */
    protected int $published = 0;

    /**
     * Existing files left untouched during the current run.
     *
     * @var int
     */
    protected int $skipped = 0;

    /**
     * Files that could not be copied during the current run.
     *
     * @var int
     */
    protected int $failed = 0;

    public function __construct()
    {
        parent::__construct();
        $this->app = app();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $this->published = $this->skipped = $this->failed = 0;

            $launcher = $this->option('launcher');
            $tags = $this->parseTags($this->option('tag'));
            $force = (bool) $this->option('force');

            if ($launcher || !empty($tags)) {
                return $this->publishSelection($launcher ?: null, $tags, $force);
            }

            return $this->publishInteractively($force);
        });
    }

    /**
     * Split the --tag value into a list of unique tag names.
     *
     * Accepts a single tag or a comma separated list: --tag=a,b
     *
     * @param mixed $value
     * @return array<int, string>
     */
    protected function parseTags(mixed $value): array
    {
        $tags = [];

        foreach ((array) $value as $item) {
            foreach (explode(',', (string) $item) as $tag) {
                $tag = trim($tag);

                if ($tag !== '') {
                    $tags[$tag] = $tag;
                }
            }
        }

        return array_values($tags);
    }

    /**
     * Prompt the user to choose which registered launcher to publish,
     * falling back to publishing everything in non-interactive environments.
     *
     * @param bool $force
     * @return int
     */
    protected function publishInteractively(bool $force): int
    {
        $launchers = $this->publishableLaunchers();

        if (empty($launchers)) {
            $this->displayWarning('No publishable launchers found.');
            return Command::SUCCESS;
        }

        if (!$this->isInteractive()) {
            return $this->publishAll($force);
        }

        $choices = array_merge(['All'], $launchers);

        $choice = $this->choice('Which launcher would you like to publish?', $choices, 'All');

        if ($choice === 'All') {
            return $this->publishAll($force);
        }

        return $this->publishSelection($choice, [], $force);
    }

    /**
     * Get the class names of registered launchers that have publishable paths.
     *
     * @return array<int, string>
     */
    protected function publishableLaunchers(): array
    {
        $launchers = [];

        foreach ($this->app->getLaunchers() as $launcherInstance) {
            if (!empty($launcherInstance->pathsToPublish())) {
                $launchers[] = get_class($launcherInstance);
            }
        }

        return $launchers;
    }

    /**
     * Publish what the --launcher and/or --tag options select.
     *
     * @param string|null $launcher
     * @param array<int, string> $tags
     * @param bool $force
     * @return int
     */
    protected function publishSelection(?string $launcher, array $tags, bool $force = false): int
    {
        $launchers = $this->app->getLaunchers();

        if ($launcher !== null) {
            $instance = $this->app->getLauncher($launcher);

            if (!$instance) {
                $this->displayError("Unable to locate launcher: {$launcher}");
                $this->listPublishableLaunchers();

                return Command::FAILURE;
            }

            $launchers = [$instance];
        }

        $batches = [];

        if (empty($tags)) {
            $batches[] = ["launcher [{$launcher}]", $launchers[0]->pathsToPublish()];
        } else {
            $missing = [];

            foreach ($tags as $tag) {
                $paths = [];

                foreach ($launchers as $candidate) {
                    $paths = array_merge($paths, $candidate->pathsToPublish(null, $tag));
                }

                if (empty($paths)) {
                    $missing[] = $tag;
                    continue;
                }

                $batches[] = ["tag [{$tag}]", $paths];
            }

            if (!empty($missing)) {
                $this->reportUnknownTags($missing, $launchers, $launcher);

                return Command::FAILURE;
            }
        }

        $batches = array_values(array_filter($batches, fn(array $batch) => !empty($batch[1])));

        if (empty($batches)) {
            $this->displayWarning("Launcher [{$launcher}] has nothing to publish.");

            return Command::SUCCESS;
        }

        $this->publishBatches($batches, $force);

        $scope = count($batches) === 1
            ? 'for ' . $batches[0][0]
            : 'for ' . count($batches) . ' tags';

        return $this->summarize($scope);
    }

    /**
     * Publish every launcher's registered paths.
     *
     * @param bool $force
     * @return int
     */
    protected function publishAll(bool $force = false): int
    {
        $batches = [];

        foreach ($this->app->getLaunchers() as $launcher) {
            $paths = $launcher->pathsToPublish();

            if (!empty($paths)) {
                $batches[] = ['launcher [' . get_class($launcher) . ']', $paths];
            }
        }

        $this->publishBatches($batches, $force);

        $count = count($batches);

        return $this->summarize('from ' . $count . ' ' . ($count === 1 ? 'launcher' : 'launchers'));
    }

    /**
     * Publish each labelled group of paths under its own heading.
     *
     * @param array<int, array{0: string, 1: array}> $batches
     * @param bool $force
     * @return void
     */
    protected function publishBatches(array $batches, bool $force = false): void
    {
        foreach ($batches as [$label, $paths]) {
            $this->line('<options=bold>Publishing ' . OutputFormatter::escape($label) . '</>');
            $this->publishPaths($paths, $force);
            $this->newLine();
        }
    }

    /**
     * Print the final result and return the matching exit code.
     *
     * @param string $scope
     * @return int
     */
    protected function summarize(string $scope): int
    {
        if ($this->failed > 0) {
            $this->displayError($this->countOf($this->failed) . " could not be published {$scope}.");

            return Command::FAILURE;
        }

        if ($this->published > 0) {
            $message = 'Published ' . $this->countOf($this->published) . " {$scope}.";

            if ($this->skipped > 0) {
                $message .= " Skipped {$this->skipped} existing (use --force to overwrite).";
            }

            $this->displaySuccess($message);

            return Command::SUCCESS;
        }

        if ($this->skipped > 0) {
            $this->displayInfo(
                "Nothing published {$scope}: {$this->skipped} existing "
                    . ($this->skipped === 1 ? 'file was' : 'files were')
                    . ' left as is (use --force to overwrite).'
            );

            return Command::SUCCESS;
        }

        $this->displayInfo("Nothing to publish {$scope}.");

        return Command::SUCCESS;
    }

    /**
     * Explain that a tag is unknown and point at the tags that do exist.
     *
     * @param array<int, string> $missing
     * @param array $launchers
     * @param string|null $launcher
     * @return void
     */
    protected function reportUnknownTags(array $missing, array $launchers, ?string $launcher): void
    {
        $available = [];

        foreach ($launchers as $candidate) {
            $available = array_merge($available, $candidate->publishTags());
        }

        $available = array_values(array_unique($available));
        sort($available);

        foreach ($missing as $tag) {
            $where = $launcher !== null ? " in launcher [{$launcher}]" : '';

            $this->displayError("No publishable resources found for tag [{$tag}]{$where}.");

            $similar = array_values(array_filter(
                $available,
                fn(string $name) => str_contains($name, $tag) || levenshtein($name, $tag) <= 3
            ));

            if (!empty($similar)) {
                $this->line('Did you mean: <fg=yellow>' . implode('</>, <fg=yellow>', array_map(
                    [OutputFormatter::class, 'escape'],
                    $similar
                )) . '</>?');
                $this->newLine();
            }
        }

        if (!empty($available)) {
            $this->line('<options=bold>Available tags:</>');

            foreach ($available as $name) {
                $this->line('  ' . OutputFormatter::escape($name));
            }

            $this->newLine();
        }
    }

    /**
     * List the launchers that have something to publish.
     *
     * @return void
     */
    protected function listPublishableLaunchers(): void
    {
        $launchers = $this->publishableLaunchers();

        if (empty($launchers)) {
            return;
        }

        $this->line('<options=bold>Publishable launchers:</>');

        foreach ($launchers as $name) {
            $this->line('  ' . OutputFormatter::escape($name));
        }

        $this->newLine();
    }

    protected function publishPaths(array $paths, bool $force = false)
    {
        foreach ($paths as $key => $item) {
            $mappings = is_array($item) ? $item : [$key => $item];

            foreach ($mappings as $from => $to) {
                $method = is_dir($from) ? 'publishDirectory' : 'publishFile';
                $this->{$method}($from, $to, $force);
            }
        }
    }

    protected function publishDirectory(string $from, string $to, bool $force = false)
    {
        if (!is_dir($to) && !@mkdir($to, 0755, true) && !is_dir($to)) {
            $this->report('FAILED', 'red', "cannot create directory {$this->displayPath($to)}");
            $this->failed++;

            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $to . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
                    $this->report('FAILED', 'red', "cannot create directory {$this->displayPath($target)}");
                    $this->failed++;
                }
            } else {
                $this->publishFile($item->getPathname(), $target, $force);
            }
        }
    }

    protected function publishFile(string $from, string $to, bool $force = false)
    {
        if (!is_file($from)) {
            $this->report('MISSING', 'red', "source file not found: {$this->displayPath($from)}");
            $this->failed++;

            return;
        }

        $exists = file_exists($to);

        if ($exists && !$force) {
            $this->report('SKIPPED', 'yellow', $this->displayPath($to) . ' <fg=#6C7280>already exists</>');
            $this->skipped++;

            return;
        }

        $directory = dirname($to);

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            $this->report('FAILED', 'red', "cannot create directory {$this->displayPath($directory)}");
            $this->failed++;

            return;
        }

        if (!@copy($from, $to)) {
            $this->report('FAILED', 'red', "cannot write {$this->displayPath($to)}");
            $this->failed++;

            return;
        }

        $this->report(
            $exists ? 'OVERWRITTEN' : 'COPIED',
            'green',
            $this->displayPath($from) . ' <fg=#6C7280>→</> ' . $this->displayPath($to)
        );
        $this->published++;
    }

    /**
     * Write one aligned status line for a file.
     *
     * @param string $label
     * @param string $color
     * @param string $message
     * @return void
     */
    protected function report(string $label, string $color, string $message): void
    {
        $this->line(sprintf('  <fg=%s;options=bold>%s</> %s', $color, str_pad($label, 11), $message));
    }

    /**
     * Show a path relative to the application root and safe for console markup.
     *
     * @param string $path
     * @return string
     */
    protected function displayPath(string $path): string
    {
        $base = rtrim(base_path(), '/\\') . DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        return OutputFormatter::escape($path);
    }

    /**
     * Format a file count, e.g. "1 file" or "3 files".
     *
     * @param int $count
     * @return string
     */
    protected function countOf(int $count): string
    {
        return $count . ' ' . ($count === 1 ? 'file' : 'files');
    }
}
