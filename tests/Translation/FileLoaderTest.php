<?php

namespace Tests\Unit\Translation;

use Phaseolies\Translation\FileLoader;
use PHPUnit\Framework\TestCase;

final class FileLoaderTest extends TestCase
{
    private string $tempDir;
    private string $localeDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phaseolies_fileloader_' . uniqid();
        $this->localeDir = "{$this->tempDir}/en";

        mkdir($this->localeDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    /** Utility recursive delete */
    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        @rmdir($dir);
    }

    /* -------------------------------------------------------------
     |  Basic loadPath behavior
     | ------------------------------------------------------------ */

    public function testLoadPathReturnsArrayFromFile(): void
    {
        $filePath = "{$this->localeDir}/messages.php";
        file_put_contents($filePath, "<?php return ['welcome' => 'Hello'];");

        $loader = new FileLoader($this->tempDir);

        $method = (new \ReflectionClass($loader))->getMethod('loadPath');
        $method->setAccessible(true);

        $result = $method->invoke($loader, $this->tempDir, 'en', 'messages');

        $this->assertSame(['welcome' => 'Hello'], $result);
    }

    public function testLoadPathThrowsIfGroupEmpty(): void
    {
        $loader = new FileLoader($this->tempDir);

        $method = (new \ReflectionClass($loader))->getMethod('loadPath');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation group name cannot be empty');
        $method->invoke($loader, $this->tempDir, 'en', '');
    }

    public function testLoadPathThrowsIfFileMissing(): void
    {
        $loader = new FileLoader($this->tempDir);

        $method = (new \ReflectionClass($loader))->getMethod('loadPath');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $method->invoke($loader, $this->tempDir, 'en', 'missing');
    }

    /* -------------------------------------------------------------
     |  Main load() behavior
     | ------------------------------------------------------------ */

    public function testLoadReturnsLinesFromMainPath(): void
    {
        $filePath = "{$this->localeDir}/core.php";
        file_put_contents($filePath, "<?php return ['ok' => true];");

        $loader = new FileLoader($this->tempDir);
        $result = $loader->load('en', 'core');
        $this->assertSame(['ok' => true], $result);
    }

    public function testLoadFallsBackToHintNamespacePath(): void
    {
        $packageDir = "{$this->tempDir}/packages/example/en";
        mkdir($packageDir, 0777, true);
        file_put_contents("{$packageDir}/pkg.php", "<?php return ['msg' => 'from package'];");

        $loader = new FileLoader($this->tempDir);
        $loader->addNamespace('example', "{$this->tempDir}/packages/example");

        // Remove main file to trigger exception path
        $this->expectNotToPerformAssertions(); // exception path is handled internally

        try {
            $loader->load('en', 'pkg', 'example');
        } catch (\Throwable $t) {
            $this->fail('Should not throw exception for existing hint file');
        }
    }

    public function testLoadFallsBackToVendorPath(): void
    {
        $vendorDir = "{$this->tempDir}/vendor/example/en";
        mkdir($vendorDir, 0777, true);
        file_put_contents("{$vendorDir}/vendorfile.php", "<?php return ['ok' => 'vendor'];");

        $loader = new FileLoader($this->tempDir);

        // Simulate main missing, namespace with vendor path fallback
        try {
            $result = $loader->load('en', 'vendorfile', 'example');
            $this->assertSame(['ok' => 'vendor'], $result);
        } catch (\Throwable $e) {
            $this->fail("Should not throw exception when vendor file exists: {$e->getMessage()}");
        }
    }

    public function testLoadThrowsWhenNothingFound(): void
    {
        $loader = new FileLoader($this->tempDir);

        $this->expectException(\RuntimeException::class);
        $loader->load('en', 'nope', 'example');
    }

    /* -------------------------------------------------------------
     |  Namespace override merging
     | ------------------------------------------------------------ */

    public function testLoadNamespaceOverridesMergesFiles(): void
    {
        $vendorDir = "{$this->tempDir}/vendor/example/en";
        mkdir($vendorDir, 0777, true);

        file_put_contents("{$vendorDir}/messages.php", "<?php return ['override' => 'yes'];");

        $loader = new FileLoader($this->tempDir);
        $method = (new \ReflectionClass($loader))->getMethod('loadNamespaceOverrides');
        $method->setAccessible(true);

        $base = ['welcome' => 'hi'];
        $result = $method->invoke($loader, $base, 'en', 'messages', 'example');

        $this->assertSame(
            ['welcome' => 'hi', 'override' => 'yes'],
            $result
        );
    }

    public function testLoadNamespaceOverridesNoFile(): void
    {
        $loader = new FileLoader($this->tempDir);
        $method = (new \ReflectionClass($loader))->getMethod('loadNamespaceOverrides');
        $method->setAccessible(true);

        $base = ['a' => 1];
        $result = $method->invoke($loader, $base, 'en', 'none', 'example');
        $this->assertSame($base, $result);
    }

    /* -------------------------------------------------------------
     |  Namespace registration
     | ------------------------------------------------------------ */

    public function testAddNamespaceAndRetrieve(): void
    {
        $loader = new FileLoader($this->tempDir);
        $loader->addNamespace('package1', '/some/path');
        $loader->addNamespace('package2', '/another/path');

        $namespaces = $loader->namespaces();

        $this->assertArrayHasKey('package1', $namespaces);
        $this->assertArrayHasKey('package2', $namespaces);
        $this->assertSame('/another/path', $namespaces['package2']);
    }
}
