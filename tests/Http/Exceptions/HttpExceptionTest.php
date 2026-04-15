<?php

namespace Tests\Unit\Http\Exceptions;

use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Http\Exceptions\NotFoundHttpException;
use Phaseolies\Http\Exceptions\BadRequestHttpException;
use Phaseolies\Http\Exceptions\AccessDeniedHttpException;
use Phaseolies\Http\Exceptions\ConflictHttpException;
use Phaseolies\Http\Exceptions\InternalServerErrorHttpException;
use Phaseolies\Http\Exceptions\LockedHttpException;
use Phaseolies\Http\Exceptions\NotAcceptableHttpException;
use Phaseolies\Http\Exceptions\ServiceUnavailableHttpException;
use Phaseolies\Http\Exceptions\TooManyRequestsHttpException;
use Phaseolies\Http\Exceptions\UnprocessableEntityHttpException;
use Phaseolies\Http\Exceptions\UnsupportedMediaTypeHttpException;
use Phaseolies\Http\Exceptions\TokenMismatchException;
use Phaseolies\Http\Exceptions\RouteNameNotFoundException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HttpExceptionTest extends TestCase
{
    public function testHttpExceptionStoresStatusCode()
    {
        $e = new HttpException(418, 'I am a teapot');

        $this->assertEquals(418, $e->getStatusCode());
    }

    public function testHttpExceptionStoresMessage()
    {
        $e = new HttpException(400, 'Bad Request');

        $this->assertEquals('Bad Request', $e->getMessage());
    }

    public function testHttpExceptionStoresHeaders()
    {
        $headers = ['X-Custom' => 'value'];
        $e       = new HttpException(400, '', null, $headers);

        $this->assertEquals($headers, $e->getHeaders());
    }

    public function testHttpExceptionSetHeaders()
    {
        $e = new HttpException(400);
        $e->setHeaders(['Retry-After' => '120']);

        $this->assertEquals(['Retry-After' => '120'], $e->getHeaders());
    }

    public function testHttpExceptionExtendsRuntimeException()
    {
        $e = new HttpException(500);

        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testHttpExceptionDefaultsToEmptyMessage()
    {
        $e = new HttpException(404);

        $this->assertEquals('', $e->getMessage());
    }

    public function testHttpExceptionDefaultsToEmptyHeaders()
    {
        $e = new HttpException(404);

        $this->assertEquals([], $e->getHeaders());
    }

    public function testHttpExceptionChainsPreviousException()
    {
        $previous = new \Exception('original');
        $e        = new HttpException(500, 'Wrapped', $previous);

        $this->assertSame($previous, $e->getPrevious());
    }

    public function testNotFoundHttpExceptionReturns404()
    {
        $e = new NotFoundHttpException();

        $this->assertEquals(404, $e->getStatusCode());
    }

    public function testNotFoundHttpExceptionStoresMessage()
    {
        $e = new NotFoundHttpException('Page not found');

        $this->assertEquals('Page not found', $e->getMessage());
    }

    public function testBadRequestHttpExceptionReturns400()
    {
        $e = new BadRequestHttpException();

        $this->assertEquals(400, $e->getStatusCode());
    }

    public function testAccessDeniedHttpExceptionReturns403()
    {
        $e = new AccessDeniedHttpException();

        $this->assertEquals(403, $e->getStatusCode());
    }

    public function testConflictHttpExceptionReturns409()
    {
        $e = new ConflictHttpException();

        $this->assertEquals(409, $e->getStatusCode());
    }

    public function testLockedHttpExceptionReturns423()
    {
        $e = new LockedHttpException();

        $this->assertEquals(423, $e->getStatusCode());
    }

    public function testNotAcceptableHttpExceptionReturns406()
    {
        $e = new NotAcceptableHttpException();

        $this->assertEquals(406, $e->getStatusCode());
    }

    public function testUnprocessableEntityHttpExceptionReturns422()
    {
        $e = new UnprocessableEntityHttpException();

        $this->assertEquals(422, $e->getStatusCode());
    }

    public function testUnsupportedMediaTypeHttpExceptionReturns415()
    {
        $e = new UnsupportedMediaTypeHttpException();

        $this->assertEquals(415, $e->getStatusCode());
    }

    public function testTooManyRequestsHttpExceptionReturns429()
    {
        $e = new TooManyRequestsHttpException();

        $this->assertEquals(429, $e->getStatusCode());
    }

    public function testTooManyRequestsHttpExceptionWithRetryAfter()
    {
        $e = new TooManyRequestsHttpException(60, 'Too many requests');

        $this->assertEquals(429, $e->getStatusCode());
        $this->assertEquals('Too many requests', $e->getMessage());
        $this->assertEquals(['Retry-After' => 60], $e->getHeaders());
    }

    public function testServiceUnavailableHttpExceptionReturns503()
    {
        $e = new ServiceUnavailableHttpException();

        $this->assertEquals(503, $e->getStatusCode());
    }

    public function testServiceUnavailableHttpExceptionWithRetryAfter()
    {
        $e = new ServiceUnavailableHttpException(300, 'Down for maintenance');

        $this->assertEquals(503, $e->getStatusCode());
        $this->assertEquals('Down for maintenance', $e->getMessage());
        $this->assertEquals(['Retry-After' => 300], $e->getHeaders());
    }

    public function testInternalServerErrorHttpExceptionReturns403()
    {
        $e = new InternalServerErrorHttpException();

        $this->assertEquals(403, $e->getStatusCode());
    }

    public function testFromStatusCode400ReturnsBadRequest()
    {
        $e = HttpException::fromStatusCode(400, 'bad input');

        $this->assertInstanceOf(BadRequestHttpException::class, $e);
        $this->assertEquals(400, $e->getStatusCode());
        $this->assertEquals('bad input', $e->getMessage());
    }

    public function testFromStatusCode403ReturnsAccessDenied()
    {
        $e = HttpException::fromStatusCode(403);

        $this->assertInstanceOf(AccessDeniedHttpException::class, $e);
    }

    public function testFromStatusCode404ReturnsNotFound()
    {
        $e = HttpException::fromStatusCode(404);

        $this->assertInstanceOf(NotFoundHttpException::class, $e);
    }

    public function testFromStatusCode409ReturnsConflict()
    {
        $e = HttpException::fromStatusCode(409);

        $this->assertInstanceOf(ConflictHttpException::class, $e);
    }

    public function testFromStatusCode422ReturnsUnprocessableEntity()
    {
        $e = HttpException::fromStatusCode(422);

        $this->assertInstanceOf(UnprocessableEntityHttpException::class, $e);
    }

    public function testFromStatusCode429ReturnsTooManyRequests()
    {
        $e = HttpException::fromStatusCode(429);

        $this->assertInstanceOf(TooManyRequestsHttpException::class, $e);
    }

    public function testFromStatusCode500ReturnsInternalServerError()
    {
        $e = HttpException::fromStatusCode(500);

        $this->assertInstanceOf(InternalServerErrorHttpException::class, $e);
    }

    public function testFromStatusCode503ReturnsServiceUnavailable()
    {
        $e = HttpException::fromStatusCode(503);

        $this->assertInstanceOf(ServiceUnavailableHttpException::class, $e);
    }

    public function testFromStatusCodeUnknownReturnsBaseHttpException()
    {
        $e = HttpException::fromStatusCode(599, 'Unknown');

        $this->assertInstanceOf(HttpException::class, $e);
        $this->assertEquals(599, $e->getStatusCode());
    }

    public function testFromStatusCodePropagatesPreviousException()
    {
        $previous = new \Exception('root cause');
        $e        = HttpException::fromStatusCode(404, 'not found', $previous);

        $this->assertSame($previous, $e->getPrevious());
    }

    public function testFromStatusCodePropagatesHeaders()
    {
        $headers = ['X-Reason' => 'test'];
        $e       = HttpException::fromStatusCode(400, '', null, $headers);

        $this->assertEquals($headers, $e->getHeaders());
    }

    public function testTokenMismatchExceptionHasStatusCode()
    {
        $e = new TokenMismatchException(419, 'CSRF token mismatch');

        $this->assertInstanceOf(\Throwable::class, $e);
        $this->assertEquals(419, $e->getStatusCode());
        $this->assertEquals('CSRF token mismatch', $e->getMessage());
    }

    public function testRouteNameNotFoundExceptionHasStatusCode()
    {
        $e = new RouteNameNotFoundException(404, 'Route [home] not found');

        $this->assertInstanceOf(\Throwable::class, $e);
        $this->assertEquals(404, $e->getStatusCode());
        $this->assertEquals('Route [home] not found', $e->getMessage());
    }
}
