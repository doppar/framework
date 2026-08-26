<?php

namespace Tests\Unit\Application;

use ReflectionClass;
use Tests\Support\Kernel;
use Phaseolies\Application;
use Phaseolies\Auth\ActorManager;
use Phaseolies\DI\Container;
use Phaseolies\Http\DispatchResult;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Config\Config;
use Phaseolies\Http\Response\RedirectResponse;
use Phaseolies\Support\Router;
use Phaseolies\Support\Session;
use Phaseolies\Console\Console;
use Tests\Application\Mock\Providers\GhostableTestProvider;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Phaseolies\Support\StringService;
use Phaseolies\Support\View\Factory as ViewFactory;

if (!class_exists('App\Http\Kernel')) {
    class_alias(Kernel::class, 'App\Http\Kernel');
}

function base_path($path = '')
{
    return '/test/path' . ($path ? '/' . $path : '');
}

function config($key = null, $default = null)
{
    return $default;
}

function env($key, $default = null)
{
    return $default;
}

function app($abstract = null, array $parameters = [])
{
    return \Phaseolies\DI\Container::getInstance()->make($abstract, $parameters);
}

class ErrorHandler
{
    public static function handle()
    {
        // No-op for testing
    }
}

#[AllowMockObjectsWithoutExpectations]
final class ApplicationTest extends TestCase
{
    private Application $app;
    private string $tempBasePath;

    protected function setUp(): void
    {
        $container = new Container();
        $container->flush();
        $container->bind('config', fn() => Config::class);

        // Create a temporary directory structure
        $this->tempBasePath = sys_get_temp_dir() . '/phaseolies_app_test_' . uniqid();
        $this->createDirectoryStructure();

        // Create application instance without calling constructor
        $this->app = $this->getMockBuilder(Application::class)
            ->onlyMethods([
                'registerCoreProviders',
                'bootCoreProviders',
                'withConfiguration',
                'withExceptionHandler'
            ])
            ->disableOriginalConstructor()
            ->getMock();

        // Set up the mock methods to do nothing
        $this->app->method('registerCoreProviders')->willReturnSelf();
        $this->app->method('bootCoreProviders')->willReturnSelf();
        $this->app->method('withConfiguration')->willReturnSelf();
        $this->app->method('withExceptionHandler')->willReturnSelf();

        // Now call the parent constructor manually without the problematic initialization
        $reflection = new ReflectionClass(Application::class);
        $constructor = $reflection->getConstructor();
        $constructor->invoke($this->app);

        // Set base path for testing
        $this->app->withBasePath($this->tempBasePath);
    }

    protected function tearDown(): void
    {
        GhostableTestProvider::resetState();
        $this->app->flush();
        Container::forgetInstance();
        $this->deleteDirectory($this->tempBasePath);
    }

    private function createDirectoryStructure(): void
    {
        $dirs = [
            '/runtime/config',
            '/runtime',
            '/schema/migrations',
            '/public',
            '/storage',
            '/templates',
            '/templates/lang',
            '/src',
        ];

        foreach ($dirs as $dir) {
            $path = $this->tempBasePath . $dir;

            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }

        // Create minimal config file
        file_put_contents($this->tempBasePath . '/runtime/config/app.php', "<?php return [
            'env' => 'testing',
            'locale' => 'en',
            'fallback_locale' => 'en',
            'providers' => [],
            'aliases' => [],
        ];");
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    private function setProtectedProperty($object, $property, $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setValue($object, $value);
    }

    private function getProtectedProperty($object, $property)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($property);
        return $property->getValue($object);
    }

    private function callProtectedMethod($object, $method, $args = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        return $method->invokeArgs($object, $args);
    }

    public function testApplicationVersionConstant(): void
    {
        $this->assertGreaterThan('2.9.6-beta.18', Application::VERSION);
    }

    public function testApplicationIsContainerInstance(): void
    {
        $this->assertInstanceOf(\Phaseolies\DI\Container::class, $this->app);
    }

    public function testPathMethodsReturnCorrectPaths(): void
    {
        $stringService = app(StringService::class);
        $this->assertStringEndsWith('/templates/views', $stringService->urlHarmonize($this->app->templatesPath('views')));
        $this->assertStringEndsWith('/templates/client/js/pages', $stringService->urlHarmonize($this->app->clientPath('js\\pages')));
        $this->assertStringEndsWith('/runtime/cache', $stringService->urlHarmonize($this->app->bootstrapPath('cache')));
        $this->assertStringEndsWith('/schema/migrations', $stringService->urlHarmonize($this->app->schemaPath('migrations')));
        $this->assertStringEndsWith('/public/assets', $stringService->urlHarmonize($this->app->publicPath('assets')));
        $this->assertStringEndsWith('/storage/logs', $stringService->urlHarmonize($this->app->storagePath('logs')));
        $this->assertStringEndsWith('/runtime/config/app.php', $stringService->urlHarmonize($this->app->configPath('app.php')));
        $this->assertStringEndsWith('/templates/lang/en', $stringService->urlHarmonize($this->app->langPath('en')));
    }

    public function testPathCaching(): void
    {
        $firstCall = $this->app->configPath();
        $secondCall = $this->app->configPath();

        $this->assertSame($firstCall, $secondCall);
    }

    public function testEnvironmentFileManagement(): void
    {
        $this->assertSame('.env', $this->app->environmentFile());

        $result = $this->app->loadEnvironmentFrom('.env.testing');
        $this->assertSame($this->app, $result);
        $this->assertSame('.env.testing', $this->app->environmentFile());
    }

    public function testCoreProvidersAreLoaded(): void
    {
        $providers = $this->callProtectedMethod($this->app, 'loadCoreProviders');

        $this->assertIsArray($providers);
        $this->assertContains(\Phaseolies\Providers\RouteServiceProvider::class, $providers);
        $this->assertContains(\Phaseolies\Providers\LanguageServiceProvider::class, $providers);
    }

    public function testGhostableProvidersAreQueuedOutsideConsole(): void
    {
        GhostableTestProvider::resetState();

        $this->setProtectedProperty($this->app, 'isRunningInConsole', false);

        $this->callProtectedMethod($this->app, 'registerProviders', [
            [GhostableTestProvider::class],
        ]);

        $this->assertTrue($this->app->has('ghost.service'));
        $this->assertSame([], $this->app->getProviders());
        $this->assertSame(0, GhostableTestProvider::$registerCount);
    }

    public function testGhostServiceResolutionLoadsQueuedProviderAndBootsIt(): void
    {
        GhostableTestProvider::resetState();

        $this->setProtectedProperty($this->app, 'isRunningInConsole', false);
        $this->setProtectedProperty($this->app, 'providersBooted', true);

        $this->callProtectedMethod($this->app, 'registerProviders', [
            [GhostableTestProvider::class],
        ]);

        $this->assertSame('ghost-value', $this->app->make('ghost.service'));
        $this->assertSame('booted', $this->app->make('ghost.booted'));
        $this->assertSame(1, GhostableTestProvider::$registerCount);
        $this->assertSame(1, GhostableTestProvider::$bootCount);
        $this->assertInstanceOf(
            GhostableTestProvider::class,
            $this->app->getProvider(GhostableTestProvider::class)
        );
    }

    public function testGhostServiceCanBeResolvedDuringProviderRegistration(): void
    {
        GhostableTestProvider::resetState();
        GhostableTestProvider::$resolveDuringRegister = true;

        $this->setProtectedProperty($this->app, 'isRunningInConsole', false);

        $this->callProtectedMethod($this->app, 'registerProviders', [
            [GhostableTestProvider::class],
        ]);

        $this->assertSame('ghost-value', $this->app->make('ghost.service'));
        $this->assertSame(1, GhostableTestProvider::$registerCount);
    }

    public function testGhostableProvidersRemainEagerInConsole(): void
    {
        GhostableTestProvider::resetState();

        $this->setProtectedProperty($this->app, 'isRunningInConsole', true);

        $this->callProtectedMethod($this->app, 'registerProviders', [
            [GhostableTestProvider::class],
        ]);

        $this->assertSame(1, GhostableTestProvider::$registerCount);
        $this->assertCount(1, $this->app->getProviders());
    }

    public function testSingletonBindings(): void
    {
        $this->callProtectedMethod($this->app, 'bindSingletonClasses');

        // Test path bindings
        $this->assertSame($this->app->langPath(), $this->app->make('path.lang'));
        $this->assertSame($this->app->configPath(), $this->app->make('path.config'));
        $this->assertSame($this->app->publicPath(), $this->app->make('path.public'));

        // Test core singleton bindings
        $this->assertInstanceOf(Request::class, $this->app->make('request'));
        $this->assertInstanceOf(Router::class, $this->app->make('route'));
        $this->assertInstanceOf(Console::class, $this->app->make('console'));
        $this->assertInstanceOf(ViewFactory::class, $this->app->make('view'));
    }

    public function testRouterIsInitialized(): void
    {
        $this->callProtectedMethod($this->app, 'bindSingletonClasses');

        $router = $this->app->router;
        $this->assertInstanceOf(Router::class, $router);
        $this->assertSame($router, $this->app->make('route'));
    }

    public function testRunningInConsoleDetection(): void
    {
        $this->assertTrue($this->app->runningInConsole());
    }

    public function testBootStatusTracking(): void
    {
        $this->assertFalse($this->app->isBooted());
        $this->assertFalse($this->app->hasBeenBootstrapped());

        $this->setProtectedProperty($this->app, 'booted', true);
        $this->setProtectedProperty($this->app, 'hasBeenBootstrapped', true);

        $this->assertTrue($this->app->isBooted());
        $this->assertTrue($this->app->hasBeenBootstrapped());
    }

    public function testRelaxablePathsManagement(): void
    {
        $paths = ['/api/*', '/webhook/*'];

        $result = $this->app->setRelaxablePaths($paths);
        $this->assertSame($this->app, $result);
        $this->assertSame($paths, $this->app->getRelaxablePaths());
    }

    public function testMakeMethodResolvesDependencies(): void
    {
        $this->callProtectedMethod($this->app, 'bindSingletonClasses');

        $request = $this->app->make(Request::class);

        $this->assertInstanceOf(Request::class, $request);
    }

    public function testUnitTestDetection(): void
    {
        $result = $this->app->isRunningUnitTests();

        $this->assertIsBool($result);
    }

    public function testExceptionHandlerInitialization(): void
    {
        $result = $this->app->withExceptionHandler();

        $this->assertSame($this->app, $result);
    }

    public function testConfigurationInitialization(): void
    {
        $result = $this->app->withConfiguration();

        $this->assertSame($this->app, $result);
    }

    public function testHandleReturnsResolvedResponse(): void
    {
        $request = new Request();
        $response = new Response('handled');

        $router = $this->createMock(Router::class);
        $router->expects($this->once())
            ->method('resolve')
            ->with($this->app, $request)
            ->willReturn($response);

        $this->app->router = $router;

        $handled = $this->app->handle($request);

        $this->assertSame($response, $handled);
    }

    public function testDispatchReturnsTerminableResult(): void
    {
        $request = new Request();

        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'send'])
            ->getMock();

        $response->expects($this->once())
            ->method('prepare')
            ->with($request)
            ->willReturnSelf();

        $response->expects($this->once())
            ->method('send')
            ->with()
            ->willReturnSelf();

        $router = $this->createMock(Router::class);
        $router->expects($this->once())
            ->method('resolve')
            ->with($this->app, $request)
            ->willReturn($response);

        $this->app->router = $router;

        $captured = [];

        $this->app->terminating(
            function (
                Request $requestArg,
                ?Response $responseArg,
                ?\Throwable $exception
            ) use (&$captured): void {
                $captured = [$requestArg, $responseArg, $exception];
            }
        );

        $result = $this->app->dispatch($request);

        $this->assertInstanceOf(DispatchResult::class, $result);

        $result->terminate();

        $this->assertSame($response, $result->response());
        $this->assertNull($result->exception());
        $this->assertSame($request, $captured[0]);
        $this->assertSame($response, $captured[1]);
        $this->assertNull($captured[2]);
    }

    public function testDispatchStillTerminatesWhenResultIsIgnored(): void
    {
        $request = new Request();

        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'send'])
            ->getMock();

        $response->expects($this->once())
            ->method('prepare')
            ->with($request)
            ->willReturnSelf();

        $response->expects($this->once())
            ->method('send')
            ->with()
            ->willReturnSelf();

        $router = $this->createMock(Router::class);
        $router->expects($this->once())
            ->method('resolve')
            ->with($this->app, $request)
            ->willReturn($response);

        $this->app->router = $router;

        $captured = [];

        $this->app->terminating(function (
            Request $requestArg,
            ?Response $responseArg,
            ?\Throwable $exception
        ) use (&$captured): void {
            $captured = [$requestArg, $responseArg, $exception];
        });

        $this->app->dispatch($request);

        $this->assertSame($request, $captured[0]);
        $this->assertSame($response, $captured[1]);
        $this->assertNull($captured[2]);
    }

    public function testDispatchRebindsCurrentRequestBeforeResolvingRoutes(): void
    {
        $oldRequest = new Request();
        $newRequest = new Request();

        $this->app->instance('request', $oldRequest);

        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'send'])
            ->getMock();

        $response->expects($this->once())
            ->method('prepare')
            ->with($newRequest)
            ->willReturnSelf();

        $response->expects($this->once())
            ->method('send')
            ->with()
            ->willReturnSelf();

        $router = $this->createMock(Router::class);
        $router->expects($this->once())
            ->method('resolve')
            ->with($this->app, $newRequest)
            ->willReturnCallback(function () use ($oldRequest, $newRequest, $response) {
                $this->assertSame($newRequest, app('request'));
                $this->assertSame($newRequest, app(Request::class));
                $this->assertNotSame($oldRequest, app('request'));

                return $response;
            });

        $this->app->router = $router;

        $this->app->dispatch($newRequest);
    }

    public function testDispatchResultDoesNotTerminateTwiceAfterExplicitTermination(): void
    {
        $request = new Request();
        $response = new Response('ok');
        $calls = 0;

        $this->app->terminating(function () use (&$calls): void {
            $calls++;
        });

        $result = new DispatchResult($this->app, $request, $response);

        $result->terminate();
        unset($result);

        $this->assertSame(1, $calls);
    }

    public function testTerminateProvidesLifecycleContextToNamedParameters(): void
    {
        $request = new Request();
        $response = new Response('ok');
        $exception = new HttpException(500, 'Lifecycle failed');

        $captured = [];

        $this->app->terminating(function ($request, $response, $exception) use (&$captured): void {
            $captured = compact('request', 'response', 'exception');
        });

        $this->app->terminate($request, $response, $exception);

        $this->assertSame($request, $captured['request']);
        $this->assertSame($response, $captured['response']);
        $this->assertSame($exception, $captured['exception']);
    }

    public function testTerminateCleansRequestScopedServicesAfterCallbacks(): void
    {
        $_SESSION = [];

        $request = new Request();
        $response = new Response('ok');
        $session = new Session();
        $redirect = new RedirectResponse();

        $auth = $this->getMockBuilder(ActorManager::class)
            ->onlyMethods(['forgetActors'])
            ->getMock();

        $auth->expects($this->once())
            ->method('forgetActors');

        $this->app->instance('request', $request);
        $this->app->instance('response', $response);
        $this->app->instance('session', $session);
        $this->app->instance('redirect', $redirect);
        $this->app->instance('auth', $auth);

        $seenInsideCallback = [];

        $this->app->terminating(function () use (&$seenInsideCallback): void {
            $seenInsideCallback = [
                'request' => $this->app->hasInstance('request'),
                'response' => $this->app->hasInstance('response'),
                'session' => $this->app->hasInstance('session'),
                'redirect' => $this->app->hasInstance('redirect'),
                'auth' => $this->app->hasInstance('auth'),
            ];
        });

        $this->app->terminate($request, $response);

        $this->assertSame([
            'request' => true,
            'response' => true,
            'session' => true,
            'redirect' => true,
            'auth' => true,
        ], $seenInsideCallback);

        $this->assertFalse($this->app->hasInstance('request'));
        $this->assertFalse($this->app->hasInstance('response'));
        $this->assertFalse($this->app->hasInstance('session'));
        $this->assertFalse($this->app->hasInstance('redirect'));
        $this->assertTrue($this->app->hasInstance('auth'));
    }

    public function testTerminateStillCleansRequestScopedServicesWithoutCallbacks(): void
    {
        $_SESSION = [];

        $request = new Request();
        $response = new Response('ok');

        $this->app->instance('request', $request);
        $this->app->instance('response', $response);
        $this->app->instance('session', new Session());
        $this->app->instance('redirect', new RedirectResponse());

        $this->app->terminate($request, $response);

        $this->assertFalse($this->app->hasInstance('request'));
        $this->assertFalse($this->app->hasInstance('response'));
        $this->assertFalse($this->app->hasInstance('session'));
        $this->assertFalse($this->app->hasInstance('redirect'));
    }
}
