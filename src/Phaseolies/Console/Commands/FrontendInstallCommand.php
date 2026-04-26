<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Support\InteractsWithFrontendScaffoldState;
use Symfony\Component\Process\Process;

class FrontendInstallCommand extends Command
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
            if (!$this->confirm('Shall we set up the frontend for you?', true)) {
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
                ['Vanilla', 'React', 'Vue', 'Svelte'],
                0
            ));

            $typescript = $this->confirm(
                'Do you want TypeScript support?',
                in_array($framework, ['react', 'vue', 'svelte'], true)
            );

            $patchLayout = $this->confirm(
                'Do you want Doppar to patch your main Odo layout with #vite(...) automatically?',
                true
            );

            $layoutPath = $patchLayout
                ? $this->ask(
                    'Which layout file should be updated?',
                    base_path('resources/views/layouts/app.odo.php')
                )
                : null;

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
                'patchLayout' => $patchLayout,
                'layoutPath' => $layoutPath,
                'installDependencies' => $installDependencies,
                'packageManager' => $packageManager,
                'force' => (bool) $this->option('force'),
            ];

            $state = $this->newFrontendScaffoldState($options);
            $this->ensureClientDirectories();
            $this->prepareDependencyArtifacts($state, $packageManager, $installDependencies);
            $this->writeFrontendFiles($options, $state);

            if ($patchLayout && $layoutPath) {
                $this->patchLayout($layoutPath, $this->entryFilePath($framework, $typescript), $framework, $state);
            }

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
            client_path('js/' . $this->entryFilename($framework, $typescript)) => $this->entryFile($framework, $cssStack, $typescript),
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
     * Patch the selected layout to include #vite and a framework mount point.
     *
     * @param string $layoutPath
     * @param string $entryPath
     * @param string $framework
     * @return void
     */
    protected function patchLayout(string $layoutPath, string $entryPath, string $framework, array &$state): void
    {
        if (!file_exists($layoutPath)) {
            $this->displayWarning("Layout not found: {$layoutPath}. Skipping automatic patch.");
            return;
        }

        $this->rememberFrontendFileMutation($state, $layoutPath);
        $contents = (string) file_get_contents($layoutPath);
        $viteDirective = self::VITE_MARKER_START . "\n    #vite('{$entryPath}')\n    " . self::VITE_MARKER_END;
        $mountPoint = '<div id="app"></div>';

        if (str_contains($contents, self::VITE_MARKER_START) && str_contains($contents, self::VITE_MARKER_END)) {
            $contents = preg_replace(
                '/' . preg_quote(self::VITE_MARKER_START, '/') . '.*?' . preg_quote(self::VITE_MARKER_END, '/') . '/s',
                $viteDirective,
                $contents
            ) ?? $contents;
        } else {
            if (str_contains($contents, '</head>')) {
                $contents = str_replace('</head>', "    {$viteDirective}\n</head>", $contents);
            } else {
                $contents .= "\n{$viteDirective}\n";
            }
        }

        if ($framework !== 'vanilla' && !str_contains($contents, $mountPoint)) {
            if (str_contains($contents, '</body>')) {
                $contents = str_replace('</body>', "    {$mountPoint}\n</body>", $contents);
            } else {
                $contents .= "\n{$mountPoint}\n";
            }
        }

        file_put_contents($layoutPath, $contents);
        $this->line("  <fg=green>Patched</> <fg=white>{$layoutPath}</>");
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

        if (in_array($framework, ['vue', 'svelte'], true)) {
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
        $config = [
            'private' => true,
            'type' => 'module',
            'scripts' => [
                'dev' => 'vite',
                'build' => 'vite build',
                'preview' => 'vite preview',
            ],
            'dependencies' => [],
            'devDependencies' => [
                'vite' => '^7.0.0',
            ],
        ];

        if ($framework === 'react') {
            $config['dependencies']['react'] = '^19.0.0';
            $config['dependencies']['react-dom'] = '^19.0.0';
            $config['devDependencies']['@vitejs/plugin-react'] = '^4.3.0';
        }

        if ($framework === 'vue') {
            $config['dependencies']['vue'] = '^3.5.0';
            $config['devDependencies']['@vitejs/plugin-vue'] = '^5.2.0';
        }

        if ($framework === 'svelte') {
            $config['dependencies']['svelte'] = '^5.0.0';
            $config['devDependencies']['@sveltejs/vite-plugin-svelte'] = '^5.0.0';
        }

        if ($cssStack === 'bootstrap') {
            $config['dependencies']['bootstrap'] = '^5.3.3';
        }

        if ($cssStack === 'tailwind') {
            $config['devDependencies']['postcss'] = '^8.4.49';
            $config['devDependencies']['tailwindcss'] = '^4.0.0';
            $config['devDependencies']['@tailwindcss/postcss'] = '^4.0.0';
        }

        if ($typescript) {
            $config['devDependencies']['typescript'] = '^5.7.0';

            if ($framework === 'react') {
                $config['devDependencies']['@types/react'] = '^19.0.0';
                $config['devDependencies']['@types/react-dom'] = '^19.0.0';
            }
        }

        ksort($config['dependencies']);
        ksort($config['devDependencies']);

        return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
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
        $pluginArray = '    dopparHotFile(),';

        if ($framework === 'react') {
            $pluginImport = "import react from '@vitejs/plugin-react';\n";
            $pluginArray = "    react(),\n    dopparHotFile(),";
        } elseif ($framework === 'vue') {
            $pluginImport = "import vue from '@vitejs/plugin-vue';\n";
            $pluginArray = "    vue(),\n    dopparHotFile(),";
        } elseif ($framework === 'svelte') {
            $pluginImport = "import { svelte } from '@sveltejs/vite-plugin-svelte';\n";
            $pluginArray = "    svelte(),\n    dopparHotFile(),";
        }

        return <<<JS
import fs from 'node:fs';
import path from 'node:path';
import { defineConfig } from 'vite';
{$pluginImport}

function dopparHotFile() {
    const hotFile = path.resolve(__dirname, 'storage/framework/vite.hot');

    const cleanup = () => {
        if (fs.existsSync(hotFile)) {
            fs.rmSync(hotFile, { force: true });
        }
    };

    return {
        name: 'doppar-vite-hot-file',
        configureServer(server) {
            const writeHotFile = () => {
                const address = server.httpServer?.address();
                if (!address || typeof address === 'string') {
                    return;
                }

                const host = typeof server.config.server.host === 'string'
                    && server.config.server.host !== '0.0.0.0'
                    && server.config.server.host !== '::'
                    ? server.config.server.host
                    : '127.0.0.1';

                const protocol = server.config.server.https ? 'https' : 'http';
                fs.mkdirSync(path.dirname(hotFile), { recursive: true });
                fs.writeFileSync(hotFile, `\${protocol}://\${host}:\${address.port}`);
            };

            server.httpServer?.once('listening', writeHotFile);
            server.httpServer?.once('close', cleanup);
            process.once('exit', cleanup);
        },
    };
}

export default defineConfig({
    plugins: [
{$pluginArray}
    ],
    resolve: {
        alias: {
            '~client': path.resolve(__dirname, 'resources/client'),
            '@': path.resolve(__dirname, 'resources/client/js'),
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        origin: 'http://127.0.0.1:5173',
    },
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: 'manifest.json',
        rollupOptions: {
            input: ['{$entry}'],
        },
    },
});
JS;
    }

    /**
     * Generate the client stylesheet.
     *
     * @param string $cssStack
     * @return string
     */
    protected function clientCss(string $cssStack): string
    {
        return match ($cssStack) {
            'tailwind' => <<<CSS
@import "tailwindcss";

:root {
    color-scheme: light;
}

body {
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(16, 185, 129, 0.12), transparent 30%),
        radial-gradient(circle at bottom right, rgba(14, 165, 233, 0.12), transparent 35%),
        #f8fafc;
}
CSS,
            'bootstrap' => <<<CSS
@import "bootstrap/dist/css/bootstrap.min.css";

body {
    min-height: 100vh;
    background:
        linear-gradient(135deg, rgba(14, 165, 233, 0.08), transparent 40%),
        linear-gradient(315deg, rgba(16, 185, 129, 0.08), transparent 45%),
        #f8fafc;
}
CSS,
            default => <<<CSS
:root {
    font-family: "Instrument Sans", system-ui, sans-serif;
    color: #0f172a;
    background: #f8fafc;
}

body {
    margin: 0;
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(14, 165, 233, 0.12), transparent 30%),
        #f8fafc;
}
CSS,
        };
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
        $bootstrapImport = $cssStack === 'bootstrap'
            ? "import 'bootstrap/dist/js/bootstrap.bundle.min.js';\n"
            : '';

        if ($framework === 'react') {
            return $typescript ? <<<TS
import '../css/app.css';
{$bootstrapImport}import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';

ReactDOM.createRoot(document.getElementById('app')!).render(
    <React.StrictMode>
        <App />
    </React.StrictMode>
);
TS : <<<JS
import '../css/app.css';
{$bootstrapImport}import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';

ReactDOM.createRoot(document.getElementById('app')).render(
    <React.StrictMode>
        <App />
    </React.StrictMode>
);
JS;
        }

        if ($framework === 'vue') {
            return $typescript ? <<<TS
import '../css/app.css';
{$bootstrapImport}import { createApp } from 'vue';
import App from './App.vue';

createApp(App).mount('#app');
TS : <<<JS
import '../css/app.css';
{$bootstrapImport}import { createApp } from 'vue';
import App from './App.vue';

createApp(App).mount('#app');
JS;
        }

        if ($framework === 'svelte') {
            return $typescript ? <<<TS
import '../css/app.css';
{$bootstrapImport}import { mount } from 'svelte';
import App from './App.svelte';

mount(App, {
    target: document.getElementById('app')!,
});
TS : <<<JS
import '../css/app.css';
{$bootstrapImport}import { mount } from 'svelte';
import App from './App.svelte';

mount(App, {
    target: document.getElementById('app'),
});
JS;
        }

        return <<<JS
import '../css/app.css';
{$bootstrapImport}
document.documentElement.dataset.clientReady = 'true';
console.info('Doppar client booted successfully.');
JS;
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
                client_path('js/' . ($typescript ? 'App.tsx' : 'App.jsx')) => $typescript ? <<<TSX
export default function App() {
    return (
        <main style={{ padding: '3rem' }}>
            <h1>Doppar + React</h1>
            <p>Your client app is running from the <code>resources/client/</code> directory.</p>
        </main>
    );
}
TSX : <<<JSX
export default function App() {
    return (
        <main style={{ padding: '3rem' }}>
            <h1>Doppar + React</h1>
            <p>Your client app is running from the <code>resources/client/</code> directory.</p>
        </main>
    );
}
JSX,
            ],
            'vue' => [
                client_path('js/App.vue') => <<<VUE
<template>
    <main style="padding: 3rem">
        <h1>Doppar + Vue</h1>
        <p>Your client app is running from the <code>resources/client/</code> directory.</p>
    </main>
</template>
VUE,
            ],
            'svelte' => [
                client_path('js/App.svelte') => <<<SVELTE
<main style="padding: 3rem">
    <h1>Doppar + Svelte</h1>
    <p>Your client app is running from the <code>resources/client/</code> directory.</p>
</main>
SVELTE,
            ],
            default => [],
        };
    }

    /**
     * Generate the PostCSS configuration.
     *
     * @return string
     */
    protected function postcssConfig(): string
    {
        return <<<JS
export default {
    plugins: {
        '@tailwindcss/postcss': {},
    },
};
JS;
    }

    /**
     * Generate a TypeScript configuration tuned for the chosen stack.
     *
     * @param string $framework
     * @return string
     */
    protected function tsconfig(string $framework): string
    {
        $jsx = $framework === 'react' ? '"jsx": "react-jsx",' : '';

        return <<<JSON
{
    "compilerOptions": {
        "target": "ES2020",
        "useDefineForClassFields": true,
        "module": "ESNext",
        "moduleResolution": "Bundler",
        "strict": true,
        "resolveJsonModule": true,
        "isolatedModules": true,
        "esModuleInterop": true,
        "lib": ["ES2020", "DOM", "DOM.Iterable"],
        {$jsx}
        "baseUrl": ".",
        "paths": {
            "@/*": ["resources/client/js/*"]
        }
    },
    "include": ["resources/client/**/*.ts", "resources/client/**/*.tsx", "resources/client/**/*.vue", "resources/client/**/*.svelte"]
}
JSON;
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
}
