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
            'query string is kept' => ['/users/?next=/', '/users?next=/'],
            'encoded query is not decoded' => ['/users/?next=%2Fhome', '/users?next=%2Fhome'],
            'encoded path is not decoded' => ['/caf%C3%A9/', '/caf%C3%A9'],
            'protocol-relative host' => ['//evil.com/foo/', '/evil.com/foo'],
            'three leading slashes' => ['///evil.com/foo/', '/evil.com/foo'],
            'leading backslash' => ['/\\evil.com/foo/', '/evil.com/foo'],
            'mixed leading slashes' => ['/\\/\\evil.com/foo/', '/evil.com/foo'],
            'absolute URI' => ['http://evil.com/foo/', '/http://evil.com/foo'],
            'no trailing slash' => ['/users', null],
            'slash only in query' => ['/users?next=/', null],
            'encoded slash at the end' => ['/users%2F', null],
            'root' => ['/', null],
            'root with query' => ['/?next=/', null],
            'only slashes' => ['//', null],
            'tab before a host' => ["/\t/evil.com/", null],
            'newline before a host' => ["/\n/evil.com/", null],
            'carriage return before a host' => ["/\r/evil.com/", null],
            'tab between leading slashes' => ["/\t\t//evil.com/foo/", null],
            'control character in the query' => ["/users/?next=\t/", null],
            'delete character' => ["/\x7F/evil.com/", null],
            'encoded tab is not a control character' => ['/%09/evil.com/', '/%09/evil.com'],
        ];
    }

    #[DataProvider('uriProvider')]
    public function testRedirectTargetStaysOnThisSite(string $uri, ?string $expected): void
    {
        $launcher = (new ReflectionClass(RouteLauncher::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(RouteLauncher::class, 'trailingSlashRedirect');

        $this->assertSame($expected, $method->invoke($launcher, $uri));
    }
}
