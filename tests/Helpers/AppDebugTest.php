<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AppDebugTest extends TestCase
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

        $this->assertSame($expected, app_debug());
    }
}
