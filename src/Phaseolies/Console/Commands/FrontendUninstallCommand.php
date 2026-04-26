<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Support\InteractsWithFrontendScaffoldState;

class FrontendUninstallCommand extends Command
{
    use InteractsWithFrontendScaffoldState;

    /**
     * @var string
     */
    protected const VITE_MARKER_START = '<!-- DOPPAR_FRONTEND_VITE_START -->';

    /**
     * @var string
     */
    protected const VITE_MARKER_END = '<!-- DOPPAR_FRONTEND_VITE_END -->';

    /**
     * @var string
     */
    protected $name = 'frontend:uninstall {--clean-node-modules : Remove node_modules if it was created by frontend:install}';

    /**
     * @var string
     */
    protected $description = 'Remove Doppar frontend scaffolding and restore backed up files';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $removeNodeModules = (bool) $this->option('clean-node-modules');
            $state = $this->loadFrontendScaffoldState();

            if ($state === null) {
                $removedArtifacts = $this->performLegacyFrontendCleanup($removeNodeModules);

                if ($removedArtifacts === 0) {
                    $this->displayWarning('No frontend scaffold manifest was found, and no recognizable legacy frontend scaffold files were detected.');
                    return Command::SUCCESS;
                }

                $this->displayWarning('No frontend scaffold manifest was found. Doppar used best-effort cleanup for legacy frontend scaffold files.');
                $this->displaySuccess('Frontend scaffolding has been removed.');

                if (!$removeNodeModules && is_dir(base_path('node_modules'))) {
                    $this->displayInfo('node_modules was preserved. Use --clean-node-modules if you want it removed.');
                }

                return Command::SUCCESS;
            }

            $this->restoreFrontendScaffoldState($state, $removeNodeModules);
            $this->forgetFrontendScaffoldState();

            $this->displaySuccess('Frontend scaffolding has been removed and previous files were restored.');

            if (!$removeNodeModules && is_dir(base_path('node_modules'))) {
                $this->displayInfo('node_modules was preserved. Use --clean-node-modules if you want it removed.');
            }

            return Command::SUCCESS;
        });
    }

    /**
     * Remove a legacy frontend scaffold when no state manifest is available.
     *
     * @param bool $removeNodeModules
     * @return int
     */
    protected function performLegacyFrontendCleanup(bool $removeNodeModules = false): int
    {
        $removedArtifacts = 0;
        $detectedScaffold = false;

        $packageJsonPath = base_path('package.json');
        $packageJsonGenerated = is_file($packageJsonPath)
            && $this->isGeneratedPackageJson((string) file_get_contents($packageJsonPath));

        $generatedRootFiles = [
            base_path('vite.config.js') => fn (string $contents): bool => $this->isGeneratedViteConfig($contents),
            base_path('tsconfig.json') => fn (string $contents): bool => $this->isGeneratedTsConfig($contents),
            base_path('postcss.config.js') => fn (string $contents): bool => $this->isGeneratedPostcssConfig($contents),
        ];

        foreach ($generatedRootFiles as $path => $detector) {
            if (!is_file($path)) {
                continue;
            }

            $contents = (string) file_get_contents($path);

            if (!$detector($contents)) {
                continue;
            }

            $detectedScaffold = true;
            unlink($path);
            $removedArtifacts++;
        }

        if ($packageJsonGenerated) {
            $detectedScaffold = true;
            unlink($packageJsonPath);
            $removedArtifacts++;

            foreach ([base_path('package-lock.json'), base_path('pnpm-lock.yaml'), base_path('yarn.lock')] as $lockFile) {
                if (is_file($lockFile)) {
                    unlink($lockFile);
                    $removedArtifacts++;
                }
            }
        }

        $clientFiles = [
            client_path('css/app.css'),
            client_path('js/app.js'),
            client_path('js/app.ts'),
            client_path('js/main.js'),
            client_path('js/main.ts'),
            client_path('js/main.jsx'),
            client_path('js/main.tsx'),
            client_path('js/App.jsx'),
            client_path('js/App.tsx'),
            client_path('js/App.vue'),
            client_path('js/App.svelte'),
        ];

        foreach ($clientFiles as $path) {
            if (!is_file($path)) {
                continue;
            }

            $detectedScaffold = true;
            unlink($path);
            $removedArtifacts++;
        }

        $removedArtifacts += $this->removeLegacyViteMarkersFromLayouts();
        $detectedScaffold = $detectedScaffold || $removedArtifacts > 0;

        if ($removeNodeModules && $detectedScaffold && is_dir(base_path('node_modules'))) {
            $this->deleteDirectoryRecursively(base_path('node_modules'));
            $removedArtifacts++;
        }

        if ($detectedScaffold && is_dir(public_path('build'))) {
            $this->deleteDirectoryRecursively(public_path('build'));
            $removedArtifacts++;
        }

        $this->cleanupFrontendDirectories();

        return $removedArtifacts;
    }

    /**
     * Strip injected frontend markers from layout files.
     *
     * @return int
     */
    protected function removeLegacyViteMarkersFromLayouts(): int
    {
        $viewsPath = base_path('resources/views');

        if (!is_dir($viewsPath)) {
            return 0;
        }

        $removed = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.odo.php')) {
                continue;
            }

            $path = $file->getPathname();
            $contents = (string) file_get_contents($path);
            $updated = $this->stripPatchedFrontendMarkup($contents);

            if ($updated === $contents) {
                continue;
            }

            file_put_contents($path, $updated);
            $removed++;
        }

        return $removed;
    }

    /**
     * Remove injected #vite markers and the default mount point from a layout file.
     *
     * @param string $contents
     * @return string
     */
    protected function stripPatchedFrontendMarkup(string $contents): string
    {
        if (!str_contains($contents, self::VITE_MARKER_START) || !str_contains($contents, self::VITE_MARKER_END)) {
            return $contents;
        }

        $updated = preg_replace(
            '/^[ \t]*' . preg_quote(self::VITE_MARKER_START, '/') . '\R.*?^[ \t]*' . preg_quote(self::VITE_MARKER_END, '/') . '\R?/ms',
            '',
            $contents
        ) ?? $contents;

        $updated = preg_replace('/^[ \t]*<div id="app"><\/div>\R?/m', '', $updated, 1) ?? $updated;

        return $updated;
    }

    /**
     * Determine whether the package.json matches the generated frontend scaffold template.
     *
     * @param string $contents
     * @return bool
     */
    protected function isGeneratedPackageJson(string $contents): bool
    {
        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return false;
        }

        if (($decoded['private'] ?? null) !== true || ($decoded['type'] ?? null) !== 'module') {
            return false;
        }

        $scripts = $decoded['scripts'] ?? null;
        if (!is_array($scripts)) {
            return false;
        }

        $expectedScripts = [
            'dev' => 'vite',
            'build' => 'vite build',
            'preview' => 'vite preview',
        ];

        if ($scripts !== $expectedScripts) {
            return false;
        }

        $knownPackages = [
            'vite',
            'react',
            'react-dom',
            'vue',
            'svelte',
            'bootstrap',
            'postcss',
            'tailwindcss',
            '@tailwindcss/postcss',
            'typescript',
            '@types/react',
            '@types/react-dom',
            '@vitejs/plugin-react',
            '@vitejs/plugin-vue',
            '@sveltejs/vite-plugin-svelte',
        ];

        $dependencies = array_keys($decoded['dependencies'] ?? []);
        $devDependencies = array_keys($decoded['devDependencies'] ?? []);
        $allPackages = array_unique(array_merge($dependencies, $devDependencies));

        foreach ($allPackages as $package) {
            if (!in_array($package, $knownPackages, true)) {
                return false;
            }
        }

        return in_array('vite', $allPackages, true);
    }

    /**
     * Determine whether the file content matches the generated Vite configuration.
     *
     * @param string $contents
     * @return bool
     */
    protected function isGeneratedViteConfig(string $contents): bool
    {
        return str_contains($contents, "name: 'doppar-vite-hot-file'")
            && str_contains($contents, "storage/framework/vite.hot")
            && str_contains($contents, "outDir: 'public/build'")
            && str_contains($contents, "path.resolve(__dirname, 'client')");
    }

    /**
     * Determine whether the file content matches the generated TypeScript configuration.
     *
     * @param string $contents
     * @return bool
     */
    protected function isGeneratedTsConfig(string $contents): bool
    {
        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return false;
        }

        return ($decoded['compilerOptions']['moduleResolution'] ?? null) === 'Bundler'
            && ($decoded['compilerOptions']['baseUrl'] ?? null) === '.'
            && (($decoded['compilerOptions']['paths']['@/*'][0] ?? null) === 'client/js/*')
            && in_array('client/**/*.ts', $decoded['include'] ?? [], true)
            && in_array('client/**/*.tsx', $decoded['include'] ?? [], true);
    }

    /**
     * Determine whether the file content matches the generated PostCSS configuration.
     *
     * @param string $contents
     * @return bool
     */
    protected function isGeneratedPostcssConfig(string $contents): bool
    {
        return str_contains($contents, "export default")
            && str_contains($contents, "'@tailwindcss/postcss': {}");
    }
}
