<?php

namespace Tests\Unit\Providers;

use Phaseolies\Application;
use Phaseolies\Providers\ServiceProvider;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Migration\Migration;

// Mock ServiceProvider for testing
class TestServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Test implementation
    }

    public function boot()
    {
        // Test implementation
    }
}

// Mock Migration for testing
class TestMigration extends Migration
{
    public function up(): void
    {
        // Test implementation
    }

    public function down(): void
    {
        // Test implementation
    }
}

class ServiceProviderTest extends TestCase
{
    private $app;
    private $provider;

    protected function setUp(): void
    {
        $this->app = $this->createMock(Application::class);
        $this->provider = new TestServiceProvider($this->app);
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(ServiceProvider::class, $this->provider);

        // Test that app is properly set via reflection
        $reflection = new \ReflectionClass($this->provider);
        $appProperty = $reflection->getProperty('app');
        $appProperty->setAccessible(true);

        $this->assertSame($this->app, $appProperty->getValue($this->provider));
    }

    public function testLoadRoutesWithExistingFile()
    {
        $testFilePath = sys_get_temp_dir() . '/test_routes.php';
        file_put_contents($testFilePath, '<?php $GLOBALS["routes_loaded"] = true; ?>');

        $this->provider->loadRoutes($testFilePath);

        $this->assertTrue($GLOBALS['routes_loaded']);

        unset($GLOBALS['routes_loaded']);
        unlink($testFilePath);
    }

    public function testLoadRoutesWithNonExistingFile()
    {
        $nonExistentPath = '/non/existent/path/routes.php';

        // Should not throw any exception
        $this->provider->loadRoutes($nonExistentPath);

        $this->assertTrue(true); // No exception thrown
    }

    public function testMergeConfigWithoutConfigService()
    {
        $this->app->method('has')->with('config')->willReturn(false);

        // Should not throw any exception when config service doesn't exist
        $this->provider->mergeConfig('/some/path.php', 'test.key');
    }

    public function testPublishesViews()
    {
        $paths = ['path1', 'path2'];

        $provider = new class($this->app) extends TestServiceProvider {
            public function testPublishesViews(array $paths, string $group = 'views')
            {
                $this->publishesViews($paths, $group);
            }
        };

        $reflection = new \ReflectionClass($provider);
        $publishGroupsProperty = $reflection->getProperty('publishGroups');
        $publishGroupsProperty->setAccessible(true);

        $provider->testPublishesViews($paths, 'custom-views');

        $publishGroups = $publishGroupsProperty->getValue($provider);
        $this->assertArrayHasKey('custom-views', $publishGroups);
        $this->assertEquals($paths, $publishGroups['custom-views']);
    }

    public function testLoadViewsWithoutViewService()
    {
        $this->app->method('has')->with('view')->willReturn(false);

        $this->provider->loadViews('/test/path', 'test-namespace');
    }

    public function testLoadTranslationsWithoutTranslatorService()
    {
        $this->app->method('has')->with('translator')->willReturn(false);

        $this->provider->loadTranslations('/test/path', 'test-namespace');
    }

    public function testPublishesWithGroups()
    {
        $paths = ['path1', 'path2'];

        $reflection = new \ReflectionClass($this->provider);
        $publishesProperty = $reflection->getProperty('publishes');
        $publishGroupsProperty = $reflection->getProperty('publishGroups');
        $publishesProperty->setAccessible(true);
        $publishGroupsProperty->setAccessible(true);

        $this->provider->publishes($paths, 'test-group');

        $publishGroups = $publishGroupsProperty->getValue($this->provider);
        $this->assertArrayHasKey('test-group', $publishGroups);
        $this->assertEquals($paths, $publishGroups['test-group']);
    }

    public function testPublishesWithoutGroups()
    {
        $paths = ['path1', 'path2'];

        $reflection = new \ReflectionClass($this->provider);
        $publishesProperty = $reflection->getProperty('publishes');
        $publishesProperty->setAccessible(true);

        $this->provider->publishes($paths);

        $publishes = $publishesProperty->getValue($this->provider);
        $this->assertEquals($paths, $publishes);
    }

    public function testPublishesWithMultipleGroups()
    {
        $paths = ['path1', 'path2'];
        $groups = ['group1', 'group2'];

        $reflection = new \ReflectionClass($this->provider);
        $publishGroupsProperty = $reflection->getProperty('publishGroups');
        $publishGroupsProperty->setAccessible(true);

        $this->provider->publishes($paths, $groups);

        $publishGroups = $publishGroupsProperty->getValue($this->provider);
        $this->assertArrayHasKey('group1', $publishGroups);
        $this->assertArrayHasKey('group2', $publishGroups);
        $this->assertEquals($paths, $publishGroups['group1']);
        $this->assertEquals($paths, $publishGroups['group2']);
    }

    public function testPublishesMigrations()
    {
        $paths = ['migration/path1', 'migration/path2'];

        $reflection = new \ReflectionClass($this->provider);
        $publishGroupsProperty = $reflection->getProperty('publishGroups');
        $publishGroupsProperty->setAccessible(true);

        $this->provider->publishesMigrations($paths, 'custom-migrations');

        $publishGroups = $publishGroupsProperty->getValue($this->provider);
        $this->assertArrayHasKey('custom-migrations', $publishGroups);
        $this->assertEquals($paths, $publishGroups['custom-migrations']);
    }

    public function testPathsToPublishWithProviderAndGroup()
    {
        // Set up test data
        $reflection = new \ReflectionClass($this->provider);
        $publishesProperty = $reflection->getProperty('publishes');
        $publishGroupsProperty = $reflection->getProperty('publishGroups');
        $publishesProperty->setAccessible(true);
        $publishGroupsProperty->setAccessible(true);

        $publishesProperty->setValue($this->provider, ['provider1' => ['path1']]);
        $publishGroupsProperty->setValue($this->provider, ['group1' => ['path2']]);

        $paths = $this->provider->pathsToPublish('provider1', 'group1');

        $this->assertIsArray($paths);
    }

    public function testPathsToPublishWithGroupOnly()
    {
        $reflection = new \ReflectionClass($this->provider);
        $publishGroupsProperty = $reflection->getProperty('publishGroups');
        $publishGroupsProperty->setAccessible(true);
        $publishGroupsProperty->setValue($this->provider, ['test-group' => ['group-path']]);

        $paths = $this->provider->pathsToPublish(null, 'test-group');
        $this->assertEquals(['group-path'], $paths);
    }

    public function testPathsToPublishWithProviderOnly()
    {
        $reflection = new \ReflectionClass($this->provider);
        $publishesProperty = $reflection->getProperty('publishes');
        $publishesProperty->setAccessible(true);
        $publishesProperty->setValue($this->provider, ['test-provider' => ['provider-path']]);

        $paths = $this->provider->pathsToPublish('test-provider', null);
        $this->assertEquals(['provider-path'], $paths);
    }

    public function testPathsToPublishWithoutArguments()
    {
        $reflection = new \ReflectionClass($this->provider);
        $publishesProperty = $reflection->getProperty('publishes');
        $publishGroupsProperty = $reflection->getProperty('publishGroups');
        $publishesProperty->setAccessible(true);
        $publishGroupsProperty->setAccessible(true);

        $publishesProperty->setValue($this->provider, ['p1' => 'v1']);
        $publishGroupsProperty->setValue($this->provider, ['g1' => 'v2']);

        $paths = $this->provider->pathsToPublish();
        $this->assertEquals(['p1' => 'v1', 'g1' => 'v2'], $paths);
    }

    public function testCommandsNotInConsole()
    {
        $this->app->method('runningInConsole')->willReturn(false);

        $this->provider->commands(['command1', 'command2']);

        $this->assertTrue(true);
    }

    public function testAbstractMethodsExist()
    {
        $this->assertTrue(method_exists($this->provider, 'register'));
        $this->assertTrue(method_exists($this->provider, 'boot'));
    }
}
