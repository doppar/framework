<?php

namespace Tests\Unit;

use Phaseolies\Database\Migration\Seeder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SeederTest extends TestCase
{
    private $seeder;
    private $mocks = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seeder = new class($this) extends Seeder {
            private $testCase;

            public function __construct($testCase)
            {
                $this->testCase = $testCase;
            }

            public function runCall($seeders): void
            {
                $this->call($seeders);
            }
        };
    }

    public function app(string $class)
    {
        return $this->mocks[$class] ?? null;
    }

    public function testThrowsWhenClassNotFound()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Seeder class FooBarSeeder not found");

        $this->seeder->runCall('FooBarSeeder');
    }

    public function testThrowsWhenSeederHasNoRunMethod()
    {
        $mockSeeder = new class {};

        $this->mocks[get_class($mockSeeder)] = $mockSeeder;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Seeder " . get_class($mockSeeder) . " must have a run() method");

        $this->seeder->runCall(get_class($mockSeeder));
    }

    public function testCallDoesNotCrashWithEmptyArray()
    {
        $this->expectNotToPerformAssertions();
        $this->seeder->runCall([]);
    }
}
