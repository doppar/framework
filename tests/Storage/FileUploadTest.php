<?php

namespace Phaseolies\Support {
    function move_uploaded_file(string $from, string $to): bool
    {
        return \Tests\Unit\Storage\Support\UploadTestHooks::moveUploadedFile($from, $to);
    }

    function is_uploaded_file(string $filename): bool
    {
        return \Tests\Unit\Storage\Support\UploadTestHooks::isUploadedFile($filename);
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

        public static bool $isUploadedFile = true;

        /**
         * @var array<int, array{from: string, to: string}>
         */
        public static array $calls = [];

        public static function reset(): void
        {
            self::$shouldSucceed = true;
            self::$isUploadedFile = true;
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

        public static function isUploadedFile(string $filename): bool
        {
            return self::$isUploadedFile;
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
    use PHPUnit\Framework\Attributes\DataProvider;
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

        /**
         * @return array<string, array{0: int}>
         */
        public static function uploadErrorCodeProvider(): array
        {
            return [
                'ini size exceeded' => [UPLOAD_ERR_INI_SIZE],
                'form max size exceeded' => [UPLOAD_ERR_FORM_SIZE],
                'partial upload' => [UPLOAD_ERR_PARTIAL],
                'no tmp dir' => [UPLOAD_ERR_NO_TMP_DIR],
                'cant write' => [UPLOAD_ERR_CANT_WRITE],
                'extension blocked' => [UPLOAD_ERR_EXTENSION],
            ];
        }

        #[DataProvider('uploadErrorCodeProvider')]
        public function testStoreAndStoreAsRejectEveryUploadErrorCodeWithoutTouchingDisk(int $errorCode): void
        {
            $file = $this->makeUploadedStyleFile('report.csv', 'a,b,c', 'text/csv', $errorCode);

            $this->assertFalse($file->isValid());
            $this->assertFalse($file->store('reports'));
            $this->assertFalse($file->storeAs('reports', 'report.csv', 'local'));
            $this->assertFalse($file->move($this->tmpDir . '/moved'));
            $this->assertSame([], UploadTestHooks::$calls);
            $this->assertSame([], glob($this->publicRoot . '/reports/*') ?: []);
        }

        public function testGenerateUniqueNameStripsPathTraversalFromClientFileName(): void
        {
            $file = $this->makeUploadedStyleFile('../../../../etc/passwd', 'payload', 'text/plain');

            $unique = $file->generateUniqueName();

            $this->assertStringNotContainsString('/', $unique);
            $this->assertStringNotContainsString('..', $unique);
            $this->assertMatchesRegularExpression('/^\d+_passwd$/', $unique);
        }

        public function testStoreContainsUploadEvenWhenClientFileNameAttemptsTraversal(): void
        {
            $file = $this->makeUploadedStyleFile('../../../../etc/passwd', 'malicious payload', 'text/plain');

            $this->assertTrue($file->store('uploads'));

            $storedFiles = glob($this->publicRoot . '/uploads/*') ?: [];
            $this->assertCount(1, $storedFiles);
            $this->assertStringStartsWith($this->publicRoot . '/uploads/', $storedFiles[0]);
            $this->assertStringNotContainsString('..', basename($storedFiles[0]));
        }

        public function testStoreAsContainsUploadEvenWhenExplicitFileNameAttemptsTraversal(): void
        {
            $file = $this->makeUploadedStyleFile('note.txt', 'malicious payload', 'text/plain');

            $storedPath = $file->storeAs('uploads', '../../../../evil.php', 'local');

            $this->assertNotFalse($storedPath);
            $this->assertFileExists($this->localRoot . '/uploads/evil.php');
            $this->assertFileDoesNotExist(dirname($this->tmpDir) . '/evil.php');
        }

        public function testStoreAsSanitizesWindowsStyleBackslashPathInFileName(): void
        {
            $file = $this->makeUploadedStyleFile('note.txt', 'payload', 'text/plain');

            $storedPath = $file->storeAs('uploads', '..\\..\\..\\evil.txt', 'local');

            $this->assertSame('uploads/evil.txt', $storedPath);
            $this->assertFileExists($this->localRoot . '/uploads/evil.txt');
        }

        public function testGenerateUniqueNameStripsNullBytesAndControlCharacters(): void
        {
            $file = $this->makeUploadedStyleFile("evil\0.php\x01\x02.txt", 'payload', 'text/plain');

            $unique = $file->generateUniqueName();

            $this->assertStringNotContainsString("\0", $unique);
            $this->assertStringNotContainsString("\x01", $unique);
        }

        public function testStoreAsAvoidsWindowsReservedDeviceNames(): void
        {
            $file = $this->makeUploadedStyleFile('payload.txt', 'payload', 'text/plain');

            $storedPath = $file->storeAs('uploads', 'CON.txt', 'local');

            $this->assertSame('uploads/_CON.txt', $storedPath);
            $this->assertFileExists($this->localRoot . '/uploads/_CON.txt');
        }

        public function testGenerateUniqueNameTruncatesExcessivelyLongClientFileName(): void
        {
            $longName = str_repeat('a', 500) . '.txt';
            $file = $this->makeUploadedStyleFile($longName, 'payload', 'text/plain');

            $unique = $file->generateUniqueName();

            $this->assertLessThan(200, strlen($unique));
            $this->assertStringEndsWith('.txt', $unique);
        }

        public function testIsImageReturnsFalseForSpoofedContentType(): void
        {
            $file = $this->makeUploadedStyleFile('avatar.jpg', 'this is not really an image', 'image/jpeg');

            $this->assertFalse($file->isImage());
            $this->assertFalse($file->isMimeType('image/jpeg'));
        }

        public function testIsImageReturnsTrueForRealImageContentRegardlessOfClaimedType(): void
        {
            $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
            $file = $this->makeUploadedStyleFile('photo.png', $pngBytes, 'application/octet-stream');

            $this->assertTrue($file->isImage());
            $this->assertTrue($file->isMimeType('image/png'));
        }

        public function testIsDocumentDetectsRealPdfContent(): void
        {
            $pdfBytes = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
            $file = $this->makeUploadedStyleFile('contract.pdf', $pdfBytes, 'application/pdf');

            $this->assertTrue($file->isDocument());
        }

        public function testFileControllerStyleImageGateRejectsScriptDisguisedAsImage(): void
        {
            $file = $this->makeUploadedStyleFile('avatar.jpg', '<?php system($_GET["c"]); ?>', 'image/jpeg');

            $storedPath = $file->storeAs('avatars', 'avatar.jpg', 'public', static fn(File $f) => $f->isImage());

            $this->assertFalse($storedPath);
            $this->assertFileDoesNotExist($this->publicRoot . '/avatars/avatar.jpg');
        }

        public function testMoveUsesMoveUploadedFileForAGenuineUpload(): void
        {
            $file = $this->makeUploadedStyleFile('genuine.txt', 'genuine payload', 'text/plain');
            $destination = $this->tmpDir . '/moved';

            UploadTestHooks::$isUploadedFile = true;

            $this->assertTrue($file->move($destination));
            $this->assertFileExists($destination . '/genuine.txt');
            $this->assertCount(1, UploadTestHooks::$calls);
        }

        public function testMoveFallsBackToRenameWhenSourceIsNotAGenuineUpload(): void
        {
            $file = $this->makeUploadedStyleFile('existing.txt', 'existing payload', 'text/plain');
            $destination = $this->tmpDir . '/moved';

            UploadTestHooks::$isUploadedFile = false;

            $this->assertTrue($file->move($destination));
            $this->assertFileExists($destination . '/existing.txt');
            $this->assertSame([], UploadTestHooks::$calls);
        }

        public function testMoveSanitizesDefaultAndExplicitFileNames(): void
        {
            $file = $this->makeUploadedStyleFile('../../evil.sh', 'payload', 'text/plain');
            $destination = $this->tmpDir . '/moved';

            $this->assertTrue($file->move($destination));
            $this->assertFileExists($destination . '/evil.sh');
            $this->assertFileDoesNotExist(dirname($this->tmpDir) . '/evil.sh');
        }

        public function testStoreAsReturnsFalseForUnconfiguredDisk(): void
        {
            $file = $this->makeUploadedStyleFile('note.txt', 'payload', 'text/plain');

            $storedPath = $file->storeAs('uploads', 'note.txt', 'missing-disk');

            $this->assertFalse($storedPath);
            $this->assertSame([], UploadTestHooks::$calls);
        }

        public function testMultipleFilesStoreIndependentlyWithoutSharedState(): void
        {
            $first = $this->makeUploadedStyleFile('one.txt', 'first payload', 'text/plain');
            $second = $this->makeUploadedStyleFile('two.txt', 'second payload', 'text/plain');

            $firstPath = $first->storeAs('multi', 'one.txt', 'local');
            $secondPath = $second->storeAs('multi', 'two.txt', 'local');

            $this->assertSame('multi/one.txt', $firstPath);
            $this->assertSame('multi/two.txt', $secondPath);
            $this->assertSame('first payload', (string) file_get_contents($this->localRoot . '/multi/one.txt'));
            $this->assertSame('second payload', (string) file_get_contents($this->localRoot . '/multi/two.txt'));
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

            // A real upload's tmp_name is always a random OS-assigned path,
            // unrelated to the client-supplied name, so build it that way
            // here too rather than embedding (possibly malicious) $name.
            $path = $directory . '/' . bin2hex(random_bytes(8));
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
