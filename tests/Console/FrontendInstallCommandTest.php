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
        $this->assertSame('client/js/main.tsx', $pathMethod->invoke($command, 'react', true));
    }

    public function testViteConfigTargetsMainReactEntrypoint(): void
    {
        $command = new FrontendInstallCommand();
        $method = new \ReflectionMethod($command, 'viteConfig');
        $entryMethod = new \ReflectionMethod($command, 'entryFile');

        $config = $method->invoke($command, 'react', true);
        $entryFile = $entryMethod->invoke($command, 'react', 'none', true);

        $this->assertStringContainsString("input: ['client/js/main.tsx']", $config);
        $this->assertStringContainsString("import App from './App';", $entryFile);
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
    #vite('client/js/main.tsx')
    <!-- DOPPAR_FRONTEND_VITE_END -->
</head>
<body>
    <div id="app"></div>
</body>
</html>
ODO;

        $updated = $method->invoke($command, $contents);

        $this->assertStringNotContainsString('DOPPAR_FRONTEND_VITE_START', $updated);
        $this->assertStringNotContainsString("#vite('client/js/main.tsx')", $updated);
        $this->assertStringNotContainsString('<div id="app"></div>', $updated);
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
}
