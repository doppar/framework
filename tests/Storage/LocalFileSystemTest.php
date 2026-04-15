<?php

namespace Tests\Unit\Storage;

use Phaseolies\Support\Storage\LocalFileSystem;
use Phaseolies\Support\Storage\FileNotFoundException;
use Phaseolies\Support\File;
use PHPUnit\Framework\TestCase;

class LocalFileSystemTest extends TestCase
{
    private string $tmpDir;
    private LocalFileSystem $fs;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/doppar_local_fs_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->fs = new LocalFileSystem($this->tmpDir);
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
        file_put_contents($path, $content);
        return $path;
    }

    public function testGetReturnsFullPathWhenFileExists()
    {
        $this->createTempFile('document.txt');

        $result = $this->fs->get('document.txt');

        $this->assertEquals($this->tmpDir . '/document.txt', $result);
    }

    public function testGetReturnsNullWhenFileDoesNotExist()
    {
        $result = $this->fs->get('missing.txt');

        $this->assertNull($result);
    }

    public function testContentReturnsFileContents()
    {
        $this->createTempFile('readme.txt', 'Hello, Doppar!');

        $result = $this->fs->content('readme.txt');

        $this->assertEquals('Hello, Doppar!', $result);
    }

    public function testContentThrowsFileNotFoundExceptionForMissingFile()
    {
        $this->expectException(FileNotFoundException::class);

        $this->fs->content('nonexistent.txt');
    }

    public function testDeleteReturnsTrueAndRemovesFile()
    {
        $this->createTempFile('to_delete.txt');

        $result = $this->fs->delete('to_delete.txt');

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tmpDir . '/to_delete.txt');
    }

    public function testDeleteReturnsFalseWhenFileDoesNotExist()
    {
        $result = $this->fs->delete('ghost_file.txt');

        $this->assertFalse($result);
    }

    public function testDeleteMultipleFilesReturnsTrueWhenAllDeleted()
    {
        $this->createTempFile('file1.txt');
        $this->createTempFile('file2.txt');

        $result = $this->fs->delete(['file1.txt', 'file2.txt']);

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tmpDir . '/file1.txt');
        $this->assertFileDoesNotExist($this->tmpDir . '/file2.txt');
    }

    public function testDeleteMultipleFilesReturnsFalseWhenOneIsMissing()
    {
        $this->createTempFile('exists.txt');

        $result = $this->fs->delete(['exists.txt', 'missing.txt']);

        $this->assertFalse($result);
    }

    public function testStoreageBasePathReturnsConfiguredPath()
    {
        $this->assertEquals($this->tmpDir, $this->fs->storeageBasePath());
    }

    public function testStoreMovesFileToCorrectPath()
    {
        $sourceFile = $this->tmpDir . '/source_upload.txt';
        file_put_contents($sourceFile, 'upload data');

        $file = new File([
            'name'     => 'upload.txt',
            'type'     => 'text/plain',
            'tmp_name' => $sourceFile,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($sourceFile),
        ]);

        $subDir = 'uploads_' . uniqid();

        $result = $this->fs->store($subDir, $file, 'stored_file.txt');

        $this->assertTrue($result);
        $this->assertFileExists($this->tmpDir . '/' . $subDir . '/stored_file.txt');
    }

    public function testContentReadsMultilineFile()
    {
        $content = "line one\nline two\nline three";
        $this->createTempFile('multi.txt', $content);

        $result = $this->fs->content('multi.txt');

        $this->assertStringContainsString('line one', $result);
        $this->assertStringContainsString('line three', $result);
    }
}
