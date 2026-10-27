<?php

namespace Tests\Unit\Storage;

use Phaseolies\Application;
use Phaseolies\DI\Container;
use Phaseolies\Launchers\FacadeLauncher;
use Phaseolies\Support\Storage\Contracts\StorageInterface;
use Phaseolies\Support\Storage\StorageFileService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class StorageServiceTest extends TestCase
{
    public function testApplicationBindsTheStorageContractAndLegacyServiceNameToTheSameSingleton(): void
    {
        $app = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $containerConstructor = (new ReflectionClass(Container::class))->getConstructor();
        $containerConstructor->invoke($app);

        (new FacadeLauncher($app))->register();

        $contractStorage = $app->get(StorageInterface::class);
        $legacyStorage = $app->get('storage');

        $this->assertInstanceOf(StorageInterface::class, $contractStorage);
        $this->assertInstanceOf(StorageFileService::class, $contractStorage);
        $this->assertSame($contractStorage, $legacyStorage);
    }
}
