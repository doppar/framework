<?php

namespace Tests\Unit\Router;

require_once __DIR__ . '/../Support/MockContainer.php';
require_once __DIR__ . '/../Support/Gateway.php';

use Phaseolies\DI\Container;
use Phaseolies\Http\Request;
use Phaseolies\Support\Router;
use Phaseolies\Support\Router\Attributes\Route as RouteAttribute;
use Phaseolies\Support\StringService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Gateway;
use Tests\Support\MockContainer;

class CachedAppRouter extends Router
{
    public ?\Closure $routeFile = null;

    protected function loadRoutesFromFiles(): void
    {
        if ($this->routeFile) {
            ($this->routeFile)($this);
        }
    }

    public function reloadOriginalClosure(Request $request): mixed
    {
        return $this->getOriginalClosure($request);
    }
}

/**
 * Redeclaring the statics gives this router a route cache of its own.
 */
final class RouteCacheRouter extends Router
{
    protected static string $cachePath;

    protected static bool $cacheLoaded = false;

    public static function pointTo(string $path): void
    {
        static::$cachePath = $path;
        static::$cacheLoaded = false;
    }
}

class NamedRouteController
{
    public function handle(): string
    {
        return 'handled';
    }
}

class CurrentRouteTest extends TestCase
{
    private MockContainer $container;
    private CachedAppRouter $router;

    protected function setUp(): void
    {
        $this->container = new MockContainer();
        Container::setInstance($this->container);
        $this->container->bind('str', StringService::class);
        $this->resetRoutes();
        $this->router = new CachedAppRouter(new Gateway());
    }

    protected function tearDown(): void
    {
        $this->resetRoutes();
        Container::forgetInstance();
    }

    private function resetRoutes(): void
    {
        Router::$namedRoutes = [];
        Router::$namedRouteMethods = [];

        (new \ReflectionProperty(Router::class, 'routes'))->setValue(null, []);
        (new \ReflectionProperty(Router::class, 'routeMiddlewares'))->setValue(null, array_fill_keys(
            ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'ANY'],
            []
        ));
    }

    private function request(string $method, string $uri): Request
    {
        $_GET = $_POST = $_COOKIE = $_FILES = [];
        $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'example.com', 'REMOTE_ADDR' => '127.0.0.1', 'SCRIPT_NAME' => '/index.php', 'SERVER_NAME' => 'example.com', 'SERVER_PORT' => '80'];

        $request = Request::createFromGlobals();
        $this->container->bind('request', fn() => $request);

        return $request;
    }

    public function testRouteNameFollowsTheDispatchedMethod(): void
    {
        $this->router->get('/posts', fn() => 'index')->name('posts.index');
        $this->router->post('/posts', fn() => 'store')->name('posts.store');

        $this->router->getCallback($this->request('POST', '/posts'));
        $this->assertSame('posts.store', $this->router->currentRouteName());

        $this->router->getCallback($this->request('GET', '/posts'));
        $this->assertSame('posts.index', $this->router->currentRouteName());
    }

    public function testRouteNameIsNullForAnUnnamedRoute(): void
    {
        $this->router->get('/posts', fn() => 'index');

        $this->router->getCallback($this->request('GET', '/posts'));

        $this->assertNull($this->router->currentRouteName());
    }

    public function testRouteNameIsNullWhenOnlyAnotherMethodIsNamed(): void
    {
        $this->router->get('/posts', fn() => 'index')->name('posts.index');
        $this->router->post('/posts', fn() => 'store');

        $this->router->getCallback($this->request('POST', '/posts'));
        $this->assertNull($this->router->currentRouteName());

        $this->router->getCallback($this->request('GET', '/posts'));
        $this->assertSame('posts.index', $this->router->currentRouteName());
    }

    public function testHeadRequestUsesTheNameOfTheGetRouteServingIt(): void
    {
        $this->router->get('/posts', fn() => 'index')->name('posts.index');

        $this->router->getCallback($this->request('HEAD', '/posts'));

        $this->assertSame('posts.index', $this->router->currentRouteName());
    }

    public function testSameNameCoversEveryMethodItWasGivenTo(): void
    {
        $this->router->get('/posts', fn() => 'form')->name('posts.form');
        $this->router->post('/posts', fn() => 'save')->name('posts.form');

        foreach (['GET', 'POST'] as $method) {
            $this->router->getCallback($this->request($method, '/posts'));
            $this->assertSame('posts.form', $this->router->currentRouteName());
        }

        $this->assertSame(['GET' => '/posts', 'POST' => '/posts'], Router::$namedRouteMethods['posts.form']);
    }

    public function testNameMovedToAnotherPathLeavesThatPathsOtherRoutesAlone(): void
    {
        $this->router->get('/a', fn() => 'a')->name('shared');
        $this->router->post('/b', fn() => 'b')->name('shared');
        $this->router->get('/b', fn() => 'b')->name('other');
        $this->router->get('/c', fn() => 'c')->name('shared.c');
        $this->router->post('/c', fn() => 'c')->name('shared.c');
        $this->router->put('/c', fn() => 'c');

        $this->router->getCallback($this->request('GET', '/b'));
        $this->assertSame('other', $this->router->currentRouteName());

        $this->router->getCallback($this->request('POST', '/b'));
        $this->assertSame('shared', $this->router->currentRouteName());

        $this->router->getCallback($this->request('PUT', '/c'));
        $this->assertNull($this->router->currentRouteName());
        $this->assertSame('/b', Router::$namedRoutes['shared']);
    }

    public function testConstraintOnOneMethodKeepsASharedNameForTheOther(): void
    {
        $this->router->get('/posts/{id}', fn() => 'show')->name('posts.item');
        $this->router->post('/posts/{id}', fn() => 'update')->name('posts.item')->where(['id' => '\\d+']);

        foreach (['GET', 'POST'] as $method) {
            $this->router->getCallback($this->request($method, '/posts/5'));
            $this->assertSame('posts.item', $this->router->currentRouteName());
        }

        $this->assertSame(
            ['GET' => '/posts/{id}', 'POST' => '/posts/{id:\\d+}'],
            Router::$namedRouteMethods['posts.item']
        );
    }

    public function testNameBeforeAConstraintOnEachMethodKeepsTheSharedName(): void
    {
        $this->router->get('/posts/{id}', fn() => 'show')->name('posts.item')->where(['id' => '\\d+']);
        $this->router->post('/posts/{id}', fn() => 'update')->name('posts.item')->where(['id' => '\\d+']);

        foreach (['GET', 'POST'] as $method) {
            $this->router->getCallback($this->request($method, '/posts/5'));
            $this->assertSame('posts.item', $this->router->currentRouteName());
        }
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function attributeRouteProvider(): array
    {
        return [
            'no constraint' => [[], '/items/{id}'],
            'with constraint' => [['id' => '\d+'], '/items/{id:\d+}'],
        ];
    }

    #[DataProvider('attributeRouteProvider')]
    public function testAttributeRouteKeepsItsNameForEveryMethod(array $where, string $path): void
    {
        $route = new RouteAttribute(uri: '/items/{id}', methods: ['GET', 'POST'], name: 'items.save', where: $where);

        (new \ReflectionMethod(Router::class, 'registerAttributeRoute'))
            ->invoke($this->router, NamedRouteController::class, 'handle', $route);

        foreach (['GET', 'POST'] as $method) {
            $this->router->getCallback($this->request($method, '/items/5'));
            $this->assertSame('items.save', $this->router->currentRouteName());
        }

        $this->assertSame($path, Router::$namedRoutes['items.save']);
    }

    public function testConstraintLeavesAnotherMethodsRouteNameAlone(): void
    {
        $this->router->get('/posts/{id}', fn() => 'show')->name('posts.show');
        $this->router->post('/posts/{id}', fn() => 'update')->where(['id' => '\d+']);

        $this->router->getCallback($this->request('GET', '/posts/5'));

        $this->assertSame('posts.show', $this->router->currentRouteName());
        $this->assertSame('/posts/{id}', Router::$namedRoutes['posts.show']);
    }

    public function testConstraintMovesTheNameOfItsOwnRoute(): void
    {
        $this->router->get('/posts/{id}', fn() => 'show')->name('posts.show')->where(['id' => '\d+']);

        $this->router->getCallback($this->request('GET', '/posts/5'));

        $this->assertSame('posts.show', $this->router->currentRouteName());
        $this->assertSame('/posts/{id:\d+}', Router::$namedRoutes['posts.show']);
    }

    public function testRouteCacheKeepsEveryMethodOfAName(): void
    {
        $this->withRouteCache(function (string $cache): void {
            $router = new RouteCacheRouter(new Gateway());
            $router->get('/items', [NamedRouteController::class, 'handle'])->name('items.save');
            $router->post('/items', [NamedRouteController::class, 'handle'])->name('items.save');
            $router->cacheRoutes();

            $this->resetRoutes();
            $this->assertTrue($router->loadCachedRoutes());
            $this->assertSame(['GET' => '/items', 'POST' => '/items'], Router::$namedRouteMethods['items.save']);

            foreach (['GET', 'POST'] as $method) {
                $router->getCallback($this->request($method, '/items'));
                $this->assertSame('items.save', $router->currentRouteName());
            }
        });
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function olderRouteCacheProvider(): array
    {
        return [
            'no methods' => [[]],
            'one method per name' => [['namedRouteMethods' => ['items.index' => 'GET']]],
        ];
    }

    #[DataProvider('olderRouteCacheProvider')]
    public function testNameFromAnOlderRouteCacheStillResolves(array $format): void
    {
        $this->withRouteCache(function (string $cache) use ($format): void {
            file_put_contents($cache, '<?php return ' . var_export($format + [
                'routes' => ['GET' => ['/items' => [NamedRouteController::class, 'handle']]],
                'namedRoutes' => ['items.index' => '/items'],
                'routeMiddlewares' => [],
            ], true) . ';');

            $router = new RouteCacheRouter(new Gateway());
            $this->assertTrue($router->loadCachedRoutes());

            $router->getCallback($this->request('GET', '/items'));
            $this->assertSame('items.index', $router->currentRouteName());
        });
    }

    private function withRouteCache(callable $test): void
    {
        $dir = sys_get_temp_dir() . '/doppar-route-names-' . bin2hex(random_bytes(5));
        mkdir($dir, 0755, true);
        RouteCacheRouter::pointTo($dir . '/routes.php');

        try {
            $test($dir . '/routes.php');
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($dir);
        }
    }

    public function testCurrentRouteActionKeepsTheDispatchedRouteOfACachedApp(): void
    {
        $this->router->routeFile = fn(Router $router) => $router->get('/closure', fn() => 'x')
            ->name('closure.route')
            ->middleware('auth');

        $request = $this->request('GET', '/closure');

        $this->assertFalse($this->router->getCallback($request));
        $this->assertNotFalse($this->router->reloadOriginalClosure($request));

        $this->assertSame('Closure', $this->router->currentRouteAction());
        $this->assertSame('closure.route', $this->router->currentRouteName());
        $this->assertSame(['auth'], $this->router->getCurrentRouteMiddleware($request));
    }
}
