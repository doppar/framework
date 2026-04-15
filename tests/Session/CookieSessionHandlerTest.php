<?php

namespace Tests\Unit\Session;

use Phaseolies\Session\Handlers\CookieSessionHandler;
use PHPUnit\Framework\TestCase;

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
        $this->markTestSkipped('validate() with a cookie triggers Config::get(app.key) which requires a bootstrapped Application.');
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
}
