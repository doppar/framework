<?php

namespace Tests\Unit;

use Phaseolies\Utilities\CookieUpdater;
use Phaseolies\Http\Response\Cookie;
use Phaseolies\Support\CookieJar;
use PHPUnit\Framework\TestCase;

class CookieUpdaterTest extends TestCase
{
    private Cookie $cookie;
    private CookieUpdater $updater;

    protected function setUp(): void
    {
        $this->cookie = new Cookie('test_cookie', 'initial_value');
        $this->updater = new CookieUpdater($this->cookie);
    }

    public function testInitialization()
    {
        $this->assertInstanceOf(CookieUpdater::class, $this->updater);
        $this->assertSame($this->cookie, $this->getCookie());
    }

    public function testWithValue()
    {
        $result = $this->updater->withValue('new_value');

        $this->assertSame($this->updater, $result);
        $this->assertEquals('new_value', $this->getCookie()->getValue());
    }

    public function testWithExpiresWithTimestamp()
    {
        $expires = time() + 3600;
        $this->updater->withExpires($expires);

        $this->assertEquals($expires, $this->getCookie()->getExpiresTime());
    }

    public function testWithExpiresWithDateTime()
    {
        $expires = new \DateTime('+1 hour');
        $this->updater->withExpires($expires);

        $this->assertEquals($expires->getTimestamp(), $this->getCookie()->getExpiresTime());
    }

    public function testWithExpiresWithString()
    {
        $this->updater->withExpires('+1 hour');
        $expected = strtotime('+1 hour');

        $this->assertEqualsWithDelta($expected, $this->getCookie()->getExpiresTime(), 1);
    }

    public function testWithPath()
    {
        $this->updater->withPath('/test/path');

        $this->assertEquals('/test/path', $this->getCookie()->getPath());
    }

    public function testWithDomain()
    {
        $this->updater->withDomain('example.com');

        $this->assertEquals('example.com', $this->getCookie()->getDomain());
    }

    public function testWithSecure()
    {
        $this->updater->withSecure(true);

        $this->assertTrue($this->getCookie()->isSecure());
    }

    public function testWithHttpOnly()
    {
        $this->updater->withHttpOnly(true);

        $this->assertTrue($this->getCookie()->isHttpOnly());
    }

    public function testWithRaw()
    {
        $this->updater->withRaw(true);

        $this->assertTrue($this->getCookie()->isRaw());
    }

    public function testWithSameSite()
    {
        $this->updater->withSameSite('lax');

        $this->assertEquals('lax', $this->getCookie()->getSameSite());
    }

    public function testWithPartitioned()
    {
        $this->updater->withPartitioned(true);

        $this->assertTrue($this->getCookie()->isPartitioned());
    }

    public function testMethodChaining()
    {
        $result = $this->updater
            ->withValue('chained_value')
            ->withPath('/chained')
            ->withDomain('example.org')
            ->withSecure(true);

        $this->assertSame($this->updater, $result);
        $this->assertEquals('chained_value', $this->getCookie()->getValue());
        $this->assertEquals('/chained', $this->getCookie()->getPath());
        $this->assertEquals('example.org', $this->getCookie()->getDomain());
        $this->assertTrue($this->getCookie()->isSecure());
    }

    /**
     * Helper method to access the protected cookie property for testing
     */
    private function getCookie(): Cookie
    {
        $reflection = new \ReflectionClass($this->updater);
        $property = $reflection->getProperty('cookie');
        $property->setAccessible(true);

        return $property->getValue($this->updater);
    }
}
