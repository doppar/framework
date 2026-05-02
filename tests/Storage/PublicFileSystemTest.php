<?php

namespace Tests\Unit\Storage;

use Phaseolies\Support\Storage\PublicFileSystem;
use PHPUnit\Framework\TestCase;

class PublicFileSystemTest extends TestCase
{
    private string $tmpDir;
    private PublicFileSystem $fs;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/doppar_public_fs_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->fs = new PublicFileSystem($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function createTempFile(string $name, string $content = 'test content'): string
    {
        $path = $this->tmpDir . '/' . $name;
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $content);

        return $path;
    }

    public function testDeleteReturnsTrueAndRemovesFile(): void
    {
        $this->createTempFile('to_delete.txt');

        $result = $this->fs->delete('to_delete.txt');

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tmpDir . '/to_delete.txt');
    }

    public function testDeleteMultipleFilesReturnsTrueWhenAllDeleted(): void
    {
        $this->createTempFile('first.txt');
        $this->createTempFile('nested/second.txt');

        $result = $this->fs->delete(['first.txt', 'nested/second.txt']);

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tmpDir . '/first.txt');
        $this->assertFileDoesNotExist($this->tmpDir . '/nested/second.txt');
    }

    public function testDeleteReturnsFalseWhenFileDoesNotExist(): void
    {
        $result = $this->fs->delete('missing.txt');

        $this->assertFalse($result);
    }
}
