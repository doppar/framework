<?php

namespace Tests\Validation;

use Tests\Support\MockContainer;
use Phaseolies\Translation\Translator;
use Phaseolies\Translation\FileLoader;
use Phaseolies\Support\Validation\Sanitizer;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class FileValidationRuleTest extends TestCase
{
    private const PDF_BYTES = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind onto the same container instance we activate — bindings are
        // per-instance now (see [[ArchNotes]] in DI/Container.php).
        $container = new MockContainer();
        Container::setInstance($container);
        $container->bind('translator', function () {
            $loader = $this->createMock(FileLoader::class);
            return new Translator($loader, 'en');
        });

        $this->tmpDir = sys_get_temp_dir() . '/doppar_mimes_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $_FILES = [];
    }

    protected function tearDown(): void
    {
        $_FILES = [];

        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);

        Container::forgetInstance();
        parent::tearDown();
    }

    private function registerUploadedFile(
        string $field,
        string $contents,
        string $claimedName = 'upload',
        string $claimedType = 'application/octet-stream',
        int $error = UPLOAD_ERR_OK
    ): void {
        $path = $this->tmpDir . '/' . bin2hex(random_bytes(8));
        file_put_contents($path, $contents);

        $_FILES[$field] = [
            'name' => $claimedName,
            'type' => $claimedType,
            'tmp_name' => $path,
            'error' => $error,
            'size' => filesize($path),
        ];
    }

    private function passes(array $rules): bool
    {
        return (new Sanitizer([], $rules))->validate();
    }

    public function testMimesRejectsScriptPayloadDisguisedWithAnAllowedExtension(): void
    {
        $this->registerUploadedFile('file', '<?php system($_GET["c"]); ?>', 'shell.pdf', 'application/pdf');

        $this->assertFalse($this->passes(['file' => 'mimes:pdf,doc']));
    }

    public function testMimesRejectsScriptPayloadEvenWithSpoofedClientContentType(): void
    {
        // The client Content-Type header is attacker-controlled; this proves
        // the rule doesn't fall back to trusting it.
        $this->registerUploadedFile('file', '<?php system($_GET["c"]); ?>', 'shell.pdf', 'application/pdf');

        $this->assertFalse($this->passes(['file' => 'mimes:pdf']));
    }

    public function testMimesAcceptsRealContentMatchingAnAllowedExtension(): void
    {
        $this->registerUploadedFile('file', self::PDF_BYTES, 'contract.pdf', 'application/pdf');

        $this->assertTrue($this->passes(['file' => 'mimes:pdf,doc']));
    }

    public function testMimesRejectsRealContentNotMatchingAnyAllowedExtension(): void
    {
        $this->registerUploadedFile('file', 'just plain text', 'notes.txt', 'text/plain');

        $this->assertFalse($this->passes(['file' => 'mimes:pdf,doc']));
    }

    public function testMimesAcceptsRealImageContentRegardlessOfClaimedContentType(): void
    {
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $this->registerUploadedFile('file', $pngBytes, 'photo.jpg', 'application/octet-stream');

        $this->assertTrue($this->passes(['file' => 'mimes:jpg,jpeg,png']));
    }

    public function testMimesIsCaseInsensitiveForTheAllowedExtensionList(): void
    {
        $this->registerUploadedFile('file', self::PDF_BYTES, 'contract.PDF', 'application/pdf');

        $this->assertTrue($this->passes(['file' => 'mimes:PDF']));
    }

    public function testMimesCombinesWithRequiredAndRejectsWhenNoFileIsUploaded(): void
    {
        $_FILES = [];

        $this->assertFalse($this->passes(['file' => 'required|mimes:pdf']));
    }

    public function testImageRuleStillRejectsScriptPayloadDisguisedAsImage(): void
    {
        $this->registerUploadedFile('file', '<?php system($_GET["c"]); ?>', 'avatar.jpg', 'image/jpeg');

        $this->assertFalse($this->passes(['file' => 'image']));
    }
}
