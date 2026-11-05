<?php

namespace Tests\Unit\Launchers;

use Phaseolies\Launchers\RouteLauncher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class RouteLauncherTest extends TestCase
{
    public static function uriProvider(): array
    {
        return [
            'plain path' => ['/users/', '/users'],
            'nested path' => ['/users/7/posts/', '/users/7/posts'],
            'several trailing slashes' => ['/users///', '/users'],
            'query string is kept' => ['/users?next=/', '/users?next='],
            'protocol-relative host' => ['//evil.com/foo/', '/evil.com/foo'],
            'three leading slashes' => ['///evil.com/foo/', '/evil.com/foo'],
            'leading backslash' => ['/\\evil.com/foo/', '/evil.com/foo'],
            'mixed leading slashes' => ['/\\/\\evil.com/foo/', '/evil.com/foo'],
            'absolute URI' => ['http://evil.com/foo/', '/http://evil.com/foo'],
            'only slashes' => ['//', '/'],
        ];
    }

    #[DataProvider('uriProvider')]
    public function testRedirectTargetStaysOnThisSite(string $uri, string $expected): void
    {
        $launcher = (new ReflectionClass(RouteLauncher::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(RouteLauncher::class, 'withoutTrailingSlash');

        $this->assertSame($expected, $method->invoke($launcher, $uri));
    }
}
