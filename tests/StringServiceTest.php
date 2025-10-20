<?php

namespace Tests\Unit;

use Phaseolies\Support\StringService;
use PHPUnit\Framework\TestCase;

class StringServiceTest extends TestCase
{
    private StringService $stringService;

    protected function setUp(): void
    {
        $this->stringService = new StringService();
    }

    public function testSubstr()
    {
        $this->assertEquals('World', $this->stringService->substr('Hello World', 6));
        $this->assertEquals('Wor', $this->stringService->substr('Hello World', 6, 3));
        $this->assertEquals('界', $this->stringService->substr('Hello 世界', 7, 1));
    }

    public function testLen()
    {
        $this->assertEquals(11, $this->stringService->len('Hello World'));
        $this->assertEquals(8, $this->stringService->len('Hello 世界'));
    }

    public function testCountWord()
    {
        $this->assertEquals(2, $this->stringService->countWord('Hello World'));
        $this->assertNotEquals(3, $this->stringService->countWord('Hello World'));
    }

    public function testIsPalindrome()
    {
        $this->assertTrue($this->stringService->isPalindrome('madam'));
        $this->assertTrue($this->stringService->isPalindrome('A man, a plan, a canal, Panama!'));
        $this->assertFalse($this->stringService->isPalindrome('hello'));
    }

    public function testRandom()
    {
        $random1 = $this->stringService->random(10);
        $random2 = $this->stringService->random(10);

        $this->assertEquals(10, strlen($random1));
        $this->assertNotEquals($random1, $random2);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{10}$/', $random1);
    }

    public function testCamel()
    {
        $this->assertEquals('helloWorld', $this->stringService->camel('hello_world'));
        $this->assertEquals('helloWorld', $this->stringService->camel('Hello_World'));
    }

    public function testMask()
    {
        $this->assertEquals('m***************m', $this->stringService->mask('mahedi@doppar.com', 1, 1));
        $this->assertEquals('ma*************om', $this->stringService->mask('mahedi@doppar.com', 2, 2));
        $this->assertEquals('j##n', $this->stringService->mask('john', 1, 1, '#'));
        $this->assertEquals('john', $this->stringService->mask('john', 4, 0));
        $this->assertEquals('john', $this->stringService->mask('john', 2, 2));
    }

    public function testTruncate()
    {
        $this->assertEquals('Hello...', $this->stringService->truncate('Hello World', 5));
        $this->assertEquals('Hello', $this->stringService->truncate('Hello', 10));
        $this->assertEquals('Hello---', $this->stringService->truncate('Hello World', 5, '---'));
    }

    public function testSnake()
    {
        $this->assertEquals('hello_world', $this->stringService->snake('helloWorld'));
        $this->assertEquals('hello_world', $this->stringService->snake('HelloWorld'));
    }

    public function testTitle()
    {
        $this->assertEquals('Hello World', $this->stringService->title('hello world'));
        $this->assertEquals('Hello 世界', $this->stringService->title('hello 世界'));
    }

    public function testSlug()
    {
        $this->assertEquals('hello-world', $this->stringService->slug('Hello World!'));
        $this->assertEquals('hello_world', $this->stringService->slug('Hello World!', '_'));
    }

    public function testContains()
    {
        $this->assertTrue($this->stringService->contains('Hello World', 'World'));
        $this->assertTrue($this->stringService->contains('Hello World', 'world'));
        $this->assertTrue($this->stringService->contains('Hello World', ['foo', 'World']));
        $this->assertFalse($this->stringService->contains('Hello World', 'Foo'));
    }

    public function testLimitWords()
    {
        $this->assertEquals('This is a...', $this->stringService->limitWords('This is a test string', 3));
        $this->assertEquals('This is a test string', $this->stringService->limitWords('This is a test string', 10));
        $this->assertEquals('This is a---', $this->stringService->limitWords('This is a test string', 3, '---'));
    }

    public function testIs()
    {
        $this->assertTrue($this->stringService->is('hello', 'hello'));
        $this->assertTrue($this->stringService->is('hello*', 'hello world'));
        $this->assertFalse($this->stringService->is('hello*', 'hi world'));
    }

    public function testRemoveWhiteSpace()
    {
        $this->assertEquals('HelloWorld', $this->stringService->removeWhiteSpace('Hello   World'));
        $this->assertEquals('Hello世界', $this->stringService->removeWhiteSpace('Hello 世 界'));
    }

    public function testUuid()
    {
        $uuid1 = $this->stringService->uuid();
        $uuid2 = $this->stringService->uuid();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid1);
        $this->assertNotEquals($uuid1, $uuid2);
    }

    public function testStartsWith()
    {
        $this->assertTrue($this->stringService->startsWith('Hello World', 'Hello'));
        $this->assertTrue($this->stringService->startsWith('Hello World', ['Hi', 'Hello']));
        $this->assertFalse($this->stringService->startsWith('Hello World', 'World'));
    }

    public function testEndsWith()
    {
        $this->assertTrue($this->stringService->endsWith('Hello World', 'World'));
        $this->assertTrue($this->stringService->endsWith('Hello World', ['Earth', 'World']));
        $this->assertFalse($this->stringService->endsWith('Hello World', 'Hello'));
    }

    public function testStudly()
    {
        $this->assertEquals('HelloWorld', $this->stringService->studly('hello_world'));
        $this->assertEquals('HelloWorld', $this->stringService->studly('hello-world'));
    }

    public function testReverse()
    {
        $this->assertEquals('dlroW olleH', $this->stringService->reverse('Hello World'));
        $this->assertEquals('界世 olleH', $this->stringService->reverse('Hello 世界'));
    }

    public function testExtractNumbers()
    {
        $this->assertEquals('12345', $this->stringService->extractNumbers('abc123def45'));
        $this->assertEquals('2023', $this->stringService->extractNumbers('Year 2023'));
    }

    public function testExtractEmails()
    {
        $emails = $this->stringService->extractEmails('Contact me at test@example.com or support@test.org');
        $this->assertEquals(['test@example.com', 'support@test.org'], $emails);
        $this->assertEmpty($this->stringService->extractEmails('No emails here'));
    }

    public function testHighlightKeyword()
    {
        $this->assertEquals(
            'Hello <strong>World</strong>',
            $this->stringService->highlightKeyword('Hello World', 'World')
        );
        $this->assertEquals(
            'Hello <em>World</em>',
            $this->stringService->highlightKeyword('Hello World', 'World', 'em')
        );
    }

    public function testToUpper()
    {
        $this->assertEquals('HELLO WORLD', $this->stringService->toUpper('Hello World'));
        $this->assertEquals('HELLO 世界', $this->stringService->toUpper('Hello 世界'));
    }

        public function testAfter(): void
    {
        $this->assertEquals('World', $this->stringService->after('Hello/World', '/'));
        $this->assertEquals('こんにちは', $this->stringService->after('挨拶:こんにちは', ':'));

        $subject = 'no-sep';
        // when search not found, implementation returns original subject
        $this->assertEquals($subject, $this->stringService->after($subject, '#'));
        // empty search returns subject as implemented
        $this->assertEquals($subject, $this->stringService->after($subject, ''));
    }

    public function testBefore(): void
    {
        $this->assertEquals('Hello', $this->stringService->before('Hello/World', '/'));
        $this->assertEquals('挨拶', $this->stringService->before('挨拶:こんにちは', ':'));

        $subject = 'no-sep';
        // when search not found, implementation returns original subject
        $this->assertEquals($subject, $this->stringService->before($subject, '#'));
        // empty search returns subject as implemented
        $this->assertEquals($subject, $this->stringService->before($subject, ''));
    }

    public function testBetween(): void
    {
        $this->assertEquals('b', $this->stringService->between('a [b] c', '[', ']'));
        $this->assertEquals('大阪', $this->stringService->between('東京-大阪-名古屋', '東京-', '-名古屋'));

        $subject = 'no-delims';
        // when delimiters not present, implementation returns original subject
        $this->assertEquals($subject, $this->stringService->between($subject, '[', ']'));
        // empty delimiters return subject as implemented
        $this->assertEquals($subject, $this->stringService->between($subject, '', ''));
    }

    public function it_returns_true_for_valid_json()
    {
        $this->assertTrue($this->stringService->isJson('{"name":"John"}'));
        $this->assertTrue($this->stringService->isJson('[1, 2, 3]'));
        $this->assertTrue($this->stringService->isJson('true'));
        $this->assertTrue($this->stringService->isJson('"Hello"'));
    }

    public function it_returns_false_for_invalid_json()
    {
        $this->assertFalse($this->stringService->isJson('{"name": "John"')); // Missing closing brace
        $this->assertFalse($this->stringService->isJson('{name: John}'));    // Invalid quotes
        $this->assertFalse($this->stringService->isJson('Hello World'));
        $this->assertFalse($this->stringService->isJson(''));
    }
}
