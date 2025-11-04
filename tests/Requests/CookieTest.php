<?php

namespace Tests\Unit\Requests;

use Phaseolies\Http\Response\Cookie;
use Phaseolies\Utilities\CookieUpdater;
use PHPUnit\Framework\TestCase;
use DateTime;

class CookieTest extends TestCase
{
    public function testBasicCookieCreation()
    {
        $cookie = new Cookie('test', 'value');

        $this->assertEquals('test', $cookie->getName());
        $this->assertEquals('value', $cookie->getValue());
        $this->assertEquals('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertFalse($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEquals('lax', $cookie->getSameSite());
    }

    public function testCreateFactoryMethod()
    {
        $cookie = Cookie::create('test', 'value', 3600, '/path', 'example.com', true, false);

        $this->assertEquals('test', $cookie->getName());
        $this->assertEquals('value', $cookie->getValue());
        $this->assertEquals('/path', $cookie->getPath());
        $this->assertEquals('example.com', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertFalse($cookie->isHttpOnly());
    }

    public function testFromStringParsing()
    {
        $cookieString = 'test=value; expires=Fri, 31 Dec 2023 23:59:59 GMT; path=/; domain=example.com; secure; httponly; samesite=strict';
        $cookie = Cookie::fromString($cookieString);

        $this->assertEquals('test', $cookie->getName());
        $this->assertEquals('value', $cookie->getValue());
        $this->assertEquals('/', $cookie->getPath());
        $this->assertEquals('example.com', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEquals('strict', $cookie->getSameSite());
    }

    public function testWithValue()
    {
        $cookie = new Cookie('test', 'value');
        $newCookie = $cookie->withValue('new_value');

        $this->assertEquals('new_value', $newCookie->getValue());
        $this->assertEquals('value', $cookie->getValue()); // original unchanged
    }

    public function testWithDomain()
    {
        $cookie = new Cookie('test', 'value');
        $newCookie = $cookie->withDomain('example.com');

        $this->assertEquals('example.com', $newCookie->getDomain());
    }

    public function testWithExpires()
    {
        $cookie = new Cookie('test', 'value');

        // Test with timestamp
        $newCookie1 = $cookie->withExpires(time() + 3600);
        $this->assertEquals(time() + 3600, $newCookie1->getExpiresTime());

        // Test with DateTime
        $date = new DateTime('+1 hour');
        $newCookie2 = $cookie->withExpires($date);
        $this->assertEquals($date->getTimestamp(), $newCookie2->getExpiresTime());

        // Test with string
        $newCookie3 = $cookie->withExpires('+1 hour');
        $this->assertEquals(strtotime('+1 hour'), $newCookie3->getExpiresTime());
    }

    public function testWithPath()
    {
        $cookie = new Cookie('test', 'value');
        $newCookie = $cookie->withPath('/newpath');

        $this->assertEquals('/newpath', $newCookie->getPath());
    }

    public function testWithSecure()
    {
        $cookie = new Cookie('test', 'value');
        $newCookie = $cookie->withSecure(true);

        $this->assertTrue($newCookie->isSecure());
    }

    public function testWithHttpOnly()
    {
        $cookie = new Cookie('test', 'value');
        $cookie = $cookie->withHttpOnly(false);

        $this->assertFalse($cookie->isHttpOnly());
    }

    public function testWithRaw()
    {
        $cookie = new Cookie('test', 'value');
        $newCookie = $cookie->withRaw(true);

        $this->assertTrue($newCookie->isRaw());
    }

    public function testWithSameSite()
    {
        $cookie = new Cookie('test', 'value');

        $laxCookie = $cookie->withSameSite(Cookie::SAMESITE_LAX);
        $this->assertEquals('lax', $laxCookie->getSameSite());

        $strictCookie = $cookie->withSameSite(Cookie::SAMESITE_STRICT);
        $this->assertEquals('strict', $strictCookie->getSameSite());

        $noneCookie = $cookie->withSameSite(Cookie::SAMESITE_NONE);
        $this->assertEquals('none', $noneCookie->getSameSite());
    }

    public function testWithPartitioned()
    {
        $cookie = new Cookie('test', 'value');
        $newCookie = $cookie->withPartitioned(true);

        $this->assertTrue($newCookie->isPartitioned());
    }

    public function testToStringForDeletedCookie()
    {
        $cookie = new Cookie('test', '');
        $string = (string)$cookie;

        $this->assertStringContainsString('test=deleted', $string);
        $this->assertStringContainsString('expires=', $string);
        $this->assertStringContainsString('Max-Age=0', $string);
    }

    public function testIsCleared()
    {
        $expiredCookie = new Cookie('test', 'value', time() - 3600);
        $this->assertTrue($expiredCookie->isCleared());

        $validCookie = new Cookie('test', 'value', time() + 3600);
        $this->assertFalse($validCookie->isCleared());
    }

    public function testGetMaxAge()
    {
        $cookie = new Cookie('test', 'value', time() + 3600);
        $this->assertEquals(3600, $cookie->getMaxAge());

        $expiredCookie = new Cookie('test', 'value', time() - 3600);
        $this->assertEquals(0, $expiredCookie->getMaxAge());
    }

    public function testModifyReturnsCookieUpdater()
    {
        $cookie = new Cookie('test', 'value');
        $updater = $cookie->modify();

        $this->assertInstanceOf(CookieUpdater::class, $updater);
    }

    public function testEmptyCookieName()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Cookie('', 'value');
    }

    public function testInvalidExpiresTime()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Cookie('test', 'value', 'invalid time');
    }

    public function testInvalidSameSite()
    {
        $this->expectException(\InvalidArgumentException::class);
        $cookie = new Cookie('test', 'value');
        $cookie->withSameSite('invalid');
    }

    public function testSetSecureDefault()
    {
        $cookie = new Cookie('test', 'value', 0, '/', null, null);
        $cookie->setSecureDefault(true);

        $this->assertTrue($cookie->isSecure());
    }
}
