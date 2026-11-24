<?php

namespace Tests\Unit\Http;

use Phaseolies\DI\Container;
use Phaseolies\Http\Response;
use PHPUnit\Framework\TestCase;
use Tests\Support\MockContainer;

class ErrorViewRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new MockContainer();
        Container::setInstance($container);
        $container->bind('view', \Phaseolies\Support\View\Factory::class);
    }

    protected function tearDown(): void
    {
        Container::forgetInstance();
        parent::tearDown();
    }

    public function testBuiltInErrorPageIsIncludedWithItsMessage(): void
    {
        $path = dirname(__DIR__, 2) . '/src/Phaseolies/Support/View/errors/404.odo.php';

        $html = Response::renderErrorView($path, 404, 'Match not found');

        $this->assertStringContainsString('Match not found', $html);
        $this->assertStringContainsString('<html', $html);
    }

    public function testBrokenApplicationErrorPageFallsBackToTheBuiltInPage(): void
    {
        // An application template that cannot be rendered (here: it does not
        // exist) must not produce raw template code or a blank page.
        $path = base_path('templates/views/errors/404.odo.php');

        $html = Response::renderErrorView($path, 404, 'Gone');

        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('Gone', $html);
        $this->assertStringNotContainsString('#extends', $html);
    }
}
