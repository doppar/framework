<?php

namespace Tests\Unit\Application;

use ReflectionClass;
use Tests\Support\Kernel;
use Tests\Support\StrTest;
use Phaseolies\Application;
use Phaseolies\DI\Container;
use Phaseolies\Http\Request;
use Phaseolies\Config\Config;
use Phaseolies\Support\Router;
use Phaseolies\Console\Console;
use PHPUnit\Framework\TestCase;
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

final class ApplicationTest extends TestCase
{
    private Application $app;
    private string $tempBasePath;

    protected function setUp(): void
    {
        $container = new Container();
        $container->bind('config', fn() => Config::class);

        // Create a temporary directory structure
        $this->tempBasePath = sys_get_temp_dir() . '/phaseolies_app_test_' . uniqid();
        $this->createDirectoryStructure();

        // Create application instance without calling constructor
        $this->app = $this->createPartialMock(Application::class, [
            'registerCoreProviders',
            'bootCoreProviders',
            'withConfiguration',
            'withExceptionHandler'
        ]);

        // Set up the mock methods to do nothing
        $this->app->method('registerCoreProviders')->willReturnSelf();
        $this->app->method('bootCoreProviders')->willReturnSelf();
        $this->app->method('withConfiguration')->willReturnSelf();
        $this->app->method('withExceptionHandler')->willReturnSelf();

        // Now call the parent constructor manually without the problematic initialization
        $reflection = new ReflectionClass(Application::class);
        $constructor = $reflection->getConstructor();
        $constructor->setAccessible(true);
        $constructor->invoke($this->app);

        // Set base path for testing
        $this->app->withBasePath($this->tempBasePath);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempBasePath);
    }

    private function createDirectoryStructure(): void
    {
        $dirs = [
            '/config',
            '/bootstrap',
            '/database/migrations',
            '/public',
            '/storage',
            '/resources',
            '/resources/lang',
            '/app',
        ];

        foreach ($dirs as $dir) {
            mkdir($this->tempBasePath . $dir, 0777, true);
        }

        // Create minimal config file
        file_put_contents($this->tempBasePath . '/config/app.php', "<?php return [
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
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function getProtectedProperty($object, $property)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    private function callProtectedMethod($object, $method, $args = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    public function testApplicationVersionConstant(): void
    {
        $this->assertSame('2.9.6-beta.18', Application::VERSION);
    }

    public function testApplicationIsContainerInstance(): void
    {
        $this->assertInstanceOf(\Phaseolies\DI\Container::class, $this->app);
    }

    public function testPathMethodsReturnCorrectPaths(): void
    {
        $this->assertStringEndsWith('/resources/views', StrTest::urlHarmonize($this->app->resourcesPath('views')));
        $this->assertStringEndsWith('/bootstrap/cache', StrTest::urlHarmonize($this->app->bootstrapPath('cache')));
        $this->assertStringEndsWith('/database/migrations', StrTest::urlHarmonize($this->app->databasePath('migrations')));
        $this->assertStringEndsWith('/public/assets', StrTest::urlHarmonize($this->app->publicPath('assets')));
        $this->assertStringEndsWith('/storage/logs', StrTest::urlHarmonize($this->app->storagePath('logs')));
        $this->assertStringEndsWith('/config/app.php', StrTest::urlHarmonize($this->app->configPath('app.php')));
        $this->assertStringEndsWith('/lang/en', StrTest::urlHarmonize($this->app->langPath('en')));
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
        $this->assertContains(\Phaseolies\Providers\EnvServiceProvider::class, $providers);
        $this->assertContains(\Phaseolies\Providers\RouteServiceProvider::class, $providers);
        $this->assertContains(\Phaseolies\Providers\LanguageServiceProvider::class, $providers);
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
}
