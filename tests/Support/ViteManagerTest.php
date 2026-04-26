<?php

namespace Tests\SupportViteRuntime {
final class Paths
{
    public static string $base;
    public static string $storage;
    public static string $public;
}
}

namespace Phaseolies\Support {
    function storage_path(string $path = ''): string
    {
        $base = \Tests\SupportViteRuntime\Paths::$storage;

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    function public_path(string $path = ''): string
    {
        $base = \Tests\SupportViteRuntime\Paths::$public;

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    function enqueue(string $path = '', $secure = null): string
    {
        return 'https://example.test/' . ltrim($path, '/');
    }
}

namespace Tests\Unit\Support {

use Phaseolies\Support\ViteManager;
use PHPUnit\Framework\TestCase;
use Tests\SupportViteRuntime\Paths;

class ViteManagerTest extends TestCase
{
    private string $tempDirectory;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/doppar_vite_' . uniqid('', true);
        Paths::$base = $this->tempDirectory;
        Paths::$storage = $this->tempDirectory . '/storage';
        Paths::$public = $this->tempDirectory . '/public';

        mkdir(Paths::$storage . '/framework', 0777, true);
        mkdir(Paths::$public . '/build', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDirectory);
    }

    public function testTagsUseHotServerWhenHotFileExists(): void
    {
        file_put_contents(Paths::$storage . '/framework/vite.hot', 'http://127.0.0.1:5173');

        $tags = (new ViteManager())->tags('resources/client/js/app.js');

        $this->assertStringContainsString('http://127.0.0.1:5173/@vite/client', $tags);
        $this->assertStringContainsString('http://127.0.0.1:5173/resources/client/js/app.js', $tags);
    }

    public function testHotReactEntriesAutomaticallyIncludeRefreshPreamble(): void
    {
        file_put_contents(Paths::$storage . '/framework/vite.hot', 'http://127.0.0.1:5173');

        $tags = (new ViteManager())->tags('resources/client/js/main.tsx');

        $this->assertStringContainsString('http://127.0.0.1:5173/@react-refresh', $tags);
        $this->assertStringContainsString('__vite_plugin_react_preamble_installed__ = true', $tags);
        $this->assertStringContainsString('http://127.0.0.1:5173/@vite/client', $tags);
        $this->assertStringContainsString('http://127.0.0.1:5173/resources/client/js/main.tsx', $tags);
    }

    public function testTagsResolveManifestImportsStylesAndScripts(): void
    {
        file_put_contents(
            Paths::$public . '/build/manifest.json',
            json_encode([
                'resources/client/js/app.js' => [
                    'file' => 'assets/app-123.js',
                    'css' => ['assets/app-123.css'],
                    'imports' => ['resources/client/js/vendor.js'],
                ],
                'resources/client/js/vendor.js' => [
                    'file' => 'assets/vendor-456.js',
                    'css' => ['assets/vendor-456.css'],
                ],
            ], JSON_PRETTY_PRINT)
        );

        $tags = (new ViteManager())->tags('resources/client/js/app.js');

        $this->assertStringContainsString('modulepreload', $tags);
        $this->assertStringContainsString('https://example.test/build/assets/vendor-456.js', $tags);
        $this->assertStringContainsString('https://example.test/build/assets/app-123.css', $tags);
        $this->assertStringContainsString('https://example.test/build/assets/vendor-456.css', $tags);
        $this->assertStringContainsString('https://example.test/build/assets/app-123.js', $tags);
    }

    public function testAssetResolvesFromManifestAndHotServer(): void
    {
        file_put_contents(
            Paths::$public . '/build/manifest.json',
            json_encode([
                'resources/client/js/app.js' => ['file' => 'assets/app-123.js'],
            ], JSON_PRETTY_PRINT)
        );

        $manager = new ViteManager();

        $this->assertSame(
            'https://example.test/build/assets/app-123.js',
            $manager->asset('resources/client/js/app.js')
        );

        file_put_contents(Paths::$storage . '/framework/vite.hot', 'http://127.0.0.1:5173');

        $this->assertSame(
            'http://127.0.0.1:5173/resources/client/js/app.js',
            $manager->asset('resources/client/js/app.js')
        );
    }

    public function testTagsResolveManifestFromViteSevenDefaultDirectory(): void
    {
        mkdir(Paths::$public . '/build/.vite', 0777, true);

        file_put_contents(
            Paths::$public . '/build/.vite/manifest.json',
            json_encode([
                'resources/client/js/app.js' => [
                    'file' => 'assets/app-123.js',
                ],
            ], JSON_PRETTY_PRINT)
        );

        $tags = (new ViteManager())->tags('resources/client/js/app.js');

        $this->assertStringContainsString('https://example.test/build/assets/app-123.js', $tags);
    }

    public function testManifestPathAcceptsWindowsStyleBuildDirectory(): void
    {
        file_put_contents(
            Paths::$public . '/build/manifest.json',
            json_encode([
                'resources/client/js/app.js' => [
                    'file' => 'assets/app-123.js',
                ],
            ], JSON_PRETTY_PRINT)
        );

        $manager = new ViteManager();
        $method = new \ReflectionMethod($manager, 'manifestPath');

        $resolved = $method->invoke($manager, 'build\\');

        $this->assertSame(Paths::$public . '/build/manifest.json', $resolved);
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
}
