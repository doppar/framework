<?php

namespace Phaseolies\Support {
    function move_uploaded_file(string $from, string $to): bool
    {
        return \Tests\Unit\Storage\Support\UploadTestHooks::moveUploadedFile($from, $to);
    }
}

namespace Phaseolies\Support\Storage {
    function config(string|array $key, mixed $default = null): mixed
    {
        return \Tests\Unit\Storage\Support\UploadTestConfig::get($key, $default);
    }
}

namespace Tests\Unit\Storage\Support {
    final class UploadTestConfig
    {
        public static array $values = [];

        public static function reset(): void
        {
            self::$values = [];
        }

        public static function setMany(array $values): void
        {
            foreach ($values as $key => $value) {
                self::set($key, $value);
            }
        }

        public static function set(string $key, mixed $value): void
        {
            $segments = explode('.', $key);
            $data = &self::$values;

            foreach ($segments as $segment) {
                if (!isset($data[$segment]) || !is_array($data[$segment])) {
                    $data[$segment] = [];
                }

                $data = &$data[$segment];
            }

            $data = $value;
        }

        public static function get(string|array $key, mixed $default = null): mixed
        {
            if (is_array($key)) {
                self::setMany($key);
                return null;
            }

            $segments = explode('.', $key);
            $data = self::$values;

            foreach ($segments as $segment) {
                if (!is_array($data) || !array_key_exists($segment, $data)) {
                    return $default;
                }

                $data = $data[$segment];
            }

            return $data;
        }
    }

    final class UploadTestHooks
    {
        public static bool $shouldSucceed = true;

        /**
         * @var array<int, array{from: string, to: string}>
         */
        public static array $calls = [];

        public static function reset(): void
        {
            self::$shouldSucceed = true;
            self::$calls = [];
        }

        public static function moveUploadedFile(string $from, string $to): bool
        {
            self::$calls[] = ['from' => $from, 'to' => $to];

            if (!self::$shouldSucceed || !is_file($from)) {
                return false;
            }

            $directory = dirname($to);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $copied = copy($from, $to);
            if ($copied) {
                unlink($from);
            }

            return $copied;
        }
    }
}

namespace Tests\Unit\Storage {

    use Phaseolies\DI\Container;
    use Phaseolies\Support\File;
    use Phaseolies\Support\Facades\Storage;
    use Phaseolies\Support\Storage\LocalFileSystem;
    use Phaseolies\Support\Storage\PublicFileSystem;
    use Phaseolies\Support\Storage\StorageFileService;
    use PHPUnit\Framework\TestCase;
    use Tests\Unit\Storage\Support\UploadTestConfig;
    use Tests\Unit\Storage\Support\UploadTestHooks;

    class FileUploadTest extends TestCase
    {
        private string $tmpDir;
        private string $localRoot;
        private string $publicRoot;
        private Container $container;

        protected function setUp(): void
        {
            parent::setUp();

            $this->tmpDir = sys_get_temp_dir() . '/doppar_upload_test_' . uniqid();
            $this->localRoot = $this->tmpDir . '/storage/app';
            $this->publicRoot = $this->tmpDir . '/storage/app/public';

            mkdir($this->localRoot, 0755, true);
            mkdir($this->publicRoot, 0755, true);

            UploadTestConfig::reset();
            UploadTestConfig::setMany([
                'filesystem.disks.local.root' => $this->localRoot,
                'filesystem.disks.public.root' => $this->publicRoot,
            ]);

            UploadTestHooks::reset();

            $this->container = new Container();
            $this->container->bind('storage', fn() => new StorageFileService(), true);
            Container::setInstance($this->container);
            Storage::setFacadeApplication(null);
        }

        protected function tearDown(): void
        {
            UploadTestHooks::reset();
            UploadTestConfig::reset();

            $this->container->flush();
            Container::forgetInstance();
            Storage::setFacadeApplication(null);

            $this->removeDirectory($this->tmpDir);

            parent::tearDown();
        }

        public function testStorageFacadeResolvesLocalDiskAndStoresUpload(): void
        {
            $disk = Storage::disk('local');
            $file = $this->makeUploadedStyleFile('invoice.txt', 'local disk content');

            $this->assertInstanceOf(LocalFileSystem::class, $disk);
            $this->assertTrue($disk->store('documents', $file, 'stored.txt'));
            $this->assertFileExists($this->localRoot . '/documents/stored.txt');
            $this->assertSame('local disk content', (string) file_get_contents($this->localRoot . '/documents/stored.txt'));
        }

        public function testStorageFacadeResolvesPublicDiskAndStoresUpload(): void
        {
            $disk = Storage::disk('public');
            $file = $this->makeUploadedStyleFile('photo.jpg', 'public image payload', 'image/jpeg');

            $this->assertInstanceOf(PublicFileSystem::class, $disk);
            $this->assertTrue($disk->store('images', $file, 'hero.jpg'));
            $this->assertFileExists($this->publicRoot . '/images/hero.jpg');
            $this->assertSame('public image payload', (string) file_get_contents($this->publicRoot . '/images/hero.jpg'));
        }

        public function testFileStoreAsStoresUploadToLocalDiskAndReturnsRelativePath(): void
        {
            $file = $this->makeUploadedStyleFile('contract.pdf', 'pdf payload', 'application/pdf');

            $storedPath = $file->storeAs('contracts', 'signed.pdf', 'local');

            $this->assertSame('contracts/signed.pdf', $storedPath);
            $this->assertFileExists($this->localRoot . '/contracts/signed.pdf');
            $this->assertSame('pdf payload', (string) file_get_contents($this->localRoot . '/contracts/signed.pdf'));
            $this->assertCount(1, UploadTestHooks::$calls);
        }

        public function testFileStoreDefaultsToPublicDiskAndGeneratesUniqueName(): void
        {
            $file = $this->makeUploadedStyleFile('avatar.png', 'avatar payload', 'image/png');

            $stored = $file->store('avatars');
            $storedFiles = glob($this->publicRoot . '/avatars/*') ?: [];

            $this->assertTrue($stored);
            $this->assertCount(1, $storedFiles);
            $this->assertMatchesRegularExpression('/^\d+_avatar\.png$/', basename($storedFiles[0]));
            $this->assertSame('avatar payload', (string) file_get_contents($storedFiles[0]));
        }

        public function testFileStoreAsStopsWhenCallbackRejectsUpload(): void
        {
            $file = $this->makeUploadedStyleFile('archive.zip', 'zip payload', 'application/zip');

            $storedPath = $file->storeAs('archives', 'blocked.zip', 'public', static fn() => false);

            $this->assertFalse($storedPath);
            $this->assertFileDoesNotExist($this->publicRoot . '/archives/blocked.zip');
            $this->assertSame([], UploadTestHooks::$calls);
        }

        public function testFileStoreAsReturnsFalseForInvalidUpload(): void
        {
            $file = $this->makeUploadedStyleFile('broken.txt', 'broken payload', 'text/plain', UPLOAD_ERR_PARTIAL);

            $storedPath = $file->storeAs('broken', 'broken.txt', 'local');

            $this->assertFalse($storedPath);
            $this->assertFileDoesNotExist($this->localRoot . '/broken/broken.txt');
            $this->assertSame([], UploadTestHooks::$calls);
        }

        private function makeUploadedStyleFile(
            string $name,
            string $contents,
            string $mimeType = 'text/plain',
            int $error = UPLOAD_ERR_OK
        ): File {
            $directory = $this->tmpDir . '/incoming';
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $path = $directory . '/' . uniqid('', true) . '_' . $name;
            file_put_contents($path, $contents);

            return new File([
                'name' => $name,
                'type' => $mimeType,
                'tmp_name' => $path,
                'error' => $error,
                'size' => filesize($path),
            ]);
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
                if (is_dir($path) && !is_link($path)) {
                    $this->removeDirectory($path);
                    continue;
                }

                unlink($path);
            }

            rmdir($dir);
        }
    }
}
