<?php

namespace Tests\Unit\Error;

require_once __DIR__ . '/../Support/MockContainer.php';

use Phaseolies\DI\Container;
use Phaseolies\Error\JsonErrorRenderer;
use Phaseolies\Http\Response;
use Phaseolies\Http\Response\JsonResponse;
use Phaseolies\Translation\FileLoader;
use Phaseolies\Translation\Translator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\MockContainer;

class JsonErrorRendererTest extends TestCase
{
    protected string $translationPath;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new MockContainer();
        Container::setInstance($container);
        $this->translationPath = sys_get_temp_dir() . '/phaseolies_json_error_lang_' . uniqid();
        mkdir($this->translationPath . '/en', 0777, true);
        file_put_contents($this->translationPath . '/en/validation.php', <<<'PHP'
<?php

return [
    'default' => 'Validation failed.',
    'rate_limit' => ['message' => 'Too many requests.'],
    'unauthorized' => ['message' => 'Unauthorized.'],
];
PHP);
        $container->bind('translator', fn() => new Translator(new FileLoader($this->translationPath), 'en'));
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->translationPath);

        parent::tearDown();
    }

    protected function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        @rmdir($dir);
    }

    public function testRenderReturnsJsonResponseInstance(): void
    {
        $renderer = new JsonErrorRenderer();

        $response = $renderer->render(
            new RuntimeException('Boom', Response::HTTP_INTERNAL_SERVER_ERROR),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $payload = json_decode($response->getBody(), true);

        $this->assertSame('Boom', $payload['message']);
        $this->assertIsArray($payload['errors']);
        $this->assertArrayHasKey('trace', $payload['errors']);
    }

    public function testRenderUsesValidationEnvelopeForKnownStatuses(): void
    {
        $renderer = new JsonErrorRenderer();
        $errors = ['email' => ['The email field is required.']];

        $response = $renderer->render(
            new RuntimeException('Validation failed', Response::HTTP_UNPROCESSABLE_ENTITY),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $errors
        );

        $payload = json_decode($response->getBody(), true);

        $this->assertSame($errors, $payload['errors']);
        $this->assertIsString($payload['message']);
        $this->assertNotSame('', $payload['message']);
    }
}
