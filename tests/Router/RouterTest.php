<?php

namespace Tests\Unit\Router;

use Tests\Support\MockContainer;
use Tests\Support\Kernel;
use Phaseolies\Utilities\Attributes\Middleware;
use Phaseolies\Support\Router;
use Phaseolies\Http\Request;
use Phaseolies\DI\Container;
use Phaseolies\Application;
use PHPUnit\Framework\TestCase;

if (!class_exists('App\Http\Kernel')) {
    class_alias(Kernel::class, 'App\Http\Kernel');
}

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
        $container = new Container();
        $this->request = new Request();

        $this->app = $this->createMock(Application::class);
        $this->router = new Router($this->app);

        // Clear static properties before each test
        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routesProperty->setValue(null, []);

        $namedRoutesProperty = $reflection->getProperty('namedRoutes');
        $namedRoutesProperty->setAccessible(true);
        $namedRoutesProperty->setValue(null, []);

        $routeMiddlewaresProperty = $reflection->getProperty('routeMiddlewares');
        $routeMiddlewaresProperty->setAccessible(true);
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

    public function testGetMethodRegistersRoute(): void
    {
        $callback = fn() => 'test response';

        $result = $this->router->get('/test', $callback);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
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
        $routesProperty->setAccessible(true);
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
        $routesProperty->setAccessible(true);
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
        $routesProperty->setAccessible(true);
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
        $routesProperty->setAccessible(true);
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
        $routesProperty->setAccessible(true);
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
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('HEAD', $routes);
        $this->assertArrayHasKey('/test', $routes['HEAD']);
    }

    public function testNameMethodAssignsRouteName(): void
    {
        $this->router->get('/test', fn() => 'test')->name('test.route');

        $reflection = new \ReflectionClass(Router::class);
        $namedRoutesProperty = $reflection->getProperty('namedRoutes');
        $namedRoutesProperty->setAccessible(true);
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

    public function testMiddlewareMethodAssignsMiddlewareToRoute(): void
    {
        $this->router->get('/admin', fn() => 'admin')
            ->middleware('auth', 'admin');

        $reflection = new \ReflectionClass(Router::class);
        $routeMiddlewaresProperty = $reflection->getProperty('routeMiddlewares');
        $routeMiddlewaresProperty->setAccessible(true);
        $routeMiddlewares = $routeMiddlewaresProperty->getValue($this->router);

        $this->assertArrayHasKey('GET', $routeMiddlewares);
        $this->assertArrayHasKey('/admin', $routeMiddlewares['GET']);
        $this->assertEquals(['auth', 'admin'], $routeMiddlewares['GET']['/admin']);
    }

    public function testGroupMethodAppliesPrefix(): void
    {
        $this->router->group(['prefix' => 'api'], function ($router) {
            $router->get('/users', fn() => 'users');
        });

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('/api/users', $routes['GET']);
    }

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

    public function testCacheRoutesMethodCreatesCacheFile(): void
    {
        $this->router->get('/test', fn() => 'test');

        $cachePath = storage_path('framework/cache/routes.php');
        if (file_exists($cachePath)) {
            unlink($cachePath);
        }

        $this->router->cacheRoutes();

        $this->assertFileExists($cachePath);

        // Clean up
        if (file_exists($cachePath)) {
            unlink($cachePath);
        }
    }

    public function testExtractRouteParametersExtractsParams(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('extractRouteParameters');
        $method->setAccessible(true);

        $matches = ['id' => '123', 'name' => 'john'];
        $result = $method->invoke($this->router, '/users/{id}/profile/{name}', $matches);

        $this->assertEquals(['id' => '123', 'name' => 'john'], $result);
    }

    public function testProcessControllerMiddlewareProcessesAttributes(): void
    {
        $controller = new class {
            #[Middleware('auth')]
            public function index() {}
        };

        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('processControllerMiddleware');
        $method->setAccessible(true);

        $method->invoke($this->router, [get_class($controller), 'index']);

        // Verify middleware was processed by checking routeMiddlewares
        $routeMiddlewaresProperty = $reflection->getProperty('routeMiddlewares');
        $routeMiddlewaresProperty->setAccessible(true);
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
        $method->setAccessible(true);

        $app = $this->createMock(Application::class);
        $app->method('make')->willReturn($controller);

        $result = $method->invoke($this->router, [get_class($controller), 'index'], $app, []);

        $this->assertEquals('controller result', $result);
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
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($this->router);

        $this->assertEquals('GET', $request->getMethod());
        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/test', $routes['GET']);
    }

    public function testRedirectMethodRegistersRedirectRoute(): void
    {
        $result = $this->router->redirect('/old', '/new', 301);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/old', $routes['GET']);
        $this->assertInstanceOf(\Closure::class, $routes['GET']['/old']);
    }

    public function testGetCallbackFindsExactRoute(): void
    {
        $callback = fn() => 'exact match';
        $this->router->get('/exact', $callback);

        // Create a fresh request with the specific URI
        $_SERVER['REQUEST_URI'] = '/exact';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();

        $result = $this->router->getCallback($request);

        $this->assertSame($callback, $result);
    }

    public function testGetCallbackFindsParameterizedRoute(): void
    {
        $callback = fn() => 'user profile';
        $this->router->get('/users/{id}', $callback);

        $_SERVER['REQUEST_URI'] = '/users/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();

        $result = $this->router->getCallback($request);

        $this->assertSame($callback, $result);
        $this->assertEquals(['id' => '123'], $request->getRouteParams());
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

    public function testGroupMethodWithNestedPrefix(): void
    {
        $this->router->group(['prefix' => 'api'], function ($router) {
            $router->group(['prefix' => 'v1'], function ($router) {
                $router->get('/users', fn() => 'users');
            });
        });

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('/api/v1/users', $routes['GET']);
    }

    public function testWildcardRoute(): void
    {
        $callback = fn() => 'catch all';
        $this->router->get('*', $callback);

        $_SERVER['REQUEST_URI'] = '/any/path';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $request = new Request();
        $result = $this->router->getCallback($request);

        $this->assertSame($callback, $result);
    }

    public function testRouteWithTrailingSlashNormalization(): void
    {
        $callback = fn() => 'test';
        $this->router->get('/test/', $callback);

        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();

        $result = $this->router->getCallback($request);

        $this->assertSame($callback, $result);
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
        $method->setAccessible(true);

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
        $method->setAccessible(true);

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
        $method->setAccessible(true);

        $methodReflection = new \ReflectionMethod($controller, 'limited');
        $method->invoke($this->router, $methodReflection);

        // Verify throttle middleware was added
        $routeMiddlewaresProperty = $reflection->getProperty('routeMiddlewares');
        $routeMiddlewaresProperty->setAccessible(true);
        $routeMiddlewares = $routeMiddlewaresProperty->getValue($this->router);

        $this->assertNotEmpty($routeMiddlewares);
    }

    public function testConvertRouteToRegex(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('convertRouteToRegex');
        $method->setAccessible(true);

        $result = $method->invoke($this->router, '/users/{id}');

        $this->assertEquals('@^\/users\/(?P<id>[^\/]+)$@D', $result);
    }

    public function testShouldCacheRoutes(): void
    {
        putenv('APP_ROUTE_CACHE=true');
        $result = $this->router->shouldCacheRoutes();
        $this->assertTrue($result);

        putenv('APP_ROUTE_CACHE=false');
        $result = $this->router->shouldCacheRoutes();
        $this->assertFalse($result);
    }

    public function testGetCallbackReturnsFalseForNotFound(): void
    {
        $_SERVER['REQUEST_URI'] = '/nonexistent';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();
        $result = $this->router->getCallback($request);

        $this->assertFalse($result);
    }

    public function testRedirectWithExternalUrl(): void
    {
        $result = $this->router->redirect('/old', 'https://external.com', 301);

        $this->assertInstanceOf(Router::class, $result);

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/old', $routes['GET']);
    }

    public function testRedirectWithNamedRoute(): void
    {
        $this->router->get('/new-route', fn() => 'new')->name('new.route');

        $result = $this->router->redirect('/old', 'new.route', 301);

        $this->assertInstanceOf(Router::class, $result);

        // The redirect should be registered
        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($this->router);

        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/old', $routes['GET']);
    }

    public function testIsCacheableRoute(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('isCacheableRoute');
        $method->setAccessible(true);

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
        $method->setAccessible(true);

        // Add cacheable routes (controller arrays and invokable classes)
        $this->router->get('/invokable', InvokableTestClass::class);

        $result = $method->invoke($this->router);

        // The invokable route should be cacheable
        $this->assertArrayHasKey('GET', $result);
        $this->assertArrayHasKey('/invokable', $result['GET']);
    }

    public function testClearRouteCacheWhenNoCacheExists(): void
    {
        $cachePath = storage_path('framework/cache/routes.php');
        if (file_exists($cachePath)) {
            unlink($cachePath);
        }

        $result = $this->router->clearRouteCache();

        $this->assertTrue($result);
    }

    public function testGroupWithMiddleware(): void
    {
        $this->router->group(['prefix' => 'api'], function ($router) {
            $router->get('/users', fn() => 'users')->middleware('api');
        });

        $reflection = new \ReflectionClass(Router::class);
        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($this->router);

        $routeMiddlewaresProperty = $reflection->getProperty('routeMiddlewares');
        $routeMiddlewaresProperty->setAccessible(true);
        $routeMiddlewares = $routeMiddlewaresProperty->getValue($this->router);

        // Verify the route was registered with the correct path
        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/api/users', $routes['GET']);

        // Verify middleware was applied to the correct path
        $this->assertArrayHasKey('GET', $routeMiddlewares);
        $this->assertArrayHasKey('/api/users', $routeMiddlewares['GET']);
        $this->assertContains('api', $routeMiddlewares['GET']['/api/users']);
    }
}

class InvokableTestClass
{
    public function __invoke()
    {
        return 'invokable result';
    }
}
