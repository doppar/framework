<?php

namespace Tests\Unit\Console;

require_once __DIR__ . '/Support/CommandTestEnvironment.php';

use Phaseolies\Application;
use Phaseolies\Console\Commands\Cron\CronDaemonCommand;
use Phaseolies\Console\Commands\Cron\CronFinishCommand;
use Phaseolies\Console\Commands\DeleteCronLockFile;
use Phaseolies\Console\Commands\KeyGenerateCommand;
use Phaseolies\Console\Commands\MakeAuthCommand;
use Phaseolies\Console\Commands\MakeAuthorizerCommand;
use Phaseolies\Console\Commands\MakeConsoleCommand;
use Phaseolies\Console\Commands\MakeControllerCommand;
use Phaseolies\Console\Commands\MakeHookCommand;
use Phaseolies\Console\Commands\MakeMailCommand;
use Phaseolies\Console\Commands\MakeMiddlewareCommand;
use Phaseolies\Console\Commands\MakeModelCommand;
use Phaseolies\Console\Commands\MakeProviderCommand;
use Phaseolies\Console\Commands\MakeRequestCommand;
use Phaseolies\Console\Commands\MakeRuleCommand;
use Phaseolies\Console\Commands\MakeWatcherCommand;
use Phaseolies\Console\Commands\Migrations\AddColumnMigrationCommand;
use Phaseolies\Console\Commands\Migrations\CreateMigrationCommand;
use Phaseolies\Console\Commands\Migrations\CreateSeedCommand;
use Phaseolies\Console\Commands\PaginationPublishCommand;
use Phaseolies\Console\Commands\Server\ServerStartCommand;
use Phaseolies\Console\Commands\Server\ServerStopCommand;
use Phaseolies\Console\Commands\SetCreatablePropertyCommand;
use Phaseolies\Console\Commands\StorageLinkCommand;
use Phaseolies\Console\Commands\StorageUnlinkCommand;
use Phaseolies\Console\Commands\Tests\UnitTestCommand;
use Phaseolies\Console\Commands\VendorPublishCommand;
use Phaseolies\Console\Commands\ViewCacheCommand;
use Phaseolies\Console\Commands\ViewClearCommand;
use Phaseolies\Database\Migration\MigrationCreator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Console\Support\CommandTestEnvironment as Env;
use Tests\Unit\Console\Support\FakeDatabaseInspector;
use Tests\Unit\Console\Support\FakeSessionStore;
use Tests\Unit\Console\Support\InteractsWithFakeCommandIO;

#[AllowMockObjectsWithoutExpectations]
class CommandBehaviorCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Env::reset();
        Env::$appInstance = $this->createMock(Application::class);
        Env::bind('session', new FakeSessionStore());
        Env::bind('db', new FakeDatabaseInspector());
    }

    protected function tearDown(): void
    {
        Env::cleanup();

        parent::tearDown();
    }

    public function testMakeConsoleCommandCreatesKebabCaseCommandClass(): void
    {
        $command = new class extends MakeConsoleCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $command->fakeArguments['name'] = 'ReportsSyncCommand';

        $result = $command->handle();
        $file = Env::path('app/Schedule/Commands/ReportsSyncCommand.php');

        $this->assertSame(0, $result);
        $this->assertFileExists($file);
        $this->assertStringContainsString("protected \$name = 'doppar_demo:reports-sync';", (string) file_get_contents($file));
        $this->assertContains('Command created successfully', $command->capturedSuccesses);
    }

    public function testMakeAuthCommandGeneratesExpectedFilesAndRoutes(): void
    {
        $command = new class extends MakeAuthCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $routesPath = Env::path('routes/web.php');
        mkdir(dirname($routesPath), 0755, true);
        file_put_contents($routesPath, "<?php\n");

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertFileExists(Env::path('app/Http/Controllers/Auth/LoginController.php'));
        $this->assertFileExists(Env::path('resources/views/auth/login.odo.php'));
        $this->assertFileExists(Env::path('resources/views/layouts/app.odo.php'));
        $this->assertSame("<?php\n", (string) file_get_contents($routesPath));
        $this->assertContains('Authentication scaffolding generated successfully', $command->capturedSuccesses);
    }

    public function testMakeControllerCommandValidatesConflictingFlagsAndBuildsPlaceholders(): void
    {
        $command = new MakeControllerCommand();

        $this->assertSame(
            'A controller cannot be both invokable and bundle/api/complete.',
            $this->invokeMethod($command, 'validateControllerOptions', [true, true, false, false])
        );

        $stub = "namespace {{ namespace }};\nclass {{ class }} {}\nview {{ routeView }}\npath {{ controllerPath }}";
        $content = $this->invokeMethod($command, 'replacePlaceholders', [
            $stub,
            'App\\Http\\Controllers\\Admin',
            'UserController',
            'admin/users',
        ]);

        $this->assertStringContainsString('namespace App\\Http\\Controllers\\Admin;', $content);
        $this->assertStringContainsString('class UserController', $content);
        $this->assertStringContainsString('view admin.users', $content);
        $this->assertStringContainsString('path app/Http/Controllers/Admin/UserController.php', $content);
    }

    public function testCommandHelpersNormalizeGeneratedNamesAndRelativePaths(): void
    {
        $command = new class extends MakeProviderCommand
        {
            use InteractsWithFakeCommandIO;
        };

        [$normalized, $parts, $className] = $this->invokeMethod($command, 'splitGeneratedName', [
            '\\Admin//Hello\\TestProvider/',
        ]);

        $this->assertSame('Admin/Hello/TestProvider', $normalized);
        $this->assertSame(['Admin', 'Hello'], $parts);
        $this->assertSame('TestProvider', $className);
        $this->assertSame(
            'app/Providers/Hello/TestProvider.php',
            $this->invokeMethod($command, 'relativePath', [Env::path('app/Providers/Hello/TestProvider.php')])
        );
    }

    public function testMakeProviderCommandAcceptsBackslashesAndOutputsRelativePathWithoutLeadingSlash(): void
    {
        $command = new class extends MakeProviderCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $command->fakeArguments['name'] = 'Hello\\TestProvider';

        $result = $command->handle();
        $file = Env::path('app/Providers/Hello/TestProvider.php');
        $contents = (string) file_get_contents($file);
        $lines = array_map(static fn(array $line): string => $line[0], $command->capturedLines);

        $this->assertSame(0, $result);
        $this->assertFileExists($file);
        $this->assertStringContainsString('namespace App\\Providers\\Hello;', $contents);
        $this->assertStringContainsString('class TestProvider extends ServiceProvider', $contents);
        $this->assertContains(
            '<fg=yellow>📦 File:</> <fg=white>app/Providers/Hello/TestProvider.php</>',
            $lines
        );
    }

    public function testMakeControllerCommandNormalizesBackslashesForResolvedPathsAndLayouts(): void
    {
        $command = new class extends MakeControllerCommand
        {
            use InteractsWithFakeCommandIO;

            protected function getLayoutStub(string $stubName): string
            {
                return '<section>layout</section>';
            }
        };

        [$namespace, $filePath, $className] = $this->invokeMethod($command, 'resolveNamespacesAndPaths', [
            'Admin\\UserController',
            false,
        ]);

        $this->invokeMethod($command, 'generateLayout', [
            $namespace,
            $className,
            'admin\\users',
        ]);

        $this->assertSame('App\\Http\\Controllers\\Admin', $namespace);
        $this->assertSame(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, Env::path('app/Http/Controllers/Admin/UserController.php')),
            $filePath
        );
        $this->assertSame('UserController', $className);
        $this->assertFileExists(Env::path('resources/views/admin/users/default.odo.php'));
    }

    public function testMakeAuthorizerCommandGeneratesModelAwarePolicyMethods(): void
    {
        $command = new MakeAuthorizerCommand();

        $generic = $this->invokeMethod($command, 'generateAuthorizerContent', [
            'App\\Authorizers',
            'OrderAuthorizer',
            null,
        ]);
        $modelAware = $this->invokeMethod($command, 'generateAuthorizerContent', [
            'App\\Authorizers',
            'PostAuthorizer',
            'Post',
        ]);

        $this->assertStringContainsString('performAction(User $user)', $generic);
        $this->assertStringContainsString('use App\Models\Post;', $modelAware);
        $this->assertStringContainsString('public function update(User $user, Post $post): bool', $modelAware);
    }

    #[DataProvider('templateGeneratorProvider')]
    public function testGeneratorCommandsProduceExpectedTemplateSnippets(
        object $command,
        string $method,
        array $arguments,
        array $expectedSnippets
    ): void {
        $content = $this->invokeMethod($command, $method, $arguments);

        foreach ($expectedSnippets as $snippet) {
            $this->assertStringContainsString($snippet, $content);
        }
    }

    public static function templateGeneratorProvider(): array
    {
        $creator = new class extends MigrationCreator
        {
            public function __construct()
            {
            }
        };

        return [
            'provider' => [
                new MakeProviderCommand(),
                'generateProviderContent',
                ['App\\Providers', 'AppServiceProvider'],
                ['class AppServiceProvider extends ServiceProvider', 'public function register(): void'],
            ],
            'rule' => [
                new MakeRuleCommand(),
                'generateRuleContent',
                ['App\\Http\\Validations\\Rules', 'EmailRule'],
                ['class EmailRule implements RuleInterface', 'public function passes(string $field, mixed $value, array $input): bool'],
            ],
            'middleware' => [
                new MakeMiddlewareCommand(),
                'generateMiddlewareContent',
                ['App\\Http\\Middleware', 'EnsureActiveUser'],
                ['class EnsureActiveUser implements Middleware', 'public function __invoke(Request $request, Closure $next): Response'],
            ],
            'hook' => [
                new MakeHookCommand(),
                'generateHookContent',
                ['App\\Hooks', 'BeforeSaveHook'],
                ['class BeforeSaveHook', 'public function handle(Model $model): void'],
            ],
            'watcher' => [
                new MakeWatcherCommand(),
                'generateWatcherContent',
                ['App\\Watchers', 'StatusWatcher'],
                ['class StatusWatcher', 'public function handle(mixed $old, mixed $new, Model $model): void'],
            ],
            'mail' => [
                new MakeMailCommand(),
                'generateMailContent',
                ['App\\Mail', 'WelcomeMail'],
                ['class WelcomeMail extends Mailable', 'public function subject(): Subject', 'public function content(): Content'],
            ],
            'request' => [
                new MakeRequestCommand(),
                'generateRequestContent',
                ['App\\Http\\Validations', 'StoreUserRequest'],
                ['class StoreUserRequest extends FormRequest', 'public function rules(): array'],
            ],
            'model' => [
                new MakeModelCommand($creator),
                'generateModelContent',
                ['App\\Models', 'Invoice'],
                ['class Invoice extends Model'],
            ],
            'seed' => [
                new CreateSeedCommand(),
                'generateSeedContent',
                ['UserSeeder'],
                ['class UserSeeder extends Seeder', 'public function run(): void'],
            ],
        ];
    }

    public function testMigrationCommandsPassExpectedArgumentsToCreator(): void
    {
        $creator = $this->createMock(MigrationCreator::class);
        $calls = [];
        $creator->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (...$arguments) use (&$calls) {
                $calls[] = $arguments;

                return 'migration-' . count($calls) . '.php';
            });

        $createMigration = new class($creator) extends CreateMigrationCommand
        {
            use InteractsWithFakeCommandIO;
        };
        $createMigration->fakeArguments['name'] = 'create_users_table';
        $createMigration->fakeOptions = ['table' => null, 'create' => 'users'];

        $addColumn = new class($creator) extends AddColumnMigrationCommand
        {
            use InteractsWithFakeCommandIO;
        };
        $addColumn->fakeArguments['name'] = 'add_status_to_orders';
        $addColumn->fakeOptions = [
            'table' => 'orders',
            'create' => false,
            'column' => 'status',
            'type' => 'string',
            'after' => 'name',
        ];

        $this->assertSame(0, $createMigration->handle());
        $this->assertSame(0, $addColumn->handle());
        $this->assertSame(
            [
                ['create_users_table', Env::path('database/migrations'), 'users', true],
                ['add_status_to_orders', Env::path('database/migrations'), 'orders', false, 'status', 'string', 'name'],
            ],
            $calls
        );
    }

    public function testPaginationPublishCommandCreatesFilesAndSkipsDuplicates(): void
    {
        $command = new class extends PaginationPublishCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $firstRun = $command->handle();
        $secondRun = $command->handle();

        $paginationDir = Env::path('resources/views/vendor/pagination');

        $this->assertSame(0, $firstRun);
        $this->assertSame(0, $secondRun);
        $this->assertFileExists($paginationDir . '/jump.odo.php');
        $this->assertFileExists($paginationDir . '/number.odo.php');
        $this->assertContains('All pagination views already exist.', $command->capturedInfos);
    }

    public function testKeyGenerateCommandUpdatesExistingEnvFile(): void
    {
        $command = new class extends KeyGenerateCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $envFile = Env::path('.env');
        file_put_contents($envFile, "APP_NAME=Doppar\nAPP_KEY=base64:old-key\n");

        $result = $command->handle();
        $contents = (string) file_get_contents($envFile);

        $this->assertSame(0, $result);
        $this->assertMatchesRegularExpression('/APP_KEY=base64:[A-Za-z0-9+\/=]+/', $contents);
        $this->assertContains('Application key set successfully', $command->capturedSuccesses);
    }

    public function testDeleteCronLockFileRemovesNestedScheduleArtifacts(): void
    {
        $command = new class extends DeleteCronLockFile
        {
            use InteractsWithFakeCommandIO;
        };

        $nestedFile = Env::path('storage/schedule/nested/demo.lock');
        mkdir(dirname($nestedFile), 0755, true);
        file_put_contents($nestedFile, 'lock');

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertDirectoryExists(Env::path('storage/schedule'));
        $this->assertFileDoesNotExist($nestedFile);
    }

    public function testStorageUnlinkCommandRemovesExistingSymlink(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('symlink is not available in this environment.');
        }

        $command = new class extends StorageUnlinkCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $target = Env::path('storage/app/public');
        $link = Env::path('public/storage');

        mkdir($target, 0755, true);
        mkdir(dirname($link), 0755, true);
        symlink($target, $link);

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertFalse(is_link($link));
        $this->assertContains('Symbolic link removed successfully', $command->capturedSuccesses);
    }

    public function testStorageLinkCommandCreatesConfiguredPublicLink(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('symlink is not available in this environment.');
        }

        $command = new class extends StorageLinkCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $target = Env::path('storage/app/public');
        $link = Env::path('public/storage');
        $marker = $target . '/avatar.txt';

        Env::$config['filesystem.links'] = [$link => $target];

        mkdir($target, 0755, true);
        mkdir(dirname($link), 0755, true);
        file_put_contents($marker, 'ok');

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertTrue(is_link($link) || is_dir($link));
        $this->assertFileExists($link . '/avatar.txt');
    }

    public function testStorageLinkCommandBuildsWindowsJunctionCommandForDirectories(): void
    {
        $command = new class extends StorageLinkCommand
        {
            public function exposeBuildWindowsLinkCommand(string $target, string $link): array
            {
                return $this->buildWindowsLinkCommand($target, $link);
            }
        };

        $target = Env::path('storage/app/public');
        $link = Env::path('public/storage');

        mkdir($target, 0755, true);

        $this->assertSame(
            ['cmd', '/c', 'mklink', '/J', $link, $target],
            $command->exposeBuildWindowsLinkCommand($target, $link)
        );
    }

    public function testStorageUnlinkCommandFallsBackToRemovingDirectoryLink(): void
    {
        $command = new class extends StorageUnlinkCommand
        {
            use InteractsWithFakeCommandIO;

            protected function linkExists(string $linkPath): bool
            {
                return true;
            }

            protected function removeLink(string $linkPath): bool
            {
                return true;
            }
        };

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertContains('Symbolic link removed successfully', $command->capturedSuccesses);
    }

    public function testSetCreatablePropertyCommandFiltersFrameworkColumns(): void
    {
        $command = new class extends SetCreatablePropertyCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $db = Env::app('db');
        $db->columns['products'] = ['id', 'name', 'price', 'created_at', 'updated_at'];
        $command->fakeArguments['table'] = 'products';

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertSame(['protected $creatable = ["name","price"]'], $command->capturedInfos);
    }

    public function testViewClearCommandRemovesCompiledViewFiles(): void
    {
        $command = new class extends ViewClearCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $viewCacheDir = Env::path('storage/framework/views');
        mkdir($viewCacheDir, 0755, true);
        file_put_contents($viewCacheDir . '/one.php', 'a');
        file_put_contents($viewCacheDir . '/two.php', 'b');

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertFileDoesNotExist($viewCacheDir . '/one.php');
        $this->assertFileDoesNotExist($viewCacheDir . '/two.php');
        $this->assertContains('Compiled views cleared successfully', $command->capturedSuccesses);
    }

    public function testViewCacheCommandDiscoversViewsAndCreatesCacheDirectory(): void
    {
        $command = new class extends ViewCacheCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $viewDir = Env::path('resources/views/admin');
        mkdir($viewDir, 0755, true);
        file_put_contents($viewDir . '/dashboard.odo.php', '<h1>Dashboard</h1>');

        $cacheDir = Env::path('storage/framework/views');
        $this->invokeMethod($command, 'ensureDirectoriesExist', [Env::path('resources/views'), $cacheDir]);
        $files = $this->invokeMethod($command, 'getAllViewFiles', [Env::path('resources/views')]);

        $this->assertDirectoryExists($cacheDir);
        $this->assertSame([realpath($viewDir . '/dashboard.odo.php')], $files);
    }

    public function testCronDaemonCommandFormatsRuntimeDisplayHelpers(): void
    {
        $command = new class extends CronDaemonCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $this->assertSame('1h 1m 1s', $this->invokeMethod($command, 'formatUptime', [3661]));
        $this->assertSame('2 KB', $this->invokeMethod($command, 'formatBytes', [2048]));
        $this->assertSame(1, $this->invokeMethod($command, 'invalidAction', ['pause']));
        $this->assertContains('Invalid action: pause', $command->capturedErrors);
    }

    public function testCronFinishCommandReleasesMatchingLockAndReportsFailure(): void
    {
        $command = new class extends CronFinishCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $hash = md5('finish-test');
        $pidFile = sys_get_temp_dir() . "/doppar_cron_lock_{$hash}.pid";
        $lockFile = sys_get_temp_dir() . "/doppar_cron_lock_{$hash}";

        file_put_contents($pidFile, json_encode(['finish_id' => 'abc-123']));
        file_put_contents($lockFile, 'locked');

        $command->fakeArguments = [
            'finish_id' => 'abc-123',
            'release_lock' => '1',
            'exit_code' => '7',
        ];

        try {
            $result = $command->handle();
        } finally {
            @unlink($pidFile);
            @unlink($lockFile);
        }

        $this->assertSame(7, $result);
        $this->assertContains('Cron task failed with exit code: 7', Env::$errors);
        $this->assertFileDoesNotExist($pidFile);
        $this->assertFileDoesNotExist($lockFile);
    }

    public function testServerStartCommandValidatesPortsThroughPrivateHelper(): void
    {
        $command = new class extends ServerStartCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $this->assertFalse($this->invokeMethod($command, 'isPortOk', [null]));
        $this->assertTrue($this->invokeMethod($command, 'isPortOk', [8080]));
    }

    public function testServerStopCommandRejectsInvalidPort(): void
    {
        $command = new class extends ServerStopCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $command->fakeArguments['port'] = 'invalid-port';

        $result = $command->handle();

        $this->assertSame(1, $result);
        $this->assertContains('Port must be a valid integer', $command->capturedErrors);
    }

    public function testUnitTestCommandBuildsExpectedPhpUnitCommandAndFormatsSummary(): void
    {
        $command = new class extends UnitTestCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $phpUnitCommand = $this->invokeMethod($command, 'buildPHPUnitCommand', [
            'tests/Unit/ExampleTest.php',
            'it_works',
            true,
        ]);

        $this->invokeMethod($command, 'displaySummary', [
            'OK (3 tests, 5 assertions)',
            0.123,
            0,
        ]);

        $this->assertSame([
            Env::path('vendor/bin/phpunit'),
            Env::path('tests/Unit/ExampleTest.php'),
            '--filter',
            'it_works',
            '--testdox',
            '--disallow-test-output',
            '--colors=never',
        ], $phpUnitCommand);
        $this->assertContains('Test suite completed successfully with 5 assertion(s)', $command->capturedSuccesses);
    }

    public function testVendorPublishCommandCopiesFilesAndSkipsExistingTargets(): void
    {
        $command = new class extends VendorPublishCommand
        {
            use InteractsWithFakeCommandIO;
        };

        $sourceDir = Env::path('vendor/package/config');
        $targetDir = Env::path('published/config');
        $sourceFile = $sourceDir . '/demo.php';
        $targetFile = $targetDir . '/demo.php';

        mkdir($sourceDir, 0755, true);
        file_put_contents($sourceFile, '<?php return [];');

        $this->invokeMethod($command, 'publishDirectory', [$sourceDir, $targetDir, false]);
        $this->invokeMethod($command, 'publishFile', [$sourceFile, $targetFile, false]);

        $this->assertFileExists($targetFile);
        $this->assertContains('Skipping: File already exists at ' . $targetFile, $command->capturedWarnings);
    }

    private function invokeMethod(object $instance, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($instance, $method);

        return $reflection->invokeArgs($instance, $arguments);
    }
}
