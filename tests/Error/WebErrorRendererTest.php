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

        $response = $this->renderWithPageBuffer($renderer, $exception);

        // Rendered with the framework's bundled 500 view
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getBody());
        $this->assertStringContainsString('Something went wrong', $response->getBody());
        $this->assertStringNotContainsString('Boom', $response->getBody());
        $this->assertSame($exception, $response->getOriginal());
    }

    public function testRenderProductionPreservesHttpExceptionStatus(): void
    {
        $renderer = new WebErrorRenderer();
        $exception = HttpException::fromStatusCode(404, 'Not Found');

        $response = $this->renderWithPageBuffer($renderer, $exception);

        // Rendered with the framework's bundled 404 view
        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getBody());
        $this->assertStringContainsString('Not Found', $response->getBody());
        $this->assertSame($exception, $response->getOriginal());
    }

    public function testRenderProductionFallsBackToPlainMessageWithoutAView(): void
    {
        $renderer = new WebErrorRenderer();
        $exception = HttpException::fromStatusCode(418, "I'm a teapot");

        $response = $renderer->renderProduction($exception);

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame("I'm a teapot", $response->getBody());
    }

    /**
     * Render inside an extra output buffer, as a real request has one open:
     * rendering an error view discards the page's partial output first.
     */
    private function renderWithPageBuffer(WebErrorRenderer $renderer, \Throwable $exception): Response
    {
        $level = ob_get_level();
        ob_start();

        try {
            return $renderer->renderProduction($exception);
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
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
