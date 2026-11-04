<?php

namespace Tests\Unit\Error;

require_once __DIR__ . '/../Support/MockContainer.php';

use Phaseolies\DI\Container;
use Phaseolies\Error\WebErrorRenderer;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Http\Exceptions\TokenMismatchException;
use Phaseolies\Http\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\MockContainer;

class WebErrorRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::setInstance(new MockContainer());
    }

    public function testRenderProductionReturnsResponseForGenericException(): void
    {
        $renderer = new WebErrorRenderer();
        $exception = new RuntimeException('Boom');

        $response = $renderer->renderProduction($exception);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Something went wrong', $response->getBody());
        $this->assertSame($exception, $response->getOriginal());
    }

    public function testRenderProductionPreservesHttpExceptionStatus(): void
    {
        $renderer = new WebErrorRenderer();
        $exception = HttpException::fromStatusCode(404, 'Not Found');

        $response = $renderer->renderProduction($exception);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', $response->getBody());
        $this->assertSame($exception, $response->getOriginal());
    }

    public function testRenderProductionKeepsTheStatusOfATokenMismatch(): void
    {
        $renderer = new WebErrorRenderer();
        $exception = new TokenMismatchException(419, 'CSRF Token mismatched');

        $response = $renderer->renderProduction($exception);

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame('CSRF Token mismatched', $response->getBody());
    }

    public function testStatus419HasAReadableReasonPhrase(): void
    {
        $this->assertSame('Page Expired', (new Response())->getStatusCodeText(419));
    }
}
