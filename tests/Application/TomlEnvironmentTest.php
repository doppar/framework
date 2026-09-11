<?php

namespace Tests\Unit\Application;

use Phaseolies\Application;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class TomlEnvironmentTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phaseolies_toml_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (['TOML_BOOL_KEY', 'TOML_INT_KEY', 'TOML_STRING_KEY', 'TOML_PRE_EXISTING_KEY'] as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
        }

        putenv('TOML_PRE_EXISTING_KEY');

        $this->deleteDirectory($this->tempDir);
    }

    public function testTomlValuesKeepRealTypesInsteadOfBecomingStrings(): void
    {
        $file = $this->tempDir . '/env.toml';
        file_put_contents($file, <<<TOML
        TOML_BOOL_KEY = false
        TOML_INT_KEY = 42
        TOML_STRING_KEY = "hello"
        TOML

        );

        $this->loadTomlEnvironment($file);

        $this->assertSame(false, $_ENV['TOML_BOOL_KEY']);
        $this->assertSame(42, $_ENV['TOML_INT_KEY']);
        $this->assertSame('hello', $_ENV['TOML_STRING_KEY']);
    }

    public function testRealEnvironmentVariableTakesPriorityOverTomlFile(): void
    {
        putenv('TOML_PRE_EXISTING_KEY=from-os');

        $file = $this->tempDir . '/env.toml';
        file_put_contents($file, 'TOML_PRE_EXISTING_KEY = "from-toml"');

        $this->loadTomlEnvironment($file);

        $this->assertArrayNotHasKey('TOML_PRE_EXISTING_KEY', $_ENV);
    }

    public function testNestedTableThrowsBecauseEnvCallSitesExpectFlatKeys(): void
    {
        $file = $this->tempDir . '/env.toml';
        file_put_contents($file, "[nested]\nkey = \"value\"");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nested');

        $this->loadTomlEnvironment($file);
    }

    private function loadTomlEnvironment(string $file): void
    {
        $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(Application::class, 'loadTomlEnvironment');
        $method->invoke($app, $file);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($dir);
    }
}
