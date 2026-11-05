<?php

namespace Tests\Unit\Storage;

use Phaseolies\Application;
use Phaseolies\DI\Container;
use Phaseolies\Launchers\FacadeLauncher;
use Phaseolies\Support\Storage\Contracts\StorageInterface;
use Phaseolies\Support\Storage\FileSystem;
use Phaseolies\Support\Storage\IFileSystem;
use Phaseolies\Support\Storage\LocalFileSystem;
use Phaseolies\Support\Storage\PublicFileSystem;
use Phaseolies\Support\Storage\StorageFileService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class StorageServiceTest extends TestCase
{
    private function bootedApplication(): Application
    {
        $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();

        (new ReflectionClass(Container::class))->getConstructor()->invoke($app);

        (new FacadeLauncher($app))->register();

        return $app;
    }

    public function testApplicationBindsTheStorageContractAndLegacyServiceNameToTheSameSingleton(): void
    {
        $app = $this->bootedApplication();

        $contractStorage = $app->get(StorageInterface::class);
        $legacyStorage = $app->get('storage');

        $this->assertInstanceOf(StorageInterface::class, $contractStorage);
        $this->assertInstanceOf(StorageFileService::class, $contractStorage);
        $this->assertSame($contractStorage, $legacyStorage);
    }

    public function testStorageSingletonIsStableAcrossRepeatedResolution(): void
    {
        $app = $this->bootedApplication();

        $first = $app->get('storage');
        $second = $app->get(StorageInterface::class);
        $third = $app->get('storage');

        $this->assertSame($first, $second);
        $this->assertSame($second, $third);
    }

    public function testStorageContractDeclaresStrictlyTypedDiskAndDiskPath(): void
    {
        $disk = new ReflectionMethod(StorageInterface::class, 'disk');
        $param = $disk->getParameters()[0];

        $this->assertSame('string', $param->getType()->getName());
        $this->assertFalse($param->getType()->allowsNull());
        $this->assertFalse($param->isOptional());
        $this->assertSame(IFileSystem::class, $disk->getReturnType()->getName());

        $path = new ReflectionMethod(StorageInterface::class, 'getDiskPath');

        $this->assertSame('string', $path->getParameters()[0]->getType()->getName());
        $this->assertSame('string', $path->getReturnType()->getName());
        $this->assertTrue($path->getReturnType()->allowsNull());
    }

    public function testDiskRejectsNullBecauseTheNameIsRequired(): void
    {
        $this->expectException(\TypeError::class);

        (new StorageFileService())->disk(null);
    }

    public function testFileSystemContractDeclaresTheSharedDiskApi(): void
    {
        $methods = array_map(
            fn(ReflectionMethod $m) => $m->getName(),
            (new ReflectionClass(IFileSystem::class))->getMethods()
        );
        sort($methods);

        $this->assertSame(['content', 'delete', 'get', 'storageBasePath', 'store'], $methods);
    }

    public function testFileSystemContractDoesNotExposeTheMisspelledMethod(): void
    {
        $this->assertFalse((new ReflectionClass(IFileSystem::class))->hasMethod('storeageBasePath'));
    }

    public function testFileSystemContractTypesContentAndBasePath(): void
    {
        $content = new ReflectionMethod(IFileSystem::class, 'content');

        $this->assertSame('string', $content->getParameters()[0]->getType()->getName());
        $this->assertSame('string', $content->getReturnType()->getName());
        $this->assertSame('string', (new ReflectionMethod(IFileSystem::class, 'storageBasePath'))->getReturnType()->getName());
    }

    #[DataProvider('diskImplementations')]
    public function testBuiltInDisksImplementTheContractAndInheritTheBasePathApi(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, IFileSystem::class));
        $this->assertTrue(is_subclass_of($class, FileSystem::class));

        // The base path lives once on FileSystem, so the disks cannot drift apart.
        $this->assertSame(FileSystem::class, (new ReflectionMethod($class, 'storageBasePath'))->getDeclaringClass()->getName());
        $this->assertSame(FileSystem::class, (new ReflectionMethod($class, 'storeageBasePath'))->getDeclaringClass()->getName());
    }

    public static function diskImplementations(): array
    {
        return [
            'local' => [LocalFileSystem::class],
            'public' => [PublicFileSystem::class],
        ];
    }
}
