<?php

namespace Tests\Unit\Console;

use Phaseolies\Console\Commands\FrontendInstallCommand;
use Phaseolies\Console\Commands\FrontendUninstallCommand;
use PHPUnit\Framework\TestCase;

class FrontendInstallCommandTest extends TestCase
{
    public function testReactTypescriptUsesMainEntryFile(): void
    {
        $command = new FrontendInstallCommand();

        $filenameMethod = new \ReflectionMethod($command, 'entryFilename');
        $pathMethod = new \ReflectionMethod($command, 'entryFilePath');

        $this->assertSame('main.tsx', $filenameMethod->invoke($command, 'react', true));
        $this->assertSame('resources/client/js/main.tsx', $pathMethod->invoke($command, 'react', true));
    }

    public function testVanillaWelcomeUsesSharedAppLayout(): void
    {
        $command = new FrontendInstallCommand();
        $method = new \ReflectionMethod($command, 'welcomeView');

        $welcome = $method->invoke($command, 'vanilla', false);

        $this->assertStringContainsString("#extends('layouts.app')", $welcome);
        $this->assertStringContainsString('#section(\'content\')', $welcome);
        $this->assertStringContainsString('<h2 class="doppar-welcome-title">Welcome to Doppar</h2>', $welcome);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $welcome);
    }

    public function testInstallerWelcomeBannerIncludesDopparBranding(): void
    {
        $command = new FrontendInstallCommand();
        $method = new \ReflectionMethod($command, 'installationWelcomeBannerLines');

        $lines = $method->invoke($command);
        $banner = implode("\n", $lines);

        $this->assertStringContainsString('Welcome to the <options=bold>Doppar Frontend</> installation wizard.', $banner);
        $this->assertStringContainsString('██████╗', $banner);
        $this->assertStringContainsString('We will now walk through your frontend stack setup step by step.', $banner);
    }

    public function testClientFrameworkWelcomeUsesSharedLayoutShell(): void
    {
        $command = new FrontendInstallCommand();
        $welcomeMethod = new \ReflectionMethod($command, 'welcomeView');
        $layoutMethod = new \ReflectionMethod($command, 'appLayoutView');

        $welcome = $welcomeMethod->invoke($command, 'vue', true);
        $layout = $layoutMethod->invoke($command, 'vue', true);

        $this->assertStringContainsString("#extends('layouts.app')", $welcome);
        $this->assertStringContainsString('#section(\'content\')', $welcome);
        $this->assertStringContainsString('<div id="app"></div>', $welcome);
        $this->assertStringContainsString("#vite('resources/client/js/main.ts')", $layout);
        $this->assertStringContainsString('<meta name="csrf-token" content="[[ csrf_token() ]]" />', $layout);
        $this->assertStringContainsString('window.__DOPPAR_FRONTEND__', $layout);
        $this->assertStringContainsString('csrfToken: "[[ csrf_token() ]]"', $layout);
        $this->assertStringContainsString("#yield('content')", $layout);
        $this->assertStringNotContainsString('<div id="app"></div>', $layout);
    }

    public function testVanillaAppLayoutUsesAppEntrypoint(): void
    {
        $command = new FrontendInstallCommand();
        $method = new \ReflectionMethod($command, 'appLayoutView');

        $layout = $method->invoke($command, 'vanilla', true);

        $this->assertStringContainsString("#vite('resources/client/js/app.ts')", $layout);
        $this->assertStringContainsString("#yield('content')", $layout);
    }

    public function testViteConfigTargetsMainReactEntrypoint(): void
    {
        $command = new FrontendInstallCommand();
        $method = new \ReflectionMethod($command, 'viteConfig');
        $entryMethod = new \ReflectionMethod($command, 'entryFile');

        $config = $method->invoke($command, 'react', true);
        $entryFile = $entryMethod->invoke($command, 'react', 'none', true);

        $this->assertStringContainsString("input: ['resources/client/js/main.tsx']", $config);
        $this->assertStringContainsString("publicDir: false", $config);
        $this->assertStringContainsString('fs.writeFileSync(hotFile, `${protocol}://${host}:${address.port}`);', $config);
        $this->assertStringContainsString("import './bootstrap';", $entryFile);
        $this->assertStringContainsString("import App from './App';", $entryFile);
    }

    public function testClientBootstrapExposesCsrfHeaderFromMetaToken(): void
    {
        $command = new FrontendInstallCommand();
        $method = new \ReflectionMethod($command, 'bootstrapFile');

        $bootstrap = $method->invoke($command, 'bootstrap', true);

        $this->assertStringContainsString("meta[name=\"csrf-token\"]", $bootstrap);
        $this->assertStringContainsString("headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}", $bootstrap);
        $this->assertStringNotContainsString('window.fetch', $bootstrap);
        $this->assertStringNotContainsString('XMLHttpRequest.prototype', $bootstrap);
        $this->assertStringNotContainsString('X-Requested-With', $bootstrap);
        $this->assertStringContainsString("import 'bootstrap/dist/js/bootstrap.bundle.min.js';", $bootstrap);
    }

    public function testVuePackageJsonUsesViteSevenCompatiblePluginVersion(): void
    {
        $command = new FrontendInstallCommand();
        $method = new \ReflectionMethod($command, 'packageJson');

        $packageJson = $method->invoke($command, 'vue', 'tailwind', true);

        $this->assertStringContainsString('"vite": "^7.0.0"', $packageJson);
        $this->assertStringContainsString('"@vitejs/plugin-vue": "^6.0.0"', $packageJson);
    }

    public function testTailwindCssIncludesExplicitSourceDirectives(): void
    {
        $command = new FrontendInstallCommand();
        $method = new \ReflectionMethod($command, 'clientCss');

        $css = $method->invoke($command, 'tailwind');

        $this->assertStringContainsString('@import "tailwindcss";', $css);
        $this->assertStringContainsString('@source "../js";', $css);
        $this->assertStringContainsString('@source "../../views";', $css);
    }

    public function testFrontendInstallTracksResourcesClientDirectories(): void
    {
        $command = new FrontendInstallCommand();
        $method = new \ReflectionMethod($command, 'trackedFrontendDirectories');

        $directories = array_map($this->normalizePath(...), $method->invoke($command));

        $this->assertContains($this->normalizePath(getcwd() . '/resources/client'), $directories);
        $this->assertContains($this->normalizePath(getcwd() . '/resources/client/css'), $directories);
        $this->assertContains($this->normalizePath(getcwd() . '/resources/client/js'), $directories);
    }

    public function testFrontendUninstallCommandIsRegisteredWithExpectedSignature(): void
    {
        $command = new FrontendUninstallCommand();
        $reflection = new \ReflectionProperty($command, 'name');

        $this->assertStringStartsWith('frontend:uninstall', $reflection->getValue($command));
        $this->assertTrue($command->getDefinition()->hasOption('clean-node-modules'));
    }

    public function testFrontendUninstallStripsInjectedViteMarkersFromLayouts(): void
    {
        $command = new FrontendUninstallCommand();
        $method = new \ReflectionMethod($command, 'stripPatchedFrontendMarkup');

        $contents = <<<ODO
<html>
<head>
    <!-- DOPPAR_FRONTEND_VITE_START -->
    #vite('resources/client/js/main.tsx')
    <!-- DOPPAR_FRONTEND_VITE_END -->
</head>
<body>
    <div id="app"></div>
</body>
</html>
ODO;

        $updated = $method->invoke($command, $contents);

        $this->assertStringNotContainsString('DOPPAR_FRONTEND_VITE_START', $updated);
        $this->assertStringNotContainsString("#vite('resources/client/js/main.tsx')", $updated);
        $this->assertStringNotContainsString('<div id="app"></div>', $updated);
    }

    public function testFrontendUninstallLegacyDirectoriesIncludeResourcesClient(): void
    {
        $command = new FrontendUninstallCommand();
        $method = new \ReflectionMethod($command, 'legacyFrontendDirectories');

        $directories = array_map($this->normalizePath(...), $method->invoke($command));

        $this->assertContains($this->normalizePath(getcwd() . '/resources/client'), $directories);
        $this->assertContains($this->normalizePath(getcwd() . '/client'), $directories);
    }

    public function testFrontendUninstallDetectsGeneratedPackageJsonTemplate(): void
    {
        $command = new FrontendUninstallCommand();
        $method = new \ReflectionMethod($command, 'isGeneratedPackageJson');

        $generated = <<<'JSON'
{
    "private": true,
    "type": "module",
    "scripts": {
        "dev": "vite",
        "build": "vite build",
        "preview": "vite preview"
    },
    "dependencies": {
        "react": "^19.0.0",
        "react-dom": "^19.0.0"
    },
    "devDependencies": {
        "@vitejs/plugin-react": "^4.3.0",
        "vite": "^7.0.0"
    }
}
JSON;

        $custom = <<<'JSON'
{
    "private": true,
    "type": "module",
    "scripts": {
        "dev": "vite",
        "build": "vite build",
        "preview": "vite preview"
    },
    "dependencies": {
        "axios": "^1.0.0"
    },
    "devDependencies": {
        "vite": "^7.0.0"
    }
}
JSON;

        $this->assertTrue($method->invoke($command, $generated));
        $this->assertFalse($method->invoke($command, $custom));
    }

    private function normalizePath(string $path): string
    {
        return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
    }
}
