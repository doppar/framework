<?php

namespace Tests\Unit\Router;

use PHPUnit\Framework\TestCase;
use Phaseolies\DI\Container;
use Phaseolies\Http\Request;
use Phaseolies\Support\Router;
use Phaseolies\Support\Router\Attributes\Route as RouteAttribute;
use Tests\Support\Gateway;
use Tests\Support\MockContainer;

class ConstraintRequestStub extends Request
{
    private array $stubRouteParams = [];

    public function __construct(private string $stubMethod, private string $stubPath)
    {
    }

    public function getMethod(): string
    {
        return $this->stubMethod;
    }

    public function getPath(): string
    {
        return $this->stubPath;
    }

    public function getHost(): string
    {
        return 'localhost';
    }

    public function getRouteParams(): array
    {
        return $this->stubRouteParams;
    }

    public function setRouteParams(array $params): self
    {
        $this->stubRouteParams = $params;

        return $this;
    }
}

class ConstraintTestController
{
    public function show()
    {
    }
}

class RouteConstraintsTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());
        $this->router = new Router(new Gateway());
        $this->resetStatics();
    }

    protected function tearDown(): void
    {
        $this->resetStatics();
    }

    private function resetStatics(): void
    {
        $reflection = new \ReflectionClass(Router::class);

        $reflection->getProperty('routes')->setValue(null, []);
        $reflection->getProperty('namedRoutes')->setValue(null, []);
        $reflection->getProperty('patterns')->setValue(null, []);
        $reflection->getProperty('routeMiddlewares')->setValue(null, [
            'GET' => [], 'POST' => [], 'PUT' => [], 'PATCH' => [],
            'DELETE' => [], 'OPTIONS' => [], 'HEAD' => [], 'ANY' => [],
        ]);
    }

    private function routes(): array
    {
        return (new \ReflectionProperty(Router::class, 'routes'))->getValue();
    }

    private function middlewares(): array
    {
        return (new \ReflectionProperty(Router::class, 'routeMiddlewares'))->getValue();
    }

    /**
     * Resolve a GET path to the label the matching route returns, plus the route params
     */
    private function dispatch(string $path, string $method = 'GET'): array
    {
        $request = new ConstraintRequestStub($method, $path);
        $callback = $this->router->getCallback($request);

        return [$callback === false ? null : $callback(), $request->getRouteParams()];
    }

    public function testInlineConstraintOnlyMatchesTheDeclaredShape(): void
    {
        $this->router->get('posts/{id:[0-9]+}', fn() => 'post');

        $this->assertSame(['post', ['id' => '42']], $this->dispatch('/posts/42'));
        $this->assertSame([null, []], $this->dispatch('/posts/abc'));
        $this->assertSame([null, []], $this->dispatch('/posts/42abc'));
    }

    public function testWhereConstrainsTheLastRoute(): void
    {
        $this->router->get('posts/{id}', fn() => 'post')->where('id', '[0-9]+');

        $this->assertSame('post', $this->dispatch('/posts/7')[0]);
        $this->assertNull($this->dispatch('/posts/seven')[0]);
        $this->assertArrayHasKey('/posts/{id:[0-9]+}', $this->routes()['GET']);
        $this->assertArrayNotHasKey('/posts/{id}', $this->routes()['GET']);
    }

    public function testWhereAcceptsAMapForSeveralParameters(): void
    {
        $this->router->get('posts/{id}/{slug}', fn() => 'post')->where(['id' => '[0-9]+', 'slug' => '[a-z-]+']);

        $this->assertSame(['post', ['id' => '3', 'slug' => 'hello-world']], $this->dispatch('/posts/3/hello-world'));
        $this->assertNull($this->dispatch('/posts/3/Hello_World')[0]);
        $this->assertNull($this->dispatch('/posts/x/hello')[0]);
    }

    public function testFailingTheConstraintFallsThroughToTheNextRoute(): void
    {
        $this->router->get('posts/{id}', fn() => 'by-id')->whereNumber('id');
        $this->router->get('posts/{slug}', fn() => 'by-slug');

        $this->assertSame(['by-id', ['id' => '12']], $this->dispatch('/posts/12'));
        $this->assertSame(['by-slug', ['slug' => 'hello']], $this->dispatch('/posts/hello'));
    }

    public function testWhereKeepsTheRouteNameAndMiddlewareInEitherOrder(): void
    {
        $this->router->get('a/{id}', fn() => 'a')->name('a.show')->middleware('auth')->where('id', '[0-9]+');
        $this->router->get('b/{id}', fn() => 'b')->where('id', '[0-9]+')->name('b.show')->middleware('auth');

        $this->assertSame('/a/5', $this->router->route('a.show', ['id' => 5]));
        $this->assertSame('/b/9', $this->router->route('b.show', 9));
        $this->assertSame(['auth'], $this->middlewares()['GET']['/a/{id:[0-9]+}']);
        $this->assertSame(['auth'], $this->middlewares()['GET']['/b/{id:[0-9]+}']);
        $this->assertArrayNotHasKey('/a/{id}', $this->middlewares()['GET']);
    }

    public function testWhereKeepsTheMatchingOrder(): void
    {
        $this->router->get('first/{id}', fn() => 'first');
        $this->router->get('second/{id}', fn() => 'second')->whereNumber('id');
        $this->router->get('third/{id}', fn() => 'third');

        $this->assertSame(
            ['/first/{id}', '/second/{id:[0-9]+}', '/third/{id}'],
            array_keys($this->routes()['GET'])
        );
    }

    public function testCurrentRouteMiddlewareFollowsTheConstraint(): void
    {
        $this->router->get('items/{id}', fn() => 'by-id')->whereNumber('id')->middleware('numeric');
        $this->router->get('items/{slug}', fn() => 'by-slug')->middleware('named');

        $this->assertSame(['numeric'], $this->router->getCurrentRouteMiddleware(new ConstraintRequestStub('GET', '/items/12')));
        $this->assertSame(['named'], $this->router->getCurrentRouteMiddleware(new ConstraintRequestStub('GET', '/items/hello')));
    }

    public function testShortcuts(): void
    {
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        $this->router->get('n/{v}', fn() => 'n')->whereNumber('v');
        $this->router->get('a/{v}', fn() => 'a')->whereAlpha('v');
        $this->router->get('an/{v}', fn() => 'an')->whereAlphaNumeric('v');
        $this->router->get('u/{v}', fn() => 'u')->whereUuid('v');
        $this->router->get('l/{v}', fn() => 'l')->whereUlid('v');
        $this->router->get('i/{v}', fn() => 'i')->whereIn('v', ['draft', 'published']);

        foreach (
            [
                ['/n/10', 'n'], ['/n/x', null],
                ['/a/abc', 'a'], ['/a/ab1', null],
                ['/an/ab1', 'an'], ['/an/a-b', null],
                ["/u/$uuid", 'u'], ['/u/not-a-uuid', null],
                ["/l/$ulid", 'l'], ['/l/short', null],
                ['/i/draft', 'i'], ['/i/published', 'i'], ['/i/archived', null], ['/i/draf', null],
            ] as [$path, $expected]
        ) {
            $this->assertSame($expected, $this->dispatch($path)[0], $path);
        }
    }

    public function testShortcutsAcceptSeveralParameters(): void
    {
        $this->router->get('x/{a}/{b}', fn() => 'x')->whereNumber(['a', 'b']);

        $this->assertSame('x', $this->dispatch('/x/1/2')[0]);
        $this->assertNull($this->dispatch('/x/1/b')[0]);
    }

    public function testBracesAreAllowedAsQuantifiers(): void
    {
        $this->router->get('year/{y:[0-9]{4}}', fn() => 'inline');
        $this->router->get('month/{m}', fn() => 'where')->where('m', '[0-9]{1,2}');

        $this->assertSame('inline', $this->dispatch('/year/2025')[0]);
        $this->assertNull($this->dispatch('/year/25')[0]);
        $this->assertSame('where', $this->dispatch('/month/7')[0]);
        $this->assertNull($this->dispatch('/month/123')[0]);
    }

    public function testConstraintsMayContainSlashesAlternationAtSignsAndStars(): void
    {
        $this->router->get('files/{path}', fn() => 'files')->where('path', '.+\.(png|jpg)');
        $this->router->get('mail/{who}', fn() => 'mail')->where('who', '[a-z]+@[a-z]+');
        $this->router->get('stars/{v}', fn() => 'stars')->where('v', 'a*b');

        $this->assertSame('files', $this->dispatch('/files/photo.png')[0]);
        $this->assertNull($this->dispatch('/files/photo.gif')[0]);
        $this->assertSame('mail', $this->dispatch('/mail/me@host')[0]);
        $this->assertNull($this->dispatch('/mail/nobody')[0]);
        $this->assertSame('stars', $this->dispatch('/stars/aaab')[0]);
        $this->assertNull($this->dispatch('/stars/aaac')[0]);
    }

    public function testUnconstrainedAndWildcardRoutesBehaveAsBefore(): void
    {
        $this->router->get('plain/{id}', fn() => 'plain');
        $this->router->get('docs/*', fn() => 'docs');

        $this->assertSame(['plain', ['id' => 'anything']], $this->dispatch('/plain/anything'));
        $this->assertSame('docs', $this->dispatch('/docs/a/b')[0]);
    }

    public function testRegexIsUnchangedForUnconstrainedParameters(): void
    {
        $method = new \ReflectionMethod(Router::class, 'convertRouteToRegex');

        $this->assertSame('@^\/users\/(?P<id>[^\/]+)$@D', $method->invoke($this->router, '/users/{id}'));
        $this->assertSame('@^\/users\/(?P<id>(?:[0-9]+))$@D', $method->invoke($this->router, '/users/{id:[0-9]+}'));
    }

    public function testWhereRejectsUnknownParameters(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no {nope} parameter');

        $this->router->get('posts/{id}', fn() => 'x')->where('nope', '[0-9]+');
    }

    public function testInvalidRegularExpressionsFailAtDefinitionTime(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('not a valid regular expression');

        $this->router->get('posts/{id}', fn() => 'x')->where('id', '[0-9');
    }

    public function testInvalidInlineConstraintsFailAtDefinitionTime(): void
    {
        $this->expectException(\LogicException::class);

        $this->router->get('posts/{id:(unclosed}', fn() => 'x');
    }

    public function testNestedBracesAreRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('braces are only allowed as quantifiers');

        $this->router->get('posts/{id}', fn() => 'x')->where('id', '(?:a{1,{2}})');
    }

    public function testEmptyConstraintIsRejected(): void
    {
        $this->expectException(\LogicException::class);

        $this->router->get('posts/{id}', fn() => 'x')->where('id', '');
    }

    public function testWhereInNeedsValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->router->get('posts/{status}', fn() => 'x')->whereIn('status', []);
    }

    public function testGlobalPatternAppliesToRoutesDefinedAfterwards(): void
    {
        $this->router->get('before/{id}', fn() => 'before');

        $this->router->pattern('id', '[0-9]+');

        $this->router->get('after/{id}', fn() => 'after');
        $this->router->get('override/{id:[a-z]+}', fn() => 'override');

        $this->assertSame('before', $this->dispatch('/before/abc')[0]);
        $this->assertSame('after', $this->dispatch('/after/12')[0]);
        $this->assertNull($this->dispatch('/after/abc')[0]);
        $this->assertSame('override', $this->dispatch('/override/abc')[0], 'a constraint on the route wins');
        $this->assertNull($this->dispatch('/override/12')[0]);
    }

    public function testWhereWorksWithDomainInEitherOrderAndInsideGroups(): void
    {
        $this->router->get('a/{id}', fn() => 'a')->domain('localhost')->whereNumber('id');
        $this->router->get('b/{id}', fn() => 'b')->whereNumber('id')->domain('localhost');
        $this->router->get('c/{id}', fn() => 'c')->whereNumber('id')->domain('other.test');

        $this->router->group(['prefix' => 'admin'], function () {
            $this->router->get('users/{id}', fn() => 'users')->whereNumber('id');
        });

        $this->assertSame('a', $this->dispatch('/a/1')[0]);
        $this->assertNull($this->dispatch('/a/x')[0]);
        $this->assertSame('b', $this->dispatch('/b/1')[0]);
        $this->assertNull($this->dispatch('/b/x')[0]);
        $this->assertNull($this->dispatch('/c/1')[0], 'the domain restriction is kept');
        $this->assertSame('users', $this->dispatch('/admin/users/5')[0]);
        $this->assertNull($this->dispatch('/admin/users/x')[0]);
    }

    public function testGlobalPatternMustBeValid(): void
    {
        $this->expectException(\LogicException::class);

        $this->router->pattern('id', '(');
    }

    public function testRouteAttributeAcceptsConstraints(): void
    {
        $register = new \ReflectionMethod(Router::class, 'registerAttributeRoute');

        $register->invoke($this->router, ConstraintTestController::class, 'show', new RouteAttribute(
            uri: 'posts/{id}',
            methods: ['GET', 'DELETE'],
            name: 'posts.show',
            middleware: ['auth'],
            where: ['id' => '[0-9]+']
        ));

        $this->assertArrayHasKey('/posts/{id:[0-9]+}', $this->routes()['GET']);
        $this->assertArrayHasKey('/posts/{id:[0-9]+}', $this->routes()['DELETE']);
        $this->assertSame('/posts/3', $this->router->route('posts.show', 3));
        $this->assertSame(['auth'], $this->middlewares()['GET']['/posts/{id:[0-9]+}']);
        $this->assertSame(['auth'], $this->middlewares()['DELETE']['/posts/{id:[0-9]+}']);
    }

    public function testGlobalPatternAlsoAppliesToAttributeRoutes(): void
    {
        $this->router->pattern('id', '[0-9]+');

        $register = new \ReflectionMethod(Router::class, 'registerAttributeRoute');
        $register->invoke($this->router, ConstraintTestController::class, 'show', new RouteAttribute(uri: 'items/{id}'));
        $register->invoke($this->router, ConstraintTestController::class, 'show', new RouteAttribute(uri: 'tags/{id:[a-z]+}'));

        $this->assertArrayHasKey('/items/{id:[0-9]+}', $this->routes()['GET']);
        $this->assertArrayHasKey('/tags/{id:[a-z]+}', $this->routes()['GET'], 'a constraint on the route wins');
    }

    public function testConstraintedRouteKeysSurviveTheRouteCache(): void
    {
        $this->router->get('posts/{id}', [ConstraintTestController::class, 'show'])->where('id', '[0-9]{1,3}|new');

        $cached = eval('return ' . var_export($this->routes(), true) . ';');

        $this->assertSame(array_keys($this->routes()['GET']), array_keys($cached['GET']));
        $this->assertArrayHasKey('/posts/{id:[0-9]{1,3}|new}', $cached['GET']);
    }
}
