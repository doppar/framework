<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Support\InteractsWithFrontendScaffoldState;
use RuntimeException;
use Symfony\Component\Process\Process;

class FrontendInstallCommand extends Command
{
    private const BOOTSTRAP_VENDOR_IMPORT = "import 'bootstrap/dist/js/bootstrap.bundle.min.js';\n";

    private const HTMX_VENDOR_IMPORT = "import 'htmx.org';\n";

    private const TYPESCRIPT_DECLARATION = <<<'TYPESCRIPT'
declare global {
    interface Window {
        __DOPPAR_FRONTEND__?: Record<string, unknown> & {
            csrfToken?: string | null;
            headers?: Record<string, string>;
        };
    }
}

TYPESCRIPT;
    use InteractsWithFrontendScaffoldState;

    /**
     * @var string
     */
    protected $name = 'frontend:install {--force : Overwrite existing frontend files} {--install : Install dependencies after scaffolding}';

    /**
     * @var string
     */
    protected $description = 'Install a Vite-powered client frontend stack for Doppar';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $this->renderInstallationWelcome();

            if (!$this->confirm('Shall we set up the frontend for you?', true)) {
                $this->newLine();
                $this->displayInfo('Frontend installation aborted.');

                return Command::SUCCESS;
            }

            $cssStack = strtolower($this->choice(
                'Which CSS stack do you want to install?',
                ['Tailwind', 'Bootstrap', 'None'],
                0
            ));

            $framework = strtolower($this->choice(
                'Which client framework do you want to use?',
                ['Vanilla', 'React', 'Vue', 'Svelte', 'htmx'],
                0
            ));

            $typescript = $this->confirm(
                'Do you want TypeScript support?',
                in_array($framework, ['react', 'vue', 'svelte'], true)
            );

            $installDependencies = (bool) ($this->option('install') ?: $this->confirm(
                'Do you want to install frontend dependencies now?',
                false
            ));

            $packageManager = $installDependencies
                ? strtolower($this->choice('Which package manager should be used?', ['npm', 'pnpm', 'yarn'], 0))
                : 'npm';

            $options = [
                'cssStack' => $cssStack,
                'framework' => $framework,
                'typescript' => $typescript,
                'installDependencies' => $installDependencies,
                'packageManager' => $packageManager,
                'force' => (bool) $this->option('force'),
            ];

            $state = $this->newFrontendScaffoldState($options);
            $this->trackFrontendDirectories($state);
            $this->ensureClientDirectories();
            $this->prepareDependencyArtifacts($state, $packageManager, $installDependencies);
            $this->writeFrontendFiles($options, $state);

            if ($installDependencies) {
                $this->rememberFrontendDirectoryLifecycle($state, base_path('node_modules'));
                $this->installNodeDependencies($packageManager);
            }

            $this->saveFrontendScaffoldState($state);

            $this->displaySuccess('Frontend scaffolding installed successfully.');
            $this->line('<fg=cyan>Client folder</>  : <fg=white>' . client_path() . '</>');
            $this->line('<fg=cyan>Entry file</>     : <fg=white>' . $this->entryFilePath($framework, $typescript) . '</>');
            $this->line('<fg=cyan>Usage in Odo</>  : <fg=white>#vite(\'' . $this->entryFilePath($framework, $typescript) . '\')</>');

            return Command::SUCCESS;
        });
    }

    /**
     * Render the frontend installer welcome banner.
     *
     * @return void
     */
    protected function renderInstallationWelcome(): void
    {
        $this->newLine();

        foreach ($this->installationWelcomeBannerLines() as $line) {
            $this->line($line);
        }

        $this->newLine();
    }

    /**
     * Ensure the expected client and storage directories exist.
     *
     * @return void
     */
    protected function ensureClientDirectories(): void
    {
        foreach ([
            client_path(),
            client_path('css'),
            client_path('js'),
            resource_path('views/layouts'),
            storage_path('framework'),
        ] as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }
    }

    /**
     * Write all generated frontend files.
     *
     * @param array<string, mixed> $options
     * @return void
     */
    protected function writeFrontendFiles(array $options, array &$state): void
    {
        $framework = $options['framework'];
        $typescript = $options['typescript'];
        $cssStack = $options['cssStack'];
        $force = $options['force'];

        $files = [
            base_path('package.json') => $this->packageJson($framework, $cssStack, $typescript),
            base_path('vite.config.js') => $this->viteConfig($framework, $typescript),
            client_path('css/app.css') => $this->clientCss($cssStack),
            client_path('js/' . $this->bootstrapFilename($typescript)) => $this->bootstrapFile($cssStack, $framework, $typescript),
            client_path('js/' . $this->entryFilename($framework, $typescript)) => $this->entryFile($framework, $cssStack, $typescript),
            base_path('resources/views/layouts/app.odo.php') => $this->appLayoutView($framework, $typescript),
            base_path('resources/views/welcome.odo.php') => $this->welcomeView($framework, $typescript),
        ];

        if ($cssStack === 'tailwind') {
            $files[base_path('postcss.config.js')] = $this->postcssConfig();
        }

        if ($typescript) {
            $files[base_path('tsconfig.json')] = $this->tsconfig($framework);
        }

        foreach ($this->frameworkComponentFiles($framework, $typescript) as $path => $contents) {
            $files[$path] = $contents;
        }

        foreach ($files as $path => $contents) {
            $this->writeFile($path, $contents, $force, $state);
        }
    }

    /**
     * Build the terminal welcome banner lines.
     *
     * @return array<int, string>
     */
    protected function installationWelcomeBannerLines(): array
    {
        return [
            '<fg=white;bg=blue>  Welcome to the <options=bold>Doppar Frontend</> installation wizard.  </>',
            '',
            '<fg=#807DFC;options=bold>██████╗  ██████╗ ██████╗ ██████╗  █████╗ ██████╗</>',
            '<fg=#807DFC;options=bold>██╔══██╗██╔═══██╗██╔══██╗██╔══██╗██╔══██╗██╔══██╗</>',
            '<fg=#807DFC;options=bold>██║  ██║██║   ██║██████╔╝██████╔╝███████║██████╔╝</>',
            '<fg=#807DFC;options=bold>██║  ██║██║   ██║██╔═══╝ ██╔═══╝ ██╔══██║██╔══██╗</>',
            '<fg=#807DFC;options=bold>██████╔╝╚██████╔╝██║     ██║     ██║  ██║██║  ██║</>',
            '<fg=#807DFC;options=bold>╚═════╝  ╚═════╝ ╚═╝     ╚═╝     ╚═╝  ╚═╝╚═╝  ╚═╝</>',
            '',
            '<fg=yellow>We will now walk through your frontend stack setup step by step.</>',
        ];
    }

    /**
     * Write a single generated file, respecting --force.
     *
     * @param string $path
     * @param string $contents
     * @param bool $force
     * @return void
     */
    protected function writeFile(string $path, string $contents, bool $force, array &$state): void
    {
        if (file_exists($path) && !$force) {
            $this->displayWarning("Skipped existing file: {$path} (use --force to overwrite)");
            return;
        }

        $this->rememberFrontendFileMutation($state, $path);

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $contents);
        $this->line("  <fg=green>Wrote</> <fg=white>{$path}</>");
    }

    /**
     * Install generated dependencies using the requested package manager.
     *
     * @param string $packageManager
     * @return void
     */
    protected function installNodeDependencies(string $packageManager): void
    {
        $commands = match ($packageManager) {
            'pnpm' => ['pnpm', 'install'],
            'yarn' => ['yarn', 'install'],
            default => ['npm', 'install'],
        };

        $this->line('<fg=yellow>Installing frontend dependencies...</>');

        $process = new Process($commands, base_path());
        $process->setTimeout(600);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        if (!$process->isSuccessful()) {
            $this->displayWarning('Dependency installation did not complete successfully. You can retry manually.');
            return;
        }

        $this->displayInfo('Frontend dependencies installed successfully.');
    }

    /**
     * Track frontend directories so uninstall can restore the project to its previous state.
     *
     * @param array<string, mixed> $state
     * @return void
     */
    protected function trackFrontendDirectories(array &$state): void
    {
        foreach ($this->trackedFrontendDirectories() as $directory) {
            $this->rememberFrontendDirectoryLifecycle($state, $directory);
        }
    }

    /**
     * Get the frontend directories owned by the scaffold lifecycle.
     *
     * @return array<int, string>
     */
    protected function trackedFrontendDirectories(): array
    {
        return [
            $this->projectPath('resources/client'),
            $this->projectPath('resources/client/css'),
            $this->projectPath('resources/client/js'),
            $this->projectPath('public/build'),
        ];
    }

    /**
     * Resolve a project-relative filesystem path without requiring a booted Application instance.
     *
     * @param string $path
     * @return string
     */
    protected function projectPath(string $path = ''): string
    {
        $basePath = defined('BASE_PATH')
            ? rtrim(BASE_PATH, DIRECTORY_SEPARATOR)
            : rtrim(getcwd() ?: '', DIRECTORY_SEPARATOR);

        $normalizedPath = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        return $normalizedPath === ''
            ? $basePath
            : $basePath . DIRECTORY_SEPARATOR . $normalizedPath;
    }

    /**
     * Get the relative client entry file path used by Vite and the directive.
     *
     * @param string $framework
     * @param bool $typescript
     * @return string
     */
    protected function entryFilePath(string $framework, bool $typescript): string
    {
        return 'resources/client/js/' . $this->entryFilename($framework, $typescript);
    }

    /**
     * Determine the generated client entry filename.
     *
     * @param string $framework
     * @param bool $typescript
     * @return string
     */
    protected function entryFilename(string $framework, bool $typescript): string
    {
        if ($framework === 'react') {
            return $typescript ? 'main.tsx' : 'main.jsx';
        }

        if (in_array($framework, ['vue', 'svelte', 'htmx'], true)) {
            return $typescript ? 'main.ts' : 'main.js';
        }

        return $typescript ? 'app.ts' : 'app.js';
    }

    /**
     * Generate the package.json contents.
     *
     * @param string $framework
     * @param string $cssStack
     * @param bool $typescript
     * @return string
     */
    protected function packageJson(string $framework, string $cssStack, bool $typescript): string
    {
        $dependencies = [];
        $devDependencies = [
            'vite' => '^7.0.0',
        ];

        if ($framework === 'react') {
            $dependencies['react'] = '^19.0.0';
            $dependencies['react-dom'] = '^19.0.0';
            $devDependencies['@vitejs/plugin-react'] = '^5.0.0';
        }

        if ($framework === 'vue') {
            $dependencies['vue'] = '^3.5.0';
            $devDependencies['@vitejs/plugin-vue'] = '^6.0.0';
        }

        if ($framework === 'svelte') {
            $dependencies['svelte'] = '^5.0.0';
            $devDependencies['@sveltejs/vite-plugin-svelte'] = '^6.0.0';
        }

        if ($cssStack === 'bootstrap') {
            $dependencies['bootstrap'] = '^5.3.3';
        }

        if ($framework === 'htmx') {
            $dependencies['htmx.org'] = '^2.0.10';
        }

        if ($cssStack === 'tailwind') {
            $devDependencies['postcss'] = '^8.4.49';
            $devDependencies['tailwindcss'] = '^4.0.0';
            $devDependencies['@tailwindcss/postcss'] = '^4.0.0';
        }

        if ($typescript) {
            $devDependencies['typescript'] = '^5.7.0';

            if ($framework === 'react') {
                $devDependencies['@types/react'] = '^19.0.0';
                $devDependencies['@types/react-dom'] = '^19.0.0';
            }
        }

        ksort($dependencies);
        ksort($devDependencies);

        return $this->renderFrontendStub('configs/package.stub', [
            'dependenciesBlock' => $this->renderPackageEntries($dependencies),
            'devDependenciesBlock' => $this->renderPackageEntries($devDependencies),
        ]);
    }

    /**
     * Generate the Vite configuration file.
     *
     * @param string $framework
     * @param bool $typescript
     * @return string
     */
    protected function viteConfig(string $framework, bool $typescript): string
    {
        $entry = $this->entryFilePath($framework, $typescript);
        $pluginImport = '';
        $pluginArray = '        dopparHotFile(),';

        if ($framework === 'react') {
            $pluginImport = "import react from '@vitejs/plugin-react';\n";
            $pluginArray = "        react(),\n        dopparHotFile(),";
        } elseif ($framework === 'vue') {
            $pluginImport = "import vue from '@vitejs/plugin-vue';\n";
            $pluginArray = "        vue(),\n        dopparHotFile(),";
        } elseif ($framework === 'svelte') {
            $pluginImport = "import { svelte } from '@sveltejs/vite-plugin-svelte';\n";
            $pluginArray = "        svelte(),\n        dopparHotFile(),";
        }

        return $this->renderFrontendStub('configs/vite.stub', [
            'pluginImport' => rtrim($pluginImport),
            'pluginArray' => $pluginArray,
            'entry' => $entry,
        ]);
    }

    /**
     * Generate the client stylesheet.
     *
     * @param string $cssStack
     * @return string
     */
    protected function clientCss(string $cssStack): string
    {
        return $this->getFrontendStubContent('css/' . $cssStack . '.stub');
    }

    /**
     * Generate the main entry file.
     *
     * @param string $framework
     * @param string $cssStack
     * @param bool $typescript
     * @return string
     */
    protected function entryFile(string $framework, string $cssStack, bool $typescript): string
    {
        $bootstrapImport = "import './bootstrap';\n";

        return $this->renderFrontendStub(
            'entries/' . $this->entryStubName($framework, $typescript),
            ['bootstrapImport' => $bootstrapImport]
        );
    }

    /**
     * Generate framework component files.
     *
     * @param string $framework
     * @param bool $typescript
     * @return array<string, string>
     */
    protected function frameworkComponentFiles(string $framework, bool $typescript): array
    {
        return match ($framework) {
            'react' => [
                client_path('js/' . ($typescript ? 'App.tsx' : 'App.jsx')) =>
                    $this->getFrontendStubContent('components/' . ($typescript ? 'react.tsx.stub' : 'react.jsx.stub')),
            ],
            'vue' => [
                client_path('js/App.vue') => $this->getFrontendStubContent('components/vue.stub'),
            ],
            'svelte' => [
                client_path('js/App.svelte') => $this->getFrontendStubContent('components/svelte.stub'),
            ],
            default => [],
        };
    }

    /**
     * Generate the shared client bootstrap file.
     *
     * @param string $cssStack
     * @param string $framework
     * @param bool $typescript
     * @return string
     */
    protected function bootstrapFile(string $cssStack, string $framework, bool $typescript): string
    {
        $bootstrapVendorImport = $cssStack === 'bootstrap'
            ? self::BOOTSTRAP_VENDOR_IMPORT
            : '';

        $htmxVendorImport = $framework === 'htmx'
            ? self::HTMX_VENDOR_IMPORT
            : '';

        $typescriptDeclaration = $typescript
            ? self::TYPESCRIPT_DECLARATION
            : '';

        return $this->renderFrontendStub('entries/bootstrap.stub', [
            'bootstrapVendorImport' => $bootstrapVendorImport,
            'htmxVendorImport' => $htmxVendorImport,
            'typescriptDeclaration' => $typescriptDeclaration,
        ]);
    }

    /**
     * Generate the PostCSS configuration.
     *
     * @return string
     */
    protected function postcssConfig(): string
    {
        return $this->getFrontendStubContent('configs/postcss.stub');
    }

    /**
     * Generate a TypeScript configuration tuned for the chosen stack.
     *
     * @param string $framework
     * @return string
     */
    protected function tsconfig(string $framework): string
    {
        return $this->renderFrontendStub('configs/tsconfig.stub', [
            'jsxOption' => $framework === 'react' ? '        "jsx": "react-jsx",' : '',
        ]);
    }

    /**
     * Track dependency-related files so uninstall can restore them.
     *
     * @param array<string, mixed> $state
     * @param string $packageManager
     * @param bool $installDependencies
     * @return void
     */
    protected function prepareDependencyArtifacts(array &$state, string $packageManager, bool $installDependencies): void
    {
        if (!$installDependencies) {
            return;
        }

        $lockFile = match ($packageManager) {
            'pnpm' => base_path('pnpm-lock.yaml'),
            'yarn' => base_path('yarn.lock'),
            default => base_path('package-lock.json'),
        };

        $this->rememberFrontendFileMutation($state, $lockFile);
    }

    /**
     * Determine whether the selected stack uses a client-rendered welcome screen.
     *
     * @param string $framework
     * @return bool
     */
    protected function usesClientFramework(string $framework): bool
    {
        return in_array($framework, ['react', 'vue', 'svelte'], true);
    }

    /**
     * Build the welcome view contents for the selected stack.
     *
     * @param string $framework
     * @param bool $typescript
     * @return string
     */
    protected function welcomeView(string $framework, bool $typescript): string
    {
        if ($this->usesClientFramework($framework)) {
            return $this->getFrontendStubContent('views/welcome/client.stub');
        }

        return $this->getFrontendStubContent('views/welcome/plain.stub');
    }

    /**
     * Build the client-backed app layout view.
     *
     * @param string $framework
     * @param bool $typescript
     * @return string
     */
    protected function appLayoutView(string $framework, bool $typescript): string
    {
        return $this->renderFrontendStub('views/layouts/app.stub', [
            'entry' => $this->entryFilePath($framework, $typescript),
        ]);
    }

    /**
     * Resolve the shared bootstrap filename for the chosen stack.
     *
     * @param bool $typescript
     * @return string
     */
    protected function bootstrapFilename(bool $typescript): string
    {
        return $typescript ? 'bootstrap.ts' : 'bootstrap.js';
    }

    /**
     * Resolve the entry stub filename for the chosen stack.
     *
     * @param string $framework
     * @param bool $typescript
     * @return string
     */
    protected function entryStubName(string $framework, bool $typescript): string
    {
        return match ($framework) {
            'react' => $typescript ? 'react.ts.stub' : 'react.js.stub',
            'vue' => 'vue.stub',
            'svelte' => $typescript ? 'svelte.ts.stub' : 'svelte.js.stub',
            default => 'vanilla.stub',
        };
    }

    /**
     * Render a frontend scaffold stub with placeholder replacements.
     *
     * @param string $stubName
     * @param array<string, string> $replacements
     * @return string
     */
    protected function renderFrontendStub(string $stubName, array $replacements = []): string
    {
        $content = $this->getFrontendStubContent($stubName);

        foreach ($replacements as $key => $value) {
            $content = str_replace('{{ ' . $key . ' }}', $value, $content);
        }

        return $content;
    }

    /**
     * Load a frontend scaffold stub from disk.
     *
     * @param string $stubName
     * @return string
     */
    protected function getFrontendStubContent(string $stubName): string
    {
        $stubPath = __DIR__
            . DIRECTORY_SEPARATOR . 'stubs'
            . DIRECTORY_SEPARATOR . 'frontend'
            . DIRECTORY_SEPARATOR . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $stubName);

        if (!file_exists($stubPath)) {
            throw new RuntimeException('Stub not found: ' . $stubPath);
        }

        return (string) file_get_contents($stubPath);
    }

    /**
     * Render a map of package versions as a JSON block.
     *
     * @param array<string, string> $packages
     * @return string
     */
    protected function renderPackageEntries(array $packages): string
    {
        if ($packages === []) {
            return '';
        }

        $lines = [];

        foreach ($packages as $package => $version) {
            $lines[] = '        "' . $package . '": "' . $version . '"';
        }

        return "\n" . implode(",\n", $lines) . "\n    ";
    }
}
