<?php

namespace Tests\Unit\Router;

use Tests\Support\MockContainer;
use Tests\Support\Kernel;
use Phaseolies\Utilities\Attributes\Middleware;
use Phaseolies\Support\Router;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Http\Controllers\Controller;
use Phaseolies\DI\Container;
use Phaseolies\Application;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

if (!class_exists('App\Http\Kernel')) {
    class_alias(Kernel::class, 'App\Http\Kernel');
}

class TestRequestStub extends Request
{
    private string $testMethod;
    private string $testPath;
    private string $testHost;
    private array $testRouteParams = [];

    public function __construct(string $method, string $path, string $host)
    {
        $this->testMethod = strtoupper($method);
        $this->testPath = $path;
        $this->testHost = $host;
    }

    public function getMethod(): string
    {
        return $this->testMethod;
    }

    public function getPath(): string
    {
        return $this->testPath;
    }

    public function getHost(): string
    {
        return $this->testHost;
    }

    public function getRouteParams(): array
    {
        return $this->testRouteParams;
    }

    public function setRouteParams(array $params): self
    {
        $this->testRouteParams = $params;

        return $this;
    }
}

class TestableRouter extends Router
{
    public function handle(Request $request, \Closure $handler): Response
    {
        return $handler($request);
    }
}

#[AllowMockObjectsWithoutExpectations]
class RouterTest extends TestCase
{
    private Router $router;
    private Application $app;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        Container::setInstance(new MockContainer());
        $container = new Container();
        $container->bind('request', fn() => Request::class);
        $this->request = new Request();

        $this->app = $this->createMock(Application::class);
        $this->router = new Router($this->app);

        // Clear static properties before each test
        $reflection = new \ReflectionClass(Router::class);

        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setValue(null, []);

        $namedRoutesProperty = $reflection->getProperty('namedRoutes');
        $namedRoutesProperty->setValue(null, []);

        $routeMiddlewaresProperty = $reflection->getProperty('routeMiddlewares');
        $routeMiddlewaresProperty->setValue(null, [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'PATCH' => [],
            'DELETE' => [],
            'OPTIONS' => [],
            'HEAD' => [],
            'ANY' => [],
        ]);
    }

    protected function tearDown(): void
    {
        unset(
            $_SERVER['HTTP_HOST'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['REQUEST_METHOD'],
        );

        parent::tearDown();
    }

    // =========================================================================
    // HTTP Method Registration Tests
    // =========================================================================

    public function testGetMethodRegistersRoute(): void
    {
        $callback = fn() => 'test response';
        $result = $this->router->get('/test', $callback);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/test', $routes['GET']);
        $this->assertSame($callback, $routes['GET']['/test']);
    }

    public function testPostMethodRegistersRoute(): void
    {
        $callback = fn() => 'post response';
        $result = $this->router->post('/test', $callback);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('POST', $routes);
        $this->assertArrayHasKey('/test', $routes['POST']);
    }

    public function testPutMethodRegistersRoute(): void
    {
        $callback = fn() => 'put response';
        $result = $this->router->put('/test', $callback);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('PUT', $routes);
        $this->assertArrayHasKey('/test', $routes['PUT']);
    }

    public function testPatchMethodRegistersRoute(): void
    {
        $callback = fn() => 'patch response';
        $result = $this->router->patch('/test', $callback);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('PATCH', $routes);
        $this->assertArrayHasKey('/test', $routes['PATCH']);
    }

    public function testDeleteMethodRegistersRoute(): void
    {
        $callback = fn() => 'delete response';
        $result = $this->router->delete('/test', $callback);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('DELETE', $routes);
        $this->assertArrayHasKey('/test', $routes['DELETE']);
    }

    public function testOptionsMethodRegistersRoute(): void
    {
        $callback = fn() => 'options response';
        $result = $this->router->options('/test', $callback);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('OPTIONS', $routes);
        $this->assertArrayHasKey('/test', $routes['OPTIONS']);
    }

    public function testHeadMethodRegistersRoute(): void
    {
        $callback = fn() => 'head response';
        $result = $this->router->head('/test', $callback);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('HEAD', $routes);
        $this->assertArrayHasKey('/test', $routes['HEAD']);
    }

    public function testAnyMethodRegistersRoute(): void
    {
        $callback = fn() => 'any response';

        $_SERVER['REQUEST_METHOD'] = 'GET';

        $request = new Request();
        $container = Container::getInstance();
        $container->bind('request', fn() => $request);

        $result = $this->router->any('/test', $callback);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertEquals('GET', $request->getMethod());
        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/test', $routes['GET']);
    }

    // =========================================================================
    // Named Routes Tests
    // =========================================================================

    public function testNameMethodAssignsRouteName(): void
    {
        $this->router->get('/test', fn() => 'test')->name('test.route');

        $reflection = new \ReflectionClass(Router::class);
        $namedRoutesProperty = $reflection->getProperty('namedRoutes');
        $namedRoutes = $namedRoutesProperty->getValue($this->router);

        $this->assertArrayHasKey('test.route', $namedRoutes);
        $this->assertEquals('/test', $namedRoutes['test.route']);
    }

    public function testRouteMethodGeneratesUrlForNamedRoute(): void
    {
        $this->router->get('/users/{id}', fn() => 'user')->name('users.show');

        $url = $this->router->route('users.show', ['id' => 123]);

        $this->assertEquals('/users/123', $url);
    }

    public function testRouteMethodReturnsNullForNonExistentRoute(): void
    {
        $url = $this->router->route('non.existent.route');

        $this->assertNull($url);
    }

    // =========================================================================
    // Middleware Tests
    // =========================================================================

    public function testMiddlewareMethodAssignsMiddlewareToRoute(): void
    {
        $this->router->get('/admin', fn() => 'admin')
            ->middleware('auth', 'admin');

        $reflection = new \ReflectionClass(Router::class);
        $routeMiddlewaresProperty = $reflection->getProperty('routeMiddlewares');
        $routeMiddlewares = $routeMiddlewaresProperty->getValue($this->router);

        $this->assertArrayHasKey('GET', $routeMiddlewares);
        $this->assertArrayHasKey('/admin', $routeMiddlewares['GET']);
        $this->assertEquals(['auth', 'admin'], $routeMiddlewares['GET']['/admin']);
    }

    public function testGetCurrentRouteMiddleware(): void
    {
        $this->router->get('/admin', fn() => 'admin')->middleware('auth', 'admin');

        $_SERVER['REQUEST_URI'] = '/admin';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();

        $middleware = $this->router->getCurrentRouteMiddleware($request);

        $this->assertEquals(['auth', 'admin'], $middleware);
    }

    // =========================================================================
    // Route Groups Tests
    // =========================================================================

    public function testGroupMethodAppliesPrefix(): void
    {
        $this->router->group(['prefix' => 'api'], function ($router) {
            $router->get('/users', fn() => 'users');
        });

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('/api/users', $routes['GET']);
    }

    public function testGroupMethodWithNestedPrefix(): void
    {
        $this->router->group(['prefix' => 'api'], function ($router) {
            $router->group(['prefix' => 'v1'], function ($router) {
                $router->get('/users', fn() => 'users');
            });
        });

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('/api/v1/users', $routes['GET']);
    }

    public function testGroupWithMiddleware(): void
    {
        $this->router->group(['prefix' => 'api'], function ($router) {
            $router->get('/users', fn() => 'users')->middleware('api');
        });

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $routeMiddlewaresProperty = $reflection->getProperty('routeMiddlewares');
        $routeMiddlewares = $routeMiddlewaresProperty->getValue($this->router);

        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/api/users', $routes['GET']);
        $this->assertArrayHasKey('GET', $routeMiddlewares);
        $this->assertArrayHasKey('/api/users', $routeMiddlewares['GET']);
        $this->assertContains('api', $routeMiddlewares['GET']['/api/users']);
    }

    // =========================================================================
    // Route Matching Tests
    // =========================================================================

    public function testGetCallbackFindsExactRoute(): void
    {
        $callback = fn() => 'exact match';
        $this->router->get('/exact', $callback);

        $request = new TestRequestStub('GET', '/exact', 'localhost');
        $result = $this->router->getCallback($request);

        $this->assertSame($callback, $result);
    }

    public function testGetCallbackFindsParameterizedRoute(): void
    {
        $callback = fn() => 'user profile';
        $this->router->get('/users/{id}', $callback);

        $request = new TestRequestStub('GET', '/users/123', 'localhost');
        $result = $this->router->getCallback($request);

        $this->assertSame($callback, $result);
        $this->assertEquals(['id' => '123'], $request->getRouteParams());
    }

    public function testWildcardRoute(): void
    {
        $callback = fn() => 'catch all';
        $this->router->get('*', $callback);

        $request = new TestRequestStub('GET', '/any/path', 'localhost');
        $result = $this->router->getCallback($request);

        $this->assertSame($callback, $result);
    }

    public function testRouteWithTrailingSlashNormalization(): void
    {
        $callback = fn() => 'test';
        $this->router->get('/test/', $callback);

        $request = new TestRequestStub('GET', '/test', 'localhost');
        $result = $this->router->getCallback($request);

        $this->assertSame($callback, $result);
    }

    public function testGetCallbackReturnsFalseForNotFound(): void
    {
        $request = new TestRequestStub('GET', '/nonexistent', 'localhost');
        $result = $this->router->getCallback($request);

        $this->assertFalse($result);
    }

    public function testResolveUsesFreshResponseForScalarRouteResults(): void
    {
        $freshRouter = new TestableRouter($this->app);
        $sharedResponse = new Response('stale body', 202, ['X-Leaked' => 'yes']);

        Container::getInstance()->instance('response', $sharedResponse);

        $freshRouter->get('/fresh', fn() => 'fresh body');

        $request = new TestRequestStub('GET', '/fresh', 'localhost');
        $response = $freshRouter->resolve($this->app, $request);

        $this->assertNotSame($sharedResponse, $response);
        $this->assertSame('fresh body', $response->getBody());
        $this->assertNull($response->headers->get('X-Leaked'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testViewHelperReturnsFreshResponseInstance(): void
    {
        $sharedResponse = new Response('stale body', 202, ['X-Leaked' => 'yes']);
        $controller = $this->createMock(Controller::class);
        $controller->expects($this->once())
            ->method('render')
            ->with('demo', ['name' => 'Doppar'], true)
            ->willReturn('<h1>Doppar</h1>');

        Container::getInstance()->instance('response', $sharedResponse);
        Container::getInstance()->instance(Controller::class, $controller);

        $response = view('demo', ['name' => 'Doppar'], ['X-Test' => 'ok']);

        $this->assertNotSame($sharedResponse, $response);
        $this->assertSame('<h1>Doppar</h1>', $response->getBody());
        $this->assertSame('<h1>Doppar</h1>', $response->getOriginal());
        $this->assertSame('ok', $response->headers->get('X-Test'));
        $this->assertNull($response->headers->get('X-Leaked'));
        $this->assertSame(200, $response->getStatusCode());
    }

    // =========================================================================
    // Redirect Tests
    // =========================================================================

    public function testRedirectMethodRegistersRedirectRoute(): void
    {
        $result = $this->router->redirect('/old', '/new', 301);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/old', $routes['GET']);
        $this->assertInstanceOf(\Closure::class, $routes['GET']['/old']);
    }

    public function testRedirectWithExternalUrl(): void
    {
        $result = $this->router->redirect('/old', 'https://external.com', 301);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/old', $routes['GET']);
    }

    public function testRedirectWithNamedRoute(): void
    {
        $this->router->get('/new-route', fn() => 'new')->name('new.route');
        $result = $this->router->redirect('/old', 'new.route', 301);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/old', $routes['GET']);
    }

    // =========================================================================
    // Domain Routing Tests (New Feature)
    // =========================================================================

    public function testDomainFluentMethodRestrictsRouteToExactHost(): void
    {
        $callback = fn() => 'api only';
        $this->router->get('/status', $callback)->domain('api.example.com');

        $hit  = new TestRequestStub('GET', '/status', 'api.example.com');
        $miss = new TestRequestStub('GET', '/status', 'www.example.com');

        $this->assertSame($callback, $this->router->getCallback($hit));
        $this->assertFalse($this->router->getCallback($miss));
    }

    public function testDomainFluentMethodWithPortQualifiedHost(): void
    {
        $callback = fn() => 'local';
        $this->router->get('/', $callback)->domain('localhost:8000');

        $hit  = new TestRequestStub('GET', '/', 'localhost:8000');
        $miss = new TestRequestStub('GET', '/', 'localhost:3000');

        $this->assertSame($callback, $this->router->getCallback($hit));
        $this->assertFalse($this->router->getCallback($miss));
    }

    // public function testDomainWildcardSubdomainInjectsRouteParam(): void
    // {
    //     $callback = fn(string $tenant) => $tenant;
    //     $this->router->get('/home', $callback)->domain('{tenant}.example.com');

    //     $request = new TestRequestStub('GET', '/home', 'acme.example.com');
    //     $result  = $this->router->getCallback($request);

    //     $this->assertSame($callback, $result);
    //     $this->assertSame('acme', $request->getRouteParams()['tenant']);
    // }

    public function testDomainWildcardDoesNotMatchBareHost(): void
    {
        $this->router->get('/home', fn() => 'tenant')->domain('{tenant}.example.com');

        $request = new TestRequestStub('GET', '/home', 'example.com');

        $this->assertFalse($this->router->getCallback($request));
    }

    // public function testSamePathDifferentDomainsDispatchCorrectly(): void
    // {
    //     $webCallback = fn() => 'web';
    //     $apiCallback = fn() => 'api';

    //     $this->router->get('/', $webCallback)->domain('example.com');
    //     $this->router->get('/', $apiCallback)->domain('api.example.com');

    //     $webRequest = new TestRequestStub('GET', '/', 'example.com');
    //     $apiRequest = new TestRequestStub('GET', '/', 'api.example.com');

    //     $this->assertSame($webCallback, $this->router->getCallback($webRequest));
    //     $this->assertSame($apiCallback, $this->router->getCallback($apiRequest));
    // }

    public function testNoDomainRouteMatchesAnyHost(): void
    {
        $callback = fn() => 'public';
        $this->router->get('/public', $callback);

        foreach (['localhost', 'api.example.com', 'admin.example.com'] as $host) {
            $request = new TestRequestStub('GET', '/public', $host);
            $this->assertSame($callback, $this->router->getCallback($request), "Failed for host: $host");
        }
    }

    public function testDomainUniversalWildcardMatchesAnyHost(): void
    {
        $callback = fn() => 'catch all hosts';
        $this->router->get('/ping', $callback)->domain('*');

        foreach (['example.com', 'api.example.com', 'localhost:8000'] as $host) {
            $request = new TestRequestStub('GET', '/ping', $host);
            $this->assertSame($callback, $this->router->getCallback($request), "Failed for host: $host");
        }
    }

    public function testDomainWrappedRouteIsCacheable(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('isCacheableRoute');

        $controller = new class {
            public function index() {}
        };

        $wrappedEntry = [
            '__callback' => [get_class($controller), 'index'],
            '__domain'   => 'api.example.com',
        ];

        $this->assertTrue($method->invoke($this->router, $wrappedEntry));
    }

    public function testGetCacheableRoutesPreservesDomain(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $getCacheableRoutes = $reflection->getMethod('getCacheableRoutes');

        $this->router->get('/invokable', InvokableTestClass::class)->domain('api.example.com');

        $result = $getCacheableRoutes->invoke($this->router);

        $this->assertArrayHasKey('GET', $result);
        $this->assertArrayHasKey('/invokable', $result['GET']);

        $entry = $result['GET']['/invokable'];
        $this->assertIsArray($entry);
        $this->assertArrayHasKey('__callback', $entry);
        $this->assertArrayHasKey('__domain', $entry);
        $this->assertSame('api.example.com', $entry['__domain']);
    }

    // =========================================================================
    // Internal Method Tests
    // =========================================================================

    public function testExtractRouteParametersExtractsParams(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('extractRouteParameters');

        $matches = ['id' => '123', 'name' => 'john'];
        $result = $method->invoke($this->router, '/users/{id}/profile/{name}', $matches);

        $this->assertEquals(['id' => '123', 'name' => 'john'], $result);
    }

    public function testConvertRouteToRegex(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('convertRouteToRegex');

        $result = $method->invoke($this->router, '/users/{id}');

        $this->assertEquals('@^\/users\/(?P<id>[^\/]+)$@D', $result);
    }

    public function testIsCacheableRoute(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('isCacheableRoute');

        // Test controller array (should be cacheable if method exists)
        $controller = new class {
            public function index() {}
        };
        $result = $method->invoke($this->router, [get_class($controller), 'index']);
        $this->assertTrue($result);

        // Test invokable class (should be cacheable)
        $invokable = new class {
            public function __invoke() {}
        };
        $result = $method->invoke($this->router, get_class($invokable));
        $this->assertTrue($result);

        // Test closure (should not be cacheable)
        $result = $method->invoke($this->router, fn() => 'test');
        $this->assertFalse($result);
    }

    public function testGetCacheableRoutes(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('getCacheableRoutes');

        $this->router->get('/invokable', InvokableTestClass::class);

        $result = $method->invoke($this->router);

        $this->assertArrayHasKey('GET', $result);
        $this->assertArrayHasKey('/invokable', $result['GET']);
    }

    // =========================================================================
    // Validation Tests
    // =========================================================================

    public function testFailFastOnBadRouteDefinitionThrowsForInvalidArray(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Method NonExistentController::nonExistentMethod() does not exist');

        $this->router->failFastOnBadRouteDefinition(['NonExistentController', 'nonExistentMethod']);
    }

    public function testFailFastOnBadRouteDefinitionThrowsForInvalidString(): void
    {
        $this->expectException(\LogicException::class);

        $this->router->failFastOnBadRouteDefinition(\stdClass::class);
    }

    public function testFailFastOnBadRouteDefinitionThrowsForInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->router->failFastOnBadRouteDefinition(123);
    }

    // =========================================================================
    // Controller Resolution Tests
    // =========================================================================

    public function testProcessControllerMiddlewareProcessesAttributes(): void
    {
        $controller = new class {
            #[Middleware('auth')]
            public function index() {}
        };

        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('processControllerMiddleware');

        $method->invoke($this->router, [get_class($controller), 'index']);

        $routeMiddlewaresProperty = $reflection->getProperty('routeMiddlewares');
        $routeMiddlewares = $routeMiddlewaresProperty->getValue($this->router);

        $this->assertNotEmpty($routeMiddlewares);
    }

    public function testResolveActionWithControllerArray(): void
    {
        $controller = new class {
            public function index()
            {
                return 'controller result';
            }
        };

        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('resolveAction');

        $app = $this->createMock(Application::class);
        $app->method('make')->willReturn($controller);

        $result = $method->invoke($this->router, [get_class($controller), 'index'], $app, []);

        $this->assertEquals('controller result', $result);
    }

    public function testResolveActionWithInvokableController(): void
    {
        $controller = new class {
            public function __invoke()
            {
                return 'invokable result';
            }
        };

        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('resolveAction');

        $app = $this->createMock(Application::class);
        $app->method('make')->willReturn($controller);

        $result = $method->invoke($this->router, get_class($controller), $app, []);

        $this->assertEquals('invokable result', $result);
    }

    public function testResolveActionWithClosure(): void
    {
        $callback = fn($id, $name) => "User $id: $name";

        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('resolveAction');

        $app = $this->createMock(Application::class);

        $routeParams = ['id' => 123, 'name' => 'John'];
        $result = $method->invoke($this->router, $callback, $app, $routeParams);

        $this->assertEquals('User 123: John', $result);
    }

    public function testProcessRateLimitAnnotation(): void
    {
        $controller = new class {
            /**
             * @RateLimit 60/1
             */
            public function limited() {}
        };

        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('processRateLimitAnnotation');

        $methodReflection = new \ReflectionMethod($controller, 'limited');
        $method->invoke($this->router, $methodReflection);

        $routeMiddlewaresProperty = $reflection->getProperty('routeMiddlewares');
        $routeMiddlewares = $routeMiddlewaresProperty->getValue($this->router);

        $this->assertNotEmpty($routeMiddlewares);
    }
}

class InvokableTestClass
{
    public function __invoke()
    {
        return 'invokable result';
    }
}
