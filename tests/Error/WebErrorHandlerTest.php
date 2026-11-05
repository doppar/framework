<?php

namespace Tests\Unit\Error;

use Phaseolies\Error\Handlers\WebErrorHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class WebErrorHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['APP_DEBUG']);

        parent::tearDown();
    }

    public static function debugValueProvider(): array
    {
        return [
            'boolean false' => [false, false],
            'string false' => ['false', false],
            'string zero' => ['0', false],
            'empty string' => ['', false],
            'boolean true' => [true, true],
            'string true' => ['true', true],
            'string one' => ['1', true],
        ];
    }

    #[DataProvider('debugValueProvider')]
    public function testDebugModeIsReadFromAppDebug(bool|string $value, bool $expected): void
    {
        $_ENV['APP_DEBUG'] = $value;

        $method = new ReflectionMethod(WebErrorHandler::class, 'isDebugging');

        $this->assertSame($expected, $method->invoke(new WebErrorHandler()));
    }
}
