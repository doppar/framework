<?php

namespace Tests\Unit\Error;

require_once __DIR__ . '/../Support/MockContainer.php';

use Phaseolies\DI\Container;
use Phaseolies\Error\Utils\PathResolver;
use PHPUnit\Framework\TestCase;
use Tests\Support\MockContainer;

class CrossPlatformErrorPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new MockContainer();
        $basePath = (new \ReflectionClass($container))->getProperty('basePath');
        $basePath->setValue($container, dirname(__DIR__, 2));

        Container::setInstance($container);
    }

    public function testErrorFilePathIsDisplayedRelativeToTheApplicationRoot(): void
    {
        $errorFile = base_path(
            'storage'
            . DIRECTORY_SEPARATOR . 'framework'
            . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . 'error.php'
        );

        $displayPath = PathResolver::toDisplayPath($errorFile);

        $this->assertSame('storage/framework/views/error.php', $displayPath);
    }
}
