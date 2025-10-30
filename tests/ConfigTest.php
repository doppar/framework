<?php

namespace Tests\Unit;

use Phaseolies\Config\Config;
use PHPUnit\Framework\TestCase;
use Tests\Support\MockContainer;
use Phaseolies\DI\Container;

class MinimalConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());

        $this->resetConfigState();
    }

    protected function tearDown(): void
    {
        $this->resetConfigState();
    }

    private function resetConfigState(): void
    {
        $reflection = new \ReflectionClass(Config::class);

        foreach (
            [
                "config" => [],
                "cacheFile" => null,
                "loadedFromCache" => false,
                "fileHashes" => [],
            ]
            as $propertyName => $value
        ) {
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);

                $property->setValue(null, $value);
            }
        }
    }

    public function testConfigGetSetBasic(): void
    {
        Config::set("test.key", "value");

        $this->assertEquals("value", Config::get("test.key"));
    }

    public function testConfigGetDefault(): void
    {
        $this->assertNull(Config::get("nonexistent.key"));
        $this->assertEquals(
            "default",
            Config::get("nonexistent.key", "default"),
        );
    }

    public function testConfigHas(): void
    {
        Config::set("test.key", "value");
        $this->assertTrue(Config::has("test.key"));
        $this->assertFalse(Config::has("nonexistent.key"));
    }

    public function testDotNotation(): void
    {
        Config::set("app.name", "TestApp");
        Config::set("database.connections.mysql.host", "localhost");

        $this->assertEquals("TestApp", Config::get("app.name"));
        $this->assertEquals(
            "localhost",
            Config::get("database.connections.mysql.host"),
        );
        $this->assertNull(Config::get("database.connections.mysql.port"));
    }
}
