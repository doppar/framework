<?php

namespace Tests\Unit;

use Phaseolies\Support\UrlGenerator;
use PHPUnit\Framework\TestCase;

class UrlGeneratorTest extends TestCase
{
    private $baseUrl = 'http://example.com';
    private $secureBaseUrl = 'https://example.com';
    private $urlGenerator;

    protected function setUp(): void
    {
        $this->urlGenerator = new UrlGenerator($this->baseUrl);
    }

    public function testInitialization()
    {
        $this->assertInstanceOf(UrlGenerator::class, $this->urlGenerator);
        $this->assertEquals($this->baseUrl, $this->urlGenerator->base());
        $this->assertFalse($this->isSecure());
    }

    public function testEnqueueBasicUrl()
    {
        $url = $this->urlGenerator->enqueue('path/to/resource');
        $this->assertEquals('http://example.com/path/to/resource', $url);
    }

    public function testEnqueueWithSecure()
    {
        $url = $this->urlGenerator->enqueue('path/to/resource', true);
        $this->assertEquals('https://example.com/path/to/resource', $url);
    }

    public function testToMethod()
    {
        $generator = $this->urlGenerator->to('path/to/resource');
        $this->assertInstanceOf(UrlGenerator::class, $generator);
        $this->assertEquals('http://example.com/path/to/resource', $generator->make());
    }

    public function testWithQueryParameters()
    {
        $url = $this->urlGenerator
            ->to('path')
            ->withQuery(['param1' => 'value1', 'param2' => 'value2'])
            ->make();

        $this->assertStringContainsString('param1=value1', $url);
        $this->assertStringContainsString('param2=value2', $url);
    }

    public function testWithQueryString()
    {
        $url = $this->urlGenerator
            ->to('path')
            ->withQuery('param1=value1&param2=value2')
            ->make();

        $this->assertStringContainsString('param1=value1', $url);
        $this->assertStringContainsString('param2=value2', $url);
    }

    public function testWithFragment()
    {
        $url = $this->urlGenerator
            ->to('path')
            ->withFragment('section1')
            ->make();

        $this->assertStringEndsWith('#section1', $url);
    }

    public function testIsValidUrl()
    {
        $this->assertTrue($this->urlGenerator->isValid('http://example.com'));
        $this->assertTrue($this->urlGenerator->isValid('https://example.com'));
        $this->assertTrue($this->urlGenerator->isValid('mailto:test@example.com'));
        $this->assertTrue($this->urlGenerator->isValid('tel:+123456789'));
        $this->assertTrue($this->urlGenerator->isValid('//example.com'));
        $this->assertTrue($this->urlGenerator->isValid('#anchor'));

        $this->assertFalse($this->urlGenerator->isValid('invalid-url'));
        $this->assertFalse($this->urlGenerator->isValid('example.com'));
    }

    public function testSetSecure()
    {
        $this->urlGenerator->setSecure(true);
        $this->assertTrue($this->isSecure());

        $url = $this->urlGenerator->to('path')->make();
        $this->assertStringStartsWith('https://', $url);
    }

    public function testBaseUrlWithoutTrailingSlash()
    {
        $generator = new UrlGenerator('http://example.com/');
        $url = $generator->to('path')->make();
        $this->assertEquals('http://example.com/path', $url);
    }

    public function testPathWithoutLeadingSlash()
    {
        $url = $this->urlGenerator->to('path')->make();
        $this->assertEquals('http://example.com/path', $url);
    }

    public function testPathWithLeadingSlash()
    {
        $url = $this->urlGenerator->to('/path')->make();
        $this->assertEquals('http://example.com/path', $url);
    }

    public function testEmptyPath()
    {
        $url = $this->urlGenerator->to('')->make();
        $this->assertEquals('http://example.com/', $url);
    }

    public function testRootPath()
    {
        $url = $this->urlGenerator->to('/')->make();
        $this->assertEquals('http://example.com/', $url);
    }

    public function testComplexUrlConstruction()
    {
        $url = $this->urlGenerator
            ->to('products/details')
            ->withQuery(['id' => 123, 'category' => 'electronics'])
            ->withFragment('reviews')
            ->make();

        $expected = 'http://example.com/products/details?id=123&category=electronics#reviews';
        $this->assertEquals($expected, $url);
    }

    /**
     * Helper method to check if URL generator is secure
     */
    private function isSecure(): bool
    {
        $reflection = new \ReflectionClass($this->urlGenerator);
        $property = $reflection->getProperty('secure');
        $property->setAccessible(true);

        return $property->getValue($this->urlGenerator);
    }
}
