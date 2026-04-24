<?php

namespace Tests\Unit\Storage;

use Phaseolies\Support\Storage\FileSystem;
use PHPUnit\Framework\TestCase;

class FileSystemTest extends TestCase
{
    private string $tmpDir;
    private FileSystem $fs;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/doppar_fs_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->fs = new FileSystem($this->tmpDir);
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

    public function testIsDirectoryReturnsTrueForExistingDirectory()
    {
        $this->assertTrue($this->fs->isDirectory($this->tmpDir));
    }

    public function testIsDirectoryReturnsFalseForNonExistingDirectory()
    {
        $this->assertFalse($this->fs->isDirectory($this->tmpDir . '/nonexistent'));
    }

    public function testIsFileReturnsTrueForExistingFile()
    {
        $filePath = $this->tmpDir . '/test.txt';
        file_put_contents($filePath, 'content');

        $this->assertTrue($this->fs->isFile($filePath));
    }

    public function testIsFileReturnsFalseForNonExistingFile()
    {
        $this->assertFalse($this->fs->isFile($this->tmpDir . '/missing.txt'));
    }

    public function testIsFileReturnsFalseForDirectory()
    {
        $this->assertFalse($this->fs->isFile($this->tmpDir));
    }

    public function testMakeDirectoryCreatesDirectory()
    {
        $newDir = $this->tmpDir . '/subdir';

        $result = $this->fs->makeDirectory($newDir, 0755, false);

        $this->assertTrue($result);
        $this->assertTrue(is_dir($newDir));
    }

    public function testMakeDirectoryCreatesNestedDirectoriesRecursively()
    {
        $nestedDir = $this->tmpDir . '/a/b/c';

        $result = $this->fs->makeDirectory($nestedDir, 0755, true);

        $this->assertTrue($result);
        $this->assertTrue(is_dir($nestedDir));
    }

    public function testMakeDirectoryWithForceFlag()
    {
        $newDir = $this->tmpDir . '/forced';

        $result = $this->fs->makeDirectory($newDir, 0755, true, true);

        $this->assertTrue($result);
    }

    public function testDestinationFileBuildsCorrectPath()
    {
        $result = $this->fs->destinationFile('uploads', 'image.jpg');

        $this->assertEquals($this->tmpDir . '/uploads/image.jpg', $result);
    }

    public function testStoreageBasePathReturnsFilePath()
    {
        $this->assertEquals($this->tmpDir, $this->fs->storeageBasePath());
    }

    public function testIsDirectoryExistsCreatesDirectoryIfMissing()
    {
        $subPath = 'new_sub_' . uniqid();

        $returned = $this->fs->isDirectoryExists($subPath);

        $this->assertEquals($subPath, $returned);
        $this->assertTrue(is_dir($this->tmpDir . '/' . $subPath));
    }

    public function testIsDirectoryExistsReturnsSamePathWhenAlreadyExists()
    {
        $sub = 'existing_sub';
        mkdir($this->tmpDir . '/' . $sub, 0755, true);

        $returned = $this->fs->isDirectoryExists($sub);

        $this->assertEquals($sub, $returned);
    }

    public function testWriteStreamWritesFileContent()
    {
        $content    = 'stream content test';
        $sourceFile = $this->tmpDir . '/source.txt';
        file_put_contents($sourceFile, $content);

        $stream = fopen($sourceFile, 'rb');

        $result = $this->fs->writeStream('', 'output.txt', $stream);

        $this->assertTrue($result);
        $this->assertFileExists($this->tmpDir . '//output.txt');
        $this->assertEquals($content, file_get_contents($this->tmpDir . '//output.txt'));
    }

    public function testGetFileNameReturnsOriginalNameWhenNoCustomName()
    {
        $filePath = $this->tmpDir . '/original_name.txt';
        file_put_contents($filePath, 'data');

        $file = new \Phaseolies\Support\File($filePath);

        $result = $this->fs->getFileName($file);

        $this->assertEquals('original_name.txt', $result);
    }

    public function testGetFileNameUsesConfiguredBaseNameAndDetectedExtension()
    {
        $pngPath = $this->tmpDir . '/avatar.png';
        file_put_contents($pngPath, 'png data');

        $file = new \Phaseolies\Support\File([
            'name' => 'avatar.png',
            'type' => 'image/png',
            'tmp_name' => $pngPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($pngPath),
        ]);

        $customFs = new FileSystem($this->tmpDir, 'profile_image');

        $this->assertSame('profile_image.png', $customFs->getFileName($file));
    }

    public function testPutReturnsFalseWhenSourceStreamCannotBeOpened()
    {
        $file = new \Phaseolies\Support\File([
            'name' => 'missing.txt',
            'type' => 'text/plain',
            'tmp_name' => $this->tmpDir . '/does-not-exist.txt',
            'error' => UPLOAD_ERR_OK,
            'size' => 0,
        ]);

        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        }, E_WARNING);

        try {
            $this->assertFalse($this->fs->put('uploads', $file));
        } finally {
            restore_error_handler();
        }

        $this->assertNotNull($warning);
        $this->assertStringContainsString('Failed to open stream', $warning);
    }

    public function testPutWritesUsingConfiguredCustomFileName()
    {
        $source = $this->tmpDir . '/photo-source.jpg';
        file_put_contents($source, 'jpeg data');

        $file = new \Phaseolies\Support\File([
            'name' => 'photo.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $source,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($source),
        ]);

        $customFs = new FileSystem($this->tmpDir, 'avatar');
        $customFs->makeDirectory($this->tmpDir . '/uploads', 0755, true);

        $this->assertTrue($customFs->put('uploads', $file));
        $this->assertFileExists($this->tmpDir . '/uploads/avatar.jpeg');
    }
}
