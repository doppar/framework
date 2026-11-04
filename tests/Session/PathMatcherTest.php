<?php

namespace Tests\Unit\Session;

use Phaseolies\DI\Container;
use Phaseolies\Http\InputBag;
use Phaseolies\Http\Request;
use Phaseolies\Http\ServerBag;
use Phaseolies\Session\Stores\ArrayStore;
use Phaseolies\Support\Session;
use Phaseolies\Utilities\PathMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The stateless paths of the session, the paths that skip CSRF verification and
 * Request::is() all go through one matcher, so one pattern means one thing.
 */
class PathMatcherTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'wildcard' => ['/api/*', '/api/users', true],
            'no leading slash on the pattern' => ['api/*', '/api/users', true],
            'no leading slash on the path' => ['/api/*', 'api/users', true],
            'deeper path' => ['api/*', '/api/v1/users/1', true],
            'wildcard needs the separator' => ['api/*', '/apix', false],
            'the base itself is not under the wildcard' => ['/api/*', '/api', false],
            'exact' => ['/webhook/payment', '/webhook/payment', true],
            'a trailing slash on the path is significant' => ['/webhook/payment', '/webhook/payment/', false],
            'a trailing slash on the pattern is significant' => ['/webhook/', '/webhook', false],
            'the wildcard matches a bare trailing slash' => ['/api/*', '/api/', true],
            'the wildcard pattern without a leading slash matches it too' => ['api/*', '/api/', true],
            'exact is not a prefix' => ['/webhook', '/webhook/payment', false],
            'case is ignored' => ['/API/*', '/api/Users', true],
            'wildcard in the middle' => ['/api/*/posts', '/api/users/123/posts', true],
            'wildcard in the middle, other tail' => ['/api/*/posts', '/api/users/123/comments', false],
            'root' => ['/', '/', true],
            'root does not match a page' => ['/', '/home', false],
            'everything' => ['*', '/any/thing', true],
            'a dot is a dot' => ['/a.b', '/axb', false],
            'a regex character is literal' => ['/a+b', '/aab', false],
            'a trailing newline does not slip through' => ['/health', "/health\n", false],
        ];
    }

    #[DataProvider('cases')]
    public function test_the_matcher(string $pattern, string $path, bool $expected): void
    {
        $this->assertSame($expected, PathMatcher::matches($pattern, $path));
    }

    #[DataProvider('cases')]
    public function test_the_session_treats_a_stateless_path_the_same_way(string $pattern, string $path, bool $expected): void
    {
        $request = new class extends Request {
            public string $fakePath = '/';

            public function __construct()
            {
            }

            public function getPath(): string
            {
                return $this->fakePath;
            }
        };
        $request->fakePath = $path;
        $request->cookies = new InputBag([]);
        $request->server = new ServerBag([]);

        $session = new Session(new ArrayStore(), ['cookie' => 'test_session', 'stateless' => [$pattern]]);
        $session->attach($request);

        $this->assertSame($expected, $session->isStateless());
    }

    #[DataProvider('cases')]
    public function test_request_is_treats_the_same_pattern_the_same_way(string $pattern, string $path, bool $expected): void
    {
        $container = new Container();
        Container::setInstance($container);

        try {
            $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/' . ltrim($path, '/'), 'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_HOST' => 'localhost'];
            $request = Request::createFromGlobals();
            $container->singleton('request', fn () => $request);

            // A newline cannot appear in a real request line, so that case is the matcher's alone.
            if (!str_contains($path, "\n")) {
                $this->assertSame($expected, Request::is($pattern));
            }
        } finally {
            Container::forgetInstance();
            $_SERVER = [];
        }
    }
}
