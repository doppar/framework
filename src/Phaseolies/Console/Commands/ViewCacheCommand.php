<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phaseolies\Http\Controllers\Controller;

class ViewCacheCommand extends Command
{
    protected static $defaultName = 'view:cache';

    protected function configure()
    {
        $this
            ->setName('view:cache')
            ->setDescription('Precompile all views and store them in the storage/framework/views folder.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $viewPath = base_path() . '/resources/views';
        $cachePath = base_path() . '/storage/framework/views';

        if (!is_dir($viewPath)) {
            $output->writeln("<error>View directory does not exist: $viewPath</error>");
            return Command::FAILURE;
        }

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }

        $viewCompiler = new Controller();
        $viewFiles = $this->getAllViewFiles($viewPath);

        $counter = 0;
        foreach ($viewFiles as $file) {
            $counter++;
            $relativePath = str_replace(base_path() . '/resources/views/', '', $file);
            $viewName = str_replace(['/', '.blade.php'], ['.', ''], $relativePath);
            try {
                $compiledFile = $viewCompiler->prepare($viewName);
                if ($counter < 5) {
                    $output->writeln("<info>Compiled: " . basename($compiledFile) . "</info>");
                } else {
                    continue;
                }
            } catch (\Exception $e) {
                $output->writeln("<error>Failed to compile: " . basename($file) . " - " . $e->getMessage() . "</error>");
            }
        }

        $output->writeln("<info>Total Compiled: " . $counter . " Files </info>");
        $output->writeln('<info>All views have been cached successfully</info>');
        return Command::SUCCESS;
    }

    /**
     * Recursively get all .blade.php files in the views directory (including subdirectories).
     *
     * @param string $dir
     * @return array
     */
    private function getAllViewFiles($dir)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }
}
