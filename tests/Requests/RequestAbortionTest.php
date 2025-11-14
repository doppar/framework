<?php

namespace Tests\Unit\Requests;

use Phaseolies\Http\Support\RequestAbortion;
use Phaseolies\Http\Request;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\DI\Container;
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
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Forbidden');

        // Mock the Request object for regular web request
        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(false);
        $mockRequest->shouldReceive('is')->with('/api/*')->andReturn(false);

        // Bind the mock to the container
        $this->container->bind('request', fn() => $mockRequest);

        try {
            $this->requestAbortion->abort(403, 'Forbidden');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function testAbortThrowsHttpExceptionWithHeaders()
    {
        // Mock the Request object
        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(false);
        $mockRequest->shouldReceive('is')->with('/api/*')->andReturn(false);

        // Bind the mock to the container
        $this->container->bind('request', fn() => $mockRequest);

        try {
            $this->requestAbortion->abort(403, 'Forbidden', ['X-Custom-Header' => 'Value']);
            $this->fail('Expected HttpException was not thrown');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals('Forbidden', $e->getMessage());
            $this->assertArrayHasKey('X-Custom-Header', $e->getHeaders());
            $this->assertEquals('Value', $e->getHeaders()['X-Custom-Header']);
        }
    }

    public function testAbortIfDoesNotThrowWhenConditionIsFalse()
    {
        $this->requestAbortion->abortIf(false, 500, 'Should not throw');
        $this->assertTrue(true);
    }

    public function testAbortIfThrowsWhenConditionIsTrue()
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Bad Request');

        // Mock the Request object
        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(false);
        $mockRequest->shouldReceive('is')->with('/api/*')->andReturn(false);

        // Bind the mock to the container
        $this->container->bind('request', fn() => $mockRequest);

        try {
            $this->requestAbortion->abortIf(true, 400, 'Bad Request');
        } catch (HttpException $e) {
            $this->assertEquals(400, $e->getStatusCode());
            throw $e;
        }
    }

    public function testAbortIfWithHeaders()
    {
        $this->expectException(HttpException::class);

        // Mock the Request object
        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(false);
        $mockRequest->shouldReceive('is')->with('/api/*')->andReturn(false);

        // Bind the mock to the container
        $this->container->bind('request', fn() => $mockRequest);

        try {
            $this->requestAbortion->abortIf(true, 401, 'Unauthorized', ['X-Test' => 'HeaderValue']);
        } catch (HttpException $e) {
            $this->assertEquals(401, $e->getStatusCode());
            $this->assertEquals('Unauthorized', $e->getMessage());
            $this->assertArrayHasKey('X-Test', $e->getHeaders());
            $this->assertEquals('HeaderValue', $e->getHeaders()['X-Test']);
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
        $this->expectException(HttpException::class);

        // Mock the Request object
        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(false);
        $mockRequest->shouldReceive('is')->with('/api/*')->andReturn(false);

        // Bind the mock to the container
        $this->container->bind('request', fn() => $mockRequest);

        try {
            $this->requestAbortion->abort(403);
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals('', $e->getMessage());
            throw $e;
        }
    }

    public function testAbortWithEmptyHeaders()
    {
        $this->expectException(HttpException::class);

        // Mock the Request object
        $mockRequest = Mockery::mock(Request::class);
        $mockRequest->shouldReceive('isAjax')->andReturn(false);
        $mockRequest->shouldReceive('is')->with('/api/*')->andReturn(false);

        // Bind the mock to the container
        $this->container->bind('request', fn() => $mockRequest);

        try {
            $this->requestAbortion->abort(404, '', []);
        } catch (HttpException $e) {
            $this->assertEquals(404, $e->getStatusCode());
            $this->assertEmpty($e->getHeaders());
            throw $e;
        }
    }
}
