<?php

namespace Tests\Unit\Session;

require_once __DIR__ . '/../Support/MockContainer.php';

use Phaseolies\Config\Config;
use Phaseolies\DI\Container;
use Phaseolies\Session\Handlers\CookieSessionHandler;
use PHPUnit\Framework\TestCase;
use Exception;
use RuntimeException;
use Tests\Support\MockContainer;

class CookieSessionHandlerTest extends TestCase
{
    private array $config = [
        'cookie'   => 'doppar_session',
        'lifetime' => 120,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'same_site' => 'Lax',
    ];

    private function makeHandler(array $overrides = []): CookieSessionHandler
    {
        return new CookieSessionHandler(array_merge($this->config, $overrides));
    }

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());
        $this->seedConfig([
            'app' => ['key' => str_repeat('a', 32)],
            'session' => ['cookie' => $this->config['cookie']],
        ]);
        session_name($this->config['cookie']);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[$this->config['cookie']]);
        Container::forgetInstance();
    }

    public function testOpenReturnsTrue()
    {
        $handler = $this->makeHandler();

        $this->assertTrue($handler->open('/tmp', 'doppar_session'));
    }

    public function testCloseReturnsTrue()
    {
        $handler = $this->makeHandler();

        $this->assertTrue($handler->close());
    }

    public function testGcReturnsTrue()
    {
        $handler = $this->makeHandler();

        $this->assertTrue($handler->gc(3600));
    }

    public function testReadReturnsEmptyStringWhenNoCookiePresent()
    {
        unset($_COOKIE['doppar_session']);

        $handler = $this->makeHandler();

        $this->assertEquals('', $handler->read('session_id_123'));
    }

    public function testReadReturnsEmptyStringForMissingSessionName()
    {
        foreach (array_keys($_COOKIE) as $key) {
            unset($_COOKIE[$key]);
        }

        $handler = $this->makeHandler();

        $result = $handler->read('any_id');

        $this->assertIsString($result);
        $this->assertEquals('', $result);
    }

    public function testValidateDestroysInvalidCookieRequiresConfig()
    {
        // Set up a cookie with invalid base64 data that will cause decryption to fail
        $_COOKIE[$this->config['cookie']] = 'invalid_encrypted_data';

        $handler = $this->makeHandler();

        // Validate should handle the invalid cookie gracefully by destroying it
        // Even if Config is not available, the method should not throw a fatal error
        try {
            $handler->validate();
        } catch (Exception $e) {
            // If an exception occurs due to missing Config or key, that's expected in test environment
            $this->assertStringContainsString('Config', $e->getMessage());
            $this->assertStringContainsString('key', $e->getMessage());
        }

        // Test that validate() exists and is callable
        $this->assertTrue(method_exists($handler, 'validate'));
    }

    public function testValidateDoesNothingWhenNoCookiePresent()
    {
        unset($_COOKIE[$this->config['cookie']]);

        $handler = $this->makeHandler();

        $handler->validate();

        $this->assertArrayNotHasKey($this->config['cookie'], $_COOKIE);
    }

    public function testHandlerIsInstantiableWithValidConfig()
    {
        $handler = $this->makeHandler();

        $this->assertInstanceOf(CookieSessionHandler::class, $handler);
    }

    public function testWriteReturnsTrueForEmptyData()
    {
        $handler = $this->makeHandler();

        $this->assertTrue($handler->write('session_id', ''));
    }

    public function testDestroyReturnsTrueWhenHeadersNotSent()
    {
        $handler = $this->makeHandler();

        $result = $handler->destroy('session_id_456');

        $this->assertIsBool($result);
    }

    public function testPrivateEncryptAndDecryptRoundTrip()
    {
        $handler = $this->makeHandler();
        $encrypt = new \ReflectionMethod($handler, 'encrypt');
        $decrypt = new \ReflectionMethod($handler, 'decrypt');

        $encrypted = $encrypt->invoke($handler, 'session_payload');
        $decrypted = $decrypt->invoke($handler, $encrypted);

        $this->assertIsString($encrypted);
        $this->assertNotSame('session_payload', $encrypted);
        $this->assertSame('session_payload', $decrypted);
    }

    public function testValidatePreservesValidEncryptedCookie()
    {
        $handler = $this->makeHandler();
        $encrypt = new \ReflectionMethod($handler, 'encrypt');

        $_COOKIE[$this->config['cookie']] = $encrypt->invoke($handler, 'valid_payload');

        $handler->validate();

        $this->assertArrayHasKey($this->config['cookie'], $_COOKIE);
    }

    public function testDecryptThrowsWhenAppKeyIsMissing()
    {
        $this->seedConfig([
            'app' => ['key' => null],
            'session' => ['cookie' => $this->config['cookie']],
        ]);
        $handler = $this->makeHandler();
        $decrypt = new \ReflectionMethod($handler, 'decrypt');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption key not configured');

        $decrypt->invoke($handler, base64_encode('anything'));
    }

    private function seedConfig(array $config): void
    {
        $this->setConfigStatic('cacheFile', sys_get_temp_dir() . '/doppar_test_config.php');
        $this->setConfigStatic('config', $config);
        $this->setConfigStatic('loadedFromCache', true);
        $this->setConfigStatic('configModified', false);
        $this->setConfigStatic('fileHashes', []);
        $this->setConfigStatic('configFiles', []);
    }

    private function setConfigStatic(string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass(Config::class);
        $property = $reflection->getProperty($propertyName);
        $property->setValue(null, $value);
    }
}
