<?php

namespace Tests\Unit\Requests;

require_once __DIR__ . '/../Support/MockContainer.php';

use Phaseolies\Http\Support\RequestAbortion;
use Phaseolies\Http\Request;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\DI\Container;
use Phaseolies\Translation\FileLoader;
use Phaseolies\Translation\Translator;
use stdClass;
use PHPUnit\Framework\TestCase;
use Mockery;
use Tests\Support\MockContainer;

class TestableRequestAbortion extends RequestAbortion
{
    public function publicBuildErrorViewResponse(
        string $viewPath,
        int $statusCode,
        string $message = '',
        array $headers = [],
        mixed $original = null
    ) {
        return $this->buildErrorViewResponse($viewPath, $statusCode, $message, $headers, $original);
    }
}

class RequestAbortionTest extends TestCase
{
    protected RequestAbortion $requestAbortion;
    protected Container $container;
    protected string $translationPath;

    protected function setUp(): void
    {
        parent::setUp();
        Container::setInstance(new MockContainer());
        $this->container = Container::getInstance();
        $this->translationPath = sys_get_temp_dir() . '/phaseolies_request_abortion_lang_' . uniqid();
        mkdir($this->translationPath . '/en', 0777, true);
        file_put_contents($this->translationPath . '/en/validation.php', <<<'PHP'
<?php

return [
    'default' => 'Validation failed.',
    'rate_limit' => ['message' => 'Too many requests.'],
    'unauthorized' => ['message' => 'Unauthorized.'],
];
PHP);
        $this->container->bind('translator', fn() => new Translator(new FileLoader($this->translationPath), 'en'));
        $this->requestAbortion = new TestableRequestAbortion();
    }

    protected function tearDown(): void
    {
        Mockery::close();
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

    // public function testAbortThrowsHttpResponseExceptionForAjaxRequests()
    // {
    //     $this->expectException(HttpResponseException::class);
    //     $this->expectExceptionMessage('Not Found');
    //     $this->expectExceptionCode(404);

    //     // Mock the Request object
    //     $mockRequest = Mockery::mock(Request::class);
    //     $mockRequest->shouldReceive('isAjax')->andReturn(true);
    //     $mockRequest->shouldReceive('is')->with('/api/*')->andReturn(false);

    //     // Bind the mock to the container
    //     $this->container->bind('request', fn() => $mockRequest);

    //     $this->requestAbortion->abort(404, '');
    // }

    // public function testAbortThrowsHttpResponseExceptionForApiRoutes()
    // {
    //     $this->expectException(HttpResponseException::class);
    //     $this->expectExceptionMessage('Unauthorized');
    //     $this->expectExceptionCode(401);

    //     // Mock the Request object for API route
    //     $mockRequest = Mockery::mock(Request::class);
    //     $mockRequest->shouldReceive('isAjax')->andReturn(false);
    //     $mockRequest->shouldReceive('is')->with('/api/*')->andReturn(true);

    //     // Bind the mock to the container
    //     $this->container->bind('request', fn() => $mockRequest);

    //     $this->requestAbortion->abort(401, '');
    // }

    public function testAbortThrowsHttpExceptionForNonAjaxNonApiRequests()
    {
        $this->expectException(HttpResponseException::class);

        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(true);
        $mockRequest->shouldReceive('isApiRequest')->andReturn(false);
        $this->container->instance('request', $mockRequest);

        try {
            $this->requestAbortion->abort(403, 'Forbidden');
        } catch (HttpResponseException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertSame('Forbidden', $e->getValidationErrors());
            $this->assertTrue($e->hasResponse());
            $this->assertSame(403, $e->getResponse()?->getStatusCode());
            $this->assertStringContainsString('Forbidden', $e->getResponse()?->getBody() ?? '');
            throw $e;
        }
    }

    public function testBuildErrorViewResponseMakesMessageAvailableToView(): void
    {
        $viewPath = sys_get_temp_dir() . '/phaseolies_request_abort_error_' . uniqid() . '.php';
        file_put_contents($viewPath, <<<'PHP'
<?php echo $message === 'Route [/home] not found' ? 'Not Found' : $message; ?>
PHP);

        $exception = HttpException::fromStatusCode(404, 'Route [/home] not found');
        ob_start();
        $response = $this->requestAbortion->publicBuildErrorViewResponse(
            $viewPath,
            404,
            'Route [/home] not found',
            [],
            $exception
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', trim($response->getBody() ?? ''));
        $this->assertSame($exception, $response->getOriginal());

        @unlink($viewPath);
    }

    public function testAbortThrowsHttpExceptionWithHeaders()
    {
        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(true);
        $mockRequest->shouldReceive('isApiRequest')->andReturn(false);
        $this->container->instance('request', $mockRequest);

        try {
            $this->requestAbortion->abort(403, 'Forbidden', ['X-Custom-Header' => 'Value']);
            $this->fail('Expected HttpResponseException was not thrown');
        } catch (HttpResponseException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertSame('Forbidden', $e->getValidationErrors());
            $this->assertSame('Value', $e->getResponse()?->headers->get('X-Custom-Header'));
        }
    }

    public function testAbortIfDoesNotThrowWhenConditionIsFalse()
    {
        $this->requestAbortion->abortIf(false, 500, 'Should not throw');
        $this->assertTrue(true);
    }

    public function testAbortIfThrowsWhenConditionIsTrue()
    {
        $this->expectException(HttpResponseException::class);

        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(true);
        $mockRequest->shouldReceive('isApiRequest')->andReturn(false);
        $this->container->instance('request', $mockRequest);

        try {
            $this->requestAbortion->abortIf(true, 400, 'Bad Request');
        } catch (HttpResponseException $e) {
            $this->assertEquals(400, $e->getStatusCode());
            $this->assertSame('Bad Request', $e->getValidationErrors());
            throw $e;
        }
    }

    public function testAbortIfWithHeaders()
    {
        $this->expectException(HttpResponseException::class);

        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(true);
        $mockRequest->shouldReceive('isApiRequest')->andReturn(false);
        $this->container->instance('request', $mockRequest);

        try {
            $this->requestAbortion->abortIf(true, 401, 'Unauthorized', ['X-Test' => 'HeaderValue']);
        } catch (HttpResponseException $e) {
            $this->assertEquals(401, $e->getStatusCode());
            $this->assertSame('Unauthorized', $e->getValidationErrors());
            throw $e;
        }
    }

    public function testAbortIfWithFalseConditionDoesNotCallAbort()
    {
        // This should not throw any exception and should not call request()
        $result = $this->requestAbortion->abortIf(false, 500, 'Should not execute');

        $this->assertNull($result);
    }

    public function testAbortWithEmptyMessage()
    {
        $this->expectException(HttpResponseException::class);

        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(true);
        $mockRequest->shouldReceive('isApiRequest')->andReturn(false);
        $this->container->instance('request', $mockRequest);

        try {
            $this->requestAbortion->abort(403);
        } catch (HttpResponseException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertSame('', $e->getValidationErrors());
            throw $e;
        }
    }

    public function testAbortWithEmptyHeaders()
    {
        $this->expectException(HttpResponseException::class);

        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(true);
        $mockRequest->shouldReceive('isApiRequest')->andReturn(false);
        $this->container->instance('request', $mockRequest);

        try {
            $this->requestAbortion->abort(404, '', []);
        } catch (HttpResponseException $e) {
            $this->assertEquals(404, $e->getStatusCode());
            $this->assertSame('', $e->getValidationErrors());
            throw $e;
        }
    }
}
