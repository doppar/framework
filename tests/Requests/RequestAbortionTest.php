<?php

namespace Tests\Unit\Requests;

require_once __DIR__ . '/../Support/MockContainer.php';

use Phaseolies\Http\Support\RequestAbortion;
use Phaseolies\Http\Request;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\DI\Container;
use stdClass;
use PHPUnit\Framework\TestCase;
use Mockery;
use Tests\Support\MockContainer;

class RequestAbortionTest extends TestCase
{
    protected RequestAbortion $requestAbortion;
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Container::setInstance(new MockContainer());
        $this->container = Container::getInstance();
        $this->requestAbortion = new RequestAbortion();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
            throw $e;
        }
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

    public function testRecordInsightExceptionDelegatesToInsightRecorder(): void
    {
        $sink = new stdClass();
        $sink->captured = [];

        $mockRequest = Mockery::mock(Request::class);
        $this->container->instance('Doppar\\Insight\\Support\\ErrorHistoryRecorder', new class($sink) {
            public function __construct(private readonly object $sink)
            {
            }

            public function record($exception, $request): void
            {
                $this->sink->captured = [$exception, $request];
            }
        });

        $exception = HttpException::fromStatusCode(404, 'Route missing');
        $method = new \ReflectionMethod($this->requestAbortion, 'recordInsightException');
        $method->invoke($this->requestAbortion, $mockRequest, $exception);

        $this->assertSame($exception, $sink->captured[0]);
        $this->assertSame($mockRequest, $sink->captured[1]);
        $this->assertSame(404, $exception->getStatusCode());
    }

    public function testAbortRecordsInsightForAjaxAbort(): void
    {
        $sink = new stdClass();
        $sink->captured = [];

        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(true);
        $mockRequest->shouldReceive('isApiRequest')->andReturn(false);
        $this->container->instance('request', $mockRequest);
        $this->container->instance('Doppar\\Insight\\Support\\ErrorHistoryRecorder', new class($sink) {
            public function __construct(private readonly object $sink)
            {
            }

            public function record($exception, $request): void
            {
                $this->sink->captured = [$exception, $request];
            }
        });

        try {
            $this->requestAbortion->abort(404, 'Ajax missing');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $this->assertInstanceOf(HttpException::class, $sink->captured[0]);
            $this->assertSame(404, $sink->captured[0]->getStatusCode());
            $this->assertSame($mockRequest, $sink->captured[1]);
            $this->assertSame(404, $exception->getStatusCode());
        }
    }
}
