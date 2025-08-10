<?php

namespace Tests\Unit;

use Phaseolies\Http\Support\RequestAbortion;
use Phaseolies\Http\Exceptions\HttpException;
use PHPUnit\Framework\TestCase;

class RequestAbortionTest extends TestCase
{
    protected RequestAbortion $requestAbortion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestAbortion = new RequestAbortion();
    }

    // public function testAbortThrowsHttpException()
    // {
    //     $this->expectException(HttpException::class);
    //     $this->expectExceptionMessage('Not Found');

    //     try {
    //         $this->requestAbortion->abort(404, 'Not Found');
    //     } catch (HttpException $e) {
    //         $this->assertEquals(404, $e->getStatusCode());
    //         throw $e;
    //     }
    // }

    // public function testAbortThrowsHttpExceptionWithHeaders()
    // {
    //     try {
    //         $this->requestAbortion->abort(403, 'Forbidden', ['X-Custom-Header' => 'Value']);
    //         $this->fail('Expected HttpException was not thrown');
    //     } catch (HttpException $e) {
    //         $this->assertEquals(403, $e->getStatusCode());
    //         $this->assertEquals('Forbidden', $e->getMessage());
    //         $this->assertArrayHasKey('X-Custom-Header', $e->getHeaders());
    //         $this->assertEquals('Value', $e->getHeaders()['X-Custom-Header']);
    //     }
    // }

    public function testAbortIfDoesNotThrowWhenConditionIsFalse()
    {
        $this->requestAbortion->abortIf(false, 500, 'Should not throw');
        $this->assertTrue(true);
    }

    // public function testAbortIfThrowsWhenConditionIsTrue()
    // {
    //     $this->expectException(HttpException::class);
    //     $this->expectExceptionMessage('Bad Request');

    //     try {
    //         $this->requestAbortion->abortIf(true, 400, 'Bad Request');
    //     } catch (HttpException $e) {
    //         $this->assertEquals(400, $e->getStatusCode());
    //         throw $e;
    //     }
    // }
    // public function testAbortIfWithHeaders()
    // {
    //     $this->expectException(HttpException::class);

    //     try {
    //         $this->requestAbortion->abortIf(true, 401, 'Unauthorized', ['X-Test' => 'HeaderValue']);
    //     } catch (HttpException $e) {
    //         $this->assertEquals(401, $e->getStatusCode());
    //         $this->assertEquals('Unauthorized', $e->getMessage());
    //         $this->assertArrayHasKey('X-Test', $e->getHeaders());
    //         $this->assertEquals('HeaderValue', $e->getHeaders()['X-Test']);
    //         throw $e;
    //     }
    // }
}
