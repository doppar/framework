<?php

namespace Tests\Unit;

use Phaseolies\Support\UrlGenerator;
use PHPUnit\Framework\TestCase;

class UrlGeneratorTest extends TestCase
{
    private $baseUrl = 'http://localhost';
    private $secureBaseUrl = 'https://localhost';
    private $urlGenerator;

    protected function setUp(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['argv'] = ['phpunit'];

        $this->urlGenerator = new UrlGenerator($this->baseUrl);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['SERVER_PORT']);
        unset($_SERVER['SCRIPT_NAME']);
        unset($_SERVER['argv']);
    }

    public function testInitialization()
    {
        $this->assertInstanceOf(UrlGenerator::class, $this->urlGenerator);
        $this->assertEquals($this->baseUrl, $this->urlGenerator->base());
    }

    public function testEnqueueBasicUrl()
    {
        $url = $this->urlGenerator->enqueue('path/to/resource');
        $this->assertEquals('http://localhost/path/to/resource', $url);
    }

    public function testEnqueueWithSecure()
    {
        $url = $this->urlGenerator->enqueue('path/to/resource', true);
        $this->assertEquals('https://localhost/path/to/resource', $url);
    }

    public function testToMethod()
    {
        $generator = $this->urlGenerator->to('path/to/resource');
        $this->assertInstanceOf(UrlGenerator::class, $generator);
        $this->assertEquals('http://localhost/path/to/resource', $generator->make());
    }

    public function testWithQueryParameters()
    {
        $url = $this->urlGenerator
            ->to('path')
            ->withQuery(['param1' => 'value1', 'param2' => 'value2'])
            ->make();

        $this->assertStringContainsString('param1=value1', $url);
        $this->assertStringContainsString('param2=value2', $url);
        $this->assertStringStartsWith('http://localhost/path?', $url);
    }

    public function testWithQueryString()
    {
        $url = $this->urlGenerator
            ->to('path')
            ->withQuery('param1=value1&param2=value2')
            ->make();

        $this->assertStringContainsString('param1=value1', $url);
        $this->assertStringContainsString('param2=value2', $url);
        $this->assertStringStartsWith('http://localhost/path?', $url);
    }

    public function testWithFragment()
    {
        $url = $this->urlGenerator
            ->to('path')
            ->withFragment('section1')
            ->make();

        $this->assertEquals('http://localhost/path#section1', $url);
    }

    public function testIsValidUrl()
    {
        $this->assertTrue($this->urlGenerator->isValid('http://localhost'));
        $this->assertTrue($this->urlGenerator->isValid('https://localhost'));
        $this->assertTrue($this->urlGenerator->isValid('mailto:test@localhost'));
        $this->assertTrue($this->urlGenerator->isValid('tel:+123456789'));
        $this->assertTrue($this->urlGenerator->isValid('//localhost'));
        $this->assertTrue($this->urlGenerator->isValid('#anchor'));

        $this->assertFalse($this->urlGenerator->isValid('invalid-url'));
        $this->assertFalse($this->urlGenerator->isValid('localhost'));
    }

    public function testSetSecure()
    {
        $this->urlGenerator->setSecure(true);

        $url = $this->urlGenerator->to('path')->make();
        $this->assertEquals('https://localhost/path', $url);
    }

    public function testBaseUrlWithoutTrailingSlash()
    {
        $generator = new UrlGenerator('http://localhost/');
        $url = $generator->to('path')->make();
        $this->assertEquals('http://localhost/path', $url);
    }

    public function testPathWithoutLeadingSlash()
    {
        $url = $this->urlGenerator->to('path')->make();
        $this->assertEquals('http://localhost/path', $url);
    }

    public function testPathWithLeadingSlash()
    {
        $url = $this->urlGenerator->to('/path')->make();
        $this->assertEquals('http://localhost/path', $url);
    }

    public function testEmptyPath()
    {
        $url = $this->urlGenerator->to('')->make();
        $this->assertEquals('http://localhost/', $url);
    }

    public function testRootPath()
    {
        $url = $this->urlGenerator->to('/')->make();
        $this->assertEquals('http://localhost/', $url);
    }

    public function testComplexUrlConstruction()
    {
        $url = $this->urlGenerator
            ->to('products/details')
            ->withQuery(['id' => 123, 'category' => 'electronics'])
            ->withFragment('reviews')
            ->make();

        $expected = 'http://localhost/products/details?id=123&category=electronics#reviews';
        $this->assertEquals($expected, $url);
    }
}
