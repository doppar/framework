<?php

namespace Tests\Unit\Router;

require_once __DIR__ . '/../Support/MockContainer.php';
require_once __DIR__ . '/../Support/Gateway.php';

use Phaseolies\DI\Container;
use Phaseolies\Http\Request;
use Phaseolies\Support\Router;
use Phaseolies\Support\StringService;
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
