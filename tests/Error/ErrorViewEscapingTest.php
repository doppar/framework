<?php

namespace Tests\Unit\Error;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ErrorViewEscapingTest extends TestCase
{
    public static function statusProvider(): array
    {
        return [[400], [401], [403], [404], [429], [500]];
    }

    #[DataProvider('statusProvider')]
    public function testBundledErrorViewEscapesTheMessage(int $status): void
    {
        $message = 'Route [/<script>alert(1)</script>] not found';

        ob_start();
        include dirname(__DIR__, 2) . "/src/Phaseolies/Support/View/errors/{$status}.odo.php";
        $html = ob_get_clean();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }
}
