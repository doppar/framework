<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Http\Controllers\Controller;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class ViewCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'view:cache';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Precompile all Blade views into cached files';

    /**
     * Maximum number of files to show in output
     */
    protected const MAX_SHOW_FILES = 5;

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $startTime = microtime(true);
        $this->newLine();

        try {
            $viewPath = base_path('resources/views');
            $cachePath = base_path('storage/framework/views');

            // Validate directories
            $this->ensureDirectoriesExist($viewPath, $cachePath);

            // Compile views
            $viewFiles = $this->getAllViewFiles($viewPath);
            $results = $this->compileViews($viewFiles);

            // Show results
            $this->showResults($results, count($viewFiles));

            $executionTime = microtime(true) - $startTime;
            $this->newLine();
            $this->line(sprintf(
                "<fg=yellow>⏱ Time:</> <fg=white>%.4fs</> <fg=#6C7280>(%d μs)</>",
                $executionTime,
                (int) ($executionTime * 1000000)
            ));
            $this->newLine();

            return 0;
        } catch (RuntimeException $e) {
            $this->line('<bg=red;options=bold> ERROR </> ' . $e->getMessage());
            $this->newLine();
            return 1;
        }
    }

    /**
     * Ensure required directories exist
     */
    protected function ensureDirectoriesExist(string $viewPath, string $cachePath): void
    {
        if (!is_dir($viewPath)) {
            throw new RuntimeException("View directory does not exist: $viewPath");
        }

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
    }

    /**
     * Compile all view files
     */
    protected function compileViews(array $viewFiles): array
    {
        $viewCompiler = new Controller();
        $results = ['success' => [], 'failed' => []];

        foreach ($viewFiles as $file) {
            $relativePath = str_replace(base_path('resources/views') . '/', '', $file);
            $viewName = str_replace(['/', '.blade.php'], ['.', ''], $relativePath);

            try {
                $compiledFile = $viewCompiler->prepare($viewName);
                $results['success'][] = basename($compiledFile);
            } catch (\Exception $e) {
                $results['failed'][basename($file)] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Display compilation results
     */
    protected function showResults(array $results, int $totalFiles): void
    {
        $this->line('<bg=blue;options=bold> COMPILING </> Blade templates');
        $this->newLine();

        // Show first few successful compilations
        $shownSuccess = array_slice($results['success'], 0, self::MAX_SHOW_FILES);
        foreach ($shownSuccess as $file) {
            $this->line('<fg=green>✓ Compiled:</> <fg=white>' . $file . '</>');
        }

        // Show failed compilations
        foreach ($results['failed'] as $file => $error) {
            $this->line('<fg=red>✖ Failed:</> <fg=white>' . $file . '</> <fg=red>' . $error . '</>');
        }

        // Show summary
        $this->newLine();
        $this->line('<fg=yellow>📊 Summary:</>');
        $this->line('- <fg=green>Success:</> ' . count($results['success']));
        $this->line('- <fg=red>Failed:</> ' . count($results['failed']));
        $this->line('- <fg=blue>Total:</> ' . $totalFiles);
    }

    /**
     * Recursively get all Blade view files
     */
    protected function getAllViewFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }
}
