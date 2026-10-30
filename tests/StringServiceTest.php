<?php

namespace Tests\Unit;

use Phaseolies\Application;
use Phaseolies\DI\Container;
use Phaseolies\Launchers\FacadeLauncher;
use Phaseolies\Support\Contracts\StringServiceInterface;
use Phaseolies\Support\StringService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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

    public function testMaskWithNoVisibleEndHidesTheEnd()
    {
        $this->assertSame('12*******', $this->stringService->mask('123456789', 2, 0));
        $this->assertSame('se*********', $this->stringService->mask('secretvalue', 2, 0));
    }

    public function testMaskWithNoVisibleEndNeverRepeatsTheOriginal()
    {
        $original = '1234567890';
        $masked = $this->stringService->mask($original, 3, 0);

        $this->assertSame(strlen($original), strlen($masked));
        $this->assertStringNotContainsString($original, $masked);
        $this->assertStringNotContainsString('4567890', $masked);
    }

    public function testMaskWithNoVisibleStartHidesTheStart()
    {
        $this->assertSame('*******89', $this->stringService->mask('123456789', 0, 2));
    }

    public function testMaskWithNothingVisibleMasksEverything()
    {
        $this->assertSame('*********', $this->stringService->mask('123456789', 0, 0));
        $this->assertSame('#####', $this->stringService->mask('abcde', 0, 0, '#'));
    }

    public function testMaskKeepsTheOriginalLength()
    {
        foreach ([[0, 0], [1, 0], [0, 1], [2, 3], [4, 4]] as [$start, $end]) {
            $this->assertSame(
                9,
                strlen($this->stringService->mask('123456789', $start, $end)),
                "visible {$start}/{$end} changed the length"
            );
        }
    }

    public function testMaskTreatsNegativeVisibleCountsAsZero()
    {
        $this->assertSame('*********', $this->stringService->mask('123456789', -2, -3));
        $this->assertSame('12*******', $this->stringService->mask('123456789', 2, -1));
        $this->assertSame('*******89', $this->stringService->mask('123456789', -1, 2));
    }

    public function testMaskReturnsShortStringsUnchanged()
    {
        $this->assertSame('', $this->stringService->mask('', 1, 1));
        $this->assertSame('ab', $this->stringService->mask('ab', 1, 1));
        $this->assertSame('abc', $this->stringService->mask('abc', 3, 0));
        $this->assertSame('abc', $this->stringService->mask('abc', 0, 3));
    }

    public function testMaskOnlyHidesTheMiddleWhenBothEndsAreVisible()
    {
        $this->assertSame('1*******9', $this->stringService->mask('123456789', 1, 1));
        $this->assertSame('123***789', $this->stringService->mask('123456789', 3, 3));
        $this->assertSame('12345678*', $this->stringService->mask('123456789', 8, 0));
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

    public function testReturnsTrueForValidJson()
    {
        $this->assertTrue($this->stringService->isJson('{"name":"John"}'));
        $this->assertTrue($this->stringService->isJson('[1, 2, 3]'));
        $this->assertTrue($this->stringService->isJson('true'));
        $this->assertTrue($this->stringService->isJson('"Hello"'));
    }

    public function testReturnsFalseForInvalidJson()
    {
        $this->assertFalse($this->stringService->isJson('{"name": "John"')); // Missing closing brace
        $this->assertFalse($this->stringService->isJson('{name: John}'));    // Invalid quotes
        $this->assertFalse($this->stringService->isJson('Hello World'));
        $this->assertFalse($this->stringService->isJson(''));
    }

    // ---- isPalindrome -----------------------------------------------------

    public function testIsPalindromeHandlesNonLatinText()
    {
        $this->assertFalse($this->stringService->isPalindrome('привет'));
        $this->assertFalse($this->stringService->isPalindrome('你好吗'));
        $this->assertTrue($this->stringService->isPalindrome('топот'));
        $this->assertTrue($this->stringService->isPalindrome('А роза упала на лапу Азора'));
        $this->assertTrue($this->stringService->isPalindrome('été'));
        $this->assertTrue($this->stringService->isPalindrome('ÉtÉ'));
    }

    public function testIsPalindromeKeepsAsciiBehaviour()
    {
        $this->assertTrue($this->stringService->isPalindrome('Was it a car or a cat I saw?'));
        $this->assertTrue($this->stringService->isPalindrome('12321'));
        $this->assertFalse($this->stringService->isPalindrome('12345'));
        $this->assertFalse($this->stringService->isPalindrome('ab-à'));
    }

    // ---- random / uuid ----------------------------------------------------

    public function testRandomReturnsTheRequestedLength()
    {
        foreach ([0, 1, 7, 32, 200] as $length) {
            $this->assertSame($length, strlen($this->stringService->random($length)));
        }
    }

    public function testRandomWithNegativeLengthReturnsEmptyString()
    {
        $this->assertSame('', $this->stringService->random(-5));
    }

    public function testRandomOnlyUsesAlphanumericCharacters()
    {
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{500}$/', $this->stringService->random(500));
    }

    public function testRandomCanRepeatCharactersWithinOneString()
    {
        // A shuffle of the alphabet can never repeat a character in a short
        // string; real random picks do, so 62 draws of 400 chars always repeat.
        $this->assertLessThan(400, count(array_unique(str_split($this->stringService->random(400)))));
    }

    public function testRandomEventuallyUsesEveryCharacterClass()
    {
        $seen = array_unique(str_split($this->stringService->random(5000)));

        $this->assertGreaterThan(55, count($seen));
        $this->assertMatchesRegularExpression('/[a-z]/', implode('', $seen));
        $this->assertMatchesRegularExpression('/[A-Z]/', implode('', $seen));
        $this->assertMatchesRegularExpression('/[0-9]/', implode('', $seen));
    }

    public function testRandomValuesDoNotCollide()
    {
        $values = [];

        for ($i = 0; $i < 500; $i++) {
            $values[] = $this->stringService->random(16);
        }

        $this->assertCount(500, array_unique($values));
    }

    public function testUuidIsAlwaysAValidVersion4()
    {
        for ($i = 0; $i < 300; $i++) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $this->stringService->uuid()
            );
        }
    }

    public function testUuidValuesDoNotCollide()
    {
        $values = [];

        for ($i = 0; $i < 1000; $i++) {
            $values[] = $this->stringService->uuid();
        }

        $this->assertCount(1000, array_unique($values));
    }

    public function testUuidVariantNibbleCoversEveryAllowedValue()
    {
        $variants = [];

        for ($i = 0; $i < 600; $i++) {
            $variants[$this->stringService->uuid()[19]] = true;
        }

        $this->assertEqualsCanonicalizing(['8', '9', 'a', 'b'], array_keys($variants));
    }

    // ---- truncate ---------------------------------------------------------

    public function testTruncateCountsCharactersNotBytes()
    {
        $this->assertSame('héllo...', $this->stringService->truncate('héllo wörld', 5));
        $this->assertSame('héllo', $this->stringService->truncate('héllo', 5));
        $this->assertSame('日本...', $this->stringService->truncate('日本語テキスト', 2));
    }

    public function testTruncateNeverProducesInvalidUtf8()
    {
        foreach (range(0, 8) as $max) {
            $this->assertTrue(mb_check_encoding($this->stringService->truncate('héllo wörld 日本語', $max), 'UTF-8'));
        }
    }

    public function testTruncateWithNegativeLengthKeepsOnlyTheSuffix()
    {
        $this->assertSame('...', $this->stringService->truncate('hello world', -1));
        $this->assertSame('...', $this->stringService->truncate('hello world', 0));
    }

    public function testTruncateKeepsStringsThatFitExactly()
    {
        $this->assertSame('', $this->stringService->truncate('', 3));
        $this->assertSame('abc', $this->stringService->truncate('abc', 3));
        $this->assertSame('abc...', $this->stringService->truncate('abcd', 3));
    }

    // ---- mask (multibyte) -------------------------------------------------

    public function testMaskCountsCharactersNotBytes()
    {
        $this->assertSame('h*********d', $this->stringService->mask('héllo wörld', 1, 1));
        $this->assertSame('日*****ト', $this->stringService->mask('日本語テキスト', 1, 1));
        $this->assertSame('日本*****', $this->stringService->mask('日本語テキスト', 2, 0));
    }

    public function testMaskNeverProducesInvalidUtf8()
    {
        foreach ([[1, 1], [2, 0], [0, 2], [3, 3]] as [$start, $end]) {
            $this->assertTrue(mb_check_encoding($this->stringService->mask('héllo wörld 日本語', $start, $end), 'UTF-8'));
        }
    }

    public function testMaskKeepsTheCharacterLengthOfMultibyteText()
    {
        $this->assertSame(11, mb_strlen($this->stringService->mask('héllo wörld', 2, 2)));
    }

    // ---- snake ------------------------------------------------------------

    public function testSnakeHandlesNonAsciiLetters()
    {
        $this->assertSame('école_nom', $this->stringService->snake('ÉcoleNom'));
        $this->assertSame('é_nom', $this->stringService->snake('éNom'));
        $this->assertSame('straße_name', $this->stringService->snake('straßeName'));
    }

    public function testSnakeKeepsAsciiBehaviour()
    {
        $this->assertSame('hello_world', $this->stringService->snake('helloWorld'));
        $this->assertSame('hello_world', $this->stringService->snake('HelloWorld'));
        $this->assertSame('user_profile_image', $this->stringService->snake('userProfileImage'));
        $this->assertSame('already_snake', $this->stringService->snake('already_snake'));
        $this->assertSame('', $this->stringService->snake(''));
    }

    // ---- slug -------------------------------------------------------------

    public function testSlugWithEmptySeparatorJoinsTheWords()
    {
        $this->assertSame('helloworld', $this->stringService->slug('Hello World', ''));
    }

    public function testSlugWithEmptySeparatorStillStripsPunctuation()
    {
        $this->assertSame('helloworld', $this->stringService->slug('Hello, World!', ''));
    }

    public function testSlugCollapsesRepeatedSeparatorsAndTrimsTheEdges()
    {
        $this->assertSame('hello-world', $this->stringService->slug('  Hello    World!!  '));
        $this->assertSame('hello__world', $this->stringService->slug('Hello World', '__'));
        $this->assertSame('hello--world', $this->stringService->slug('Hello  World', '--'));
    }

    public function testSlugKeepsUnicodeLetters()
    {
        $this->assertSame('привет-мир', $this->stringService->slug('Привет Мир'));
    }

    // ---- contains ---------------------------------------------------------

    public function testContainsNeverMatchesAnEmptyNeedle()
    {
        $this->assertFalse($this->stringService->contains('abc', ''));
        $this->assertFalse($this->stringService->contains('abc', ['']));
        $this->assertFalse($this->stringService->contains('', ''));
    }

    public function testContainsStillMatchesOtherNeedlesNextToAnEmptyOne()
    {
        $this->assertTrue($this->stringService->contains('abc', ['', 'b']));
        $this->assertFalse($this->stringService->contains('abc', ['', 'x']));
    }

    public function testContainsRespectsCaseSensitivity()
    {
        $this->assertFalse($this->stringService->contains('Hello', 'hello', false));
        $this->assertTrue($this->stringService->contains('Hello', 'Hello', false));
        $this->assertTrue($this->stringService->contains('Ünï', 'ÜNÏ'));
    }

    // ---- limitWords -------------------------------------------------------

    public function testLimitWordsWithNonPositiveLimitReturnsOnlyTheEnding()
    {
        $this->assertSame('...', $this->stringService->limitWords('a b c d', 0));
        $this->assertSame('...', $this->stringService->limitWords('a b c d', -1));
        $this->assertSame('...', $this->stringService->limitWords('a b c d', -100));
    }

    public function testLimitWordsDoesNotThrowOnInvalidUtf8()
    {
        $this->assertSame("\xFF bad...", $this->stringService->limitWords("\xFF bad worse", 2));
    }

    public function testLimitWordsHandlesEmptyAndWhitespaceOnlyStrings()
    {
        $this->assertSame('', $this->stringService->limitWords('', 3));
        $this->assertSame('', $this->stringService->limitWords("  \n ", 3));
    }

    public function testLimitWordsSplitsOnAnyWhitespace()
    {
        $this->assertSame('a b...', $this->stringService->limitWords("a\tb\nc d", 2));
    }

    // ---- longestCommonSubstring ------------------------------------------

    public function testLongestCommonSubstringNeverSplitsACharacter()
    {
        $this->assertSame('caf', $this->stringService->longestCommonSubstring('café', 'cafè'));
        $this->assertTrue(mb_check_encoding($this->stringService->longestCommonSubstring('café', 'cafè'), 'UTF-8'));
        $this->assertSame('本語', $this->stringService->longestCommonSubstring('日本語', '本語'));
    }

    public function testLongestCommonSubstringFindsTheLongestRun()
    {
        $this->assertSame('abcd', $this->stringService->longestCommonSubstring('xxabcdyy', 'zabcdz'));
        $this->assertSame('Hello ', $this->stringService->longestCommonSubstring('Hello World', 'Hello There'));
    }

    public function testLongestCommonSubstringWithNothingInCommonIsEmpty()
    {
        $this->assertSame('', $this->stringService->longestCommonSubstring('abc', 'xyz'));
        $this->assertSame('', $this->stringService->longestCommonSubstring('', 'abc'));
        $this->assertSame('', $this->stringService->longestCommonSubstring('abc', ''));
        $this->assertSame('', $this->stringService->longestCommonSubstring('', ''));
    }

    public function testLongestCommonSubstringPrefersTheFirstOfEqualLengthRuns()
    {
        $this->assertSame('ab', $this->stringService->longestCommonSubstring('abXcd', 'cdYab'));
    }

    public function testLongestCommonSubstringHandlesLongInputs()
    {
        $a = str_repeat('a', 1500) . 'MATCH' . str_repeat('b', 1500);
        $b = str_repeat('c', 1500) . 'MATCH' . str_repeat('d', 1500);

        $this->assertSame('MATCH', $this->stringService->longestCommonSubstring($a, $b));
    }

    // ---- highlightKeyword -------------------------------------------------

    public function testHighlightKeywordAcceptsAZeroKeyword()
    {
        $this->assertSame('a<strong>0</strong>b', $this->stringService->highlightKeyword('a0b', '0'));
    }

    public function testHighlightKeywordIgnoresAnEmptyKeyword()
    {
        $this->assertSame('abc', $this->stringService->highlightKeyword('abc', ''));
    }

    public function testHighlightKeywordMatchesNonAsciiCaseInsensitively()
    {
        $this->assertSame('<strong>Été</strong>', $this->stringService->highlightKeyword('Été', 'été'));
    }

    public function testHighlightKeywordKeepsTheOriginalCaseOfTheMatch()
    {
        $this->assertSame('<b>Hello</b> world', $this->stringService->highlightKeyword('Hello world', 'hello', 'b'));
    }

    public function testHighlightKeywordEscapesRegexCharactersInTheKeyword()
    {
        $this->assertSame('1<strong>+</strong>1', $this->stringService->highlightKeyword('1+1', '+'));
        $this->assertSame('a<strong>.</strong>b', $this->stringService->highlightKeyword('a.b', '.'));
    }

    public function testHighlightKeywordStillWorksOnInvalidUtf8()
    {
        $this->assertSame("\xFF <strong>a</strong>", $this->stringService->highlightKeyword("\xFF a", 'a'));
    }

    public function testHighlightKeywordHighlightsEveryOccurrence()
    {
        $this->assertSame('<strong>a</strong>b<strong>a</strong>', $this->stringService->highlightKeyword('aba', 'a'));
    }

    // ---- toUpper ----------------------------------------------------------

    public function testToUpperHandlesNonAsciiLetters()
    {
        $this->assertSame('ÉA', $this->stringService->toUpper('éa'));
        $this->assertSame('ПРИВЕТ', $this->stringService->toUpper('привет'));
        $this->assertSame('GET', $this->stringService->toUpper('get'));
        $this->assertSame('', $this->stringService->toUpper(''));
    }

    // ---- suffixAppend / removeSuffix -------------------------------------

    public function testSuffixAppendWorksWithoutABootedContainer()
    {
        $this->assertSame('PostController', $this->stringService->suffixAppend('post', 'Controller'));
    }

    public function testSuffixAppendLeavesAnExistingSuffixAlone()
    {
        $this->assertSame('PostController', $this->stringService->suffixAppend('PostController', 'Controller'));
    }

    public function testSuffixAppendOfAnEmptyInputReturnsTheSuffix()
    {
        $this->assertSame('Controller', $this->stringService->suffixAppend('', 'Controller'));
    }

    public function testSuffixAppendCapitalisesAMultibyteFirstLetter()
    {
        $this->assertSame('ÉcoleController', $this->stringService->suffixAppend('école', 'Controller'));
    }

    public function testRemoveSuffixRemovesOnlyAMatchingSuffix()
    {
        $this->assertSame('Post', $this->stringService->removeSuffix('PostController', 'Controller'));
        $this->assertSame('hello', $this->stringService->removeSuffix('hello', 'xyz'));
        $this->assertSame('PostControllerX', $this->stringService->removeSuffix('PostControllerX', 'Controller'));
    }

    public function testRemoveSuffixWithAnEmptySuffixReturnsTheInput()
    {
        $this->assertSame('hello', $this->stringService->removeSuffix('hello', ''));
        $this->assertSame('', $this->stringService->removeSuffix('', ''));
    }

    public function testRemoveSuffixIgnoresASuffixLongerThanTheInput()
    {
        $this->assertSame('ab', $this->stringService->removeSuffix('ab', 'abc'));
    }

    public function testRemoveSuffixOfTheWholeStringLeavesNothing()
    {
        $this->assertSame('', $this->stringService->removeSuffix('Controller', 'Controller'));
    }

    public function testRemoveSuffixHandlesMultibyteSuffixes()
    {
        $this->assertSame('café', $this->stringService->removeSuffix('café日本', '日本'));
        $this->assertSame('日', $this->stringService->removeSuffix('日本', '本'));
    }

    public function testSuffixAppendAndRemoveSuffixRoundTrip()
    {
        $name = $this->stringService->suffixAppend('invoice', 'Controller');

        $this->assertSame('Invoice', $this->stringService->removeSuffix($name, 'Controller'));
    }

    // ---- slug separators --------------------------------------------------

    public function testSlugTurnsUnderscoresSlashesAndColonsIntoSeparators()
    {
        $this->assertSame('snake-case-title', $this->stringService->slug('snake_case_title'));
        $this->assertSame('path-to-file', $this->stringService->slug('path/to/file'));
        $this->assertSame('key-value', $this->stringService->slug('key:value'));
        $this->assertSame('a-b-c', $this->stringService->slug('a\\b|c'));
    }

    public function testSlugTurnsSymbolsIntoSeparators()
    {
        $this->assertSame('rock-roll', $this->stringService->slug('Rock & Roll'));
        $this->assertSame('a-b', $this->stringService->slug('a&b'));
        $this->assertSame('1-1', $this->stringService->slug('1+1'));
        $this->assertSame('hello-world', $this->stringService->slug('(hello) [world]'));
    }

    public function testSlugConvertsExistingSeparatorsToACustomOne()
    {
        $this->assertSame('hello_world', $this->stringService->slug('hello-world', '_'));
        $this->assertSame('a_b_c', $this->stringService->slug('a-b c', '_'));
        $this->assertSame('hello.world', $this->stringService->slug('hello world', '.'));
    }

    public function testSlugKeepsExistingHyphensWithTheDefaultSeparator()
    {
        $this->assertSame('well-known-fact', $this->stringService->slug('well-known fact'));
        $this->assertSame('a-b', $this->stringService->slug('a--b'));
    }

    public function testSlugOfNothingMeaningfulIsEmpty()
    {
        $this->assertSame('', $this->stringService->slug(''));
        $this->assertSame('', $this->stringService->slug('!!! ??? ---'));
        $this->assertSame('', $this->stringService->slug('___', '_'));
    }

    public function testSlugKeepsDigits()
    {
        $this->assertSame('top-10-tips', $this->stringService->slug('Top 10 Tips!'));
        $this->assertSame('v2-0', $this->stringService->slug('v2.0'));
    }

    public function testSlugDoesNotSplitWordsOnCombiningMarks()
    {
        $this->assertSame('cafe-au-lait', $this->stringService->slug("cafe\u{0301} au lait"));
        $this->assertSame('ecole', $this->stringService->slug("e\u{0301}cole"));
    }

    public function testSlugNeverStartsOrEndsWithTheSeparator()
    {
        foreach (['-a-', '_a_', '/a/', ' a ', '.a.'] as $input) {
            $this->assertSame('a', $this->stringService->slug($input), "slug of '{$input}'");
        }
    }

    public function testSlugIsIdempotent()
    {
        $slug = $this->stringService->slug('Hello, World_of/PHP & More!');

        $this->assertSame($slug, $this->stringService->slug($slug));
    }

    // ---- studly / camel ---------------------------------------------------

    public function testStudlyKeepsExistingHumps()
    {
        $this->assertSame('FooBar', $this->stringService->studly('fooBar'));
        $this->assertSame('FooBarBaz', $this->stringService->studly('fooBar_baz'));
        $this->assertSame('IPhoneCase', $this->stringService->studly('iPhone case'));
        $this->assertSame('McDonald', $this->stringService->studly('mcDonald'));
        $this->assertSame('XMLParser', $this->stringService->studly('XMLParser'));
    }

    public function testStudlyStillNormalisesWordsWithoutHumps()
    {
        $this->assertSame('HelloWorld', $this->stringService->studly('hello_world'));
        $this->assertSame('HelloWorld', $this->stringService->studly('HELLO_WORLD'));
        $this->assertSame('HelloWorld', $this->stringService->studly('Hello-World'));
        $this->assertSame('UserId', $this->stringService->studly('user_ID'));
        $this->assertSame('HelloWorld', $this->stringService->studly('hello world'));
    }

    public function testStudlyHandlesMessySeparatorsAndEmptyInput()
    {
        $this->assertSame('', $this->stringService->studly(''));
        $this->assertSame('', $this->stringService->studly('___'));
        $this->assertSame('HelloWorld', $this->stringService->studly('__hello__world__'));
        $this->assertSame('HelloWorld', $this->stringService->studly("hello\t\nworld"));
    }

    public function testStudlyHandlesUnicode()
    {
        $this->assertSame('ÉcoleNom', $this->stringService->studly('école_nom'));
        $this->assertSame('ÉcoleNom', $this->stringService->studly('écoleNom'));
        $this->assertSame('ПриветМир', $this->stringService->studly('привет_мир'));
    }

    public function testStudlyKeepsDigits()
    {
        $this->assertSame('User2Name', $this->stringService->studly('user2_name'));
        $this->assertSame('Create2024Table', $this->stringService->studly('create_2024_table'));
    }

    public function testCamelKeepsExistingHumps()
    {
        $this->assertSame('fooBar', $this->stringService->camel('fooBar'));
        $this->assertSame('fooBarBaz', $this->stringService->camel('FooBar_baz'));
        $this->assertSame('iPhoneCase', $this->stringService->camel('iPhone case'));
    }

    public function testCamelKeepsSnakeAndKebabBehaviour()
    {
        $this->assertSame('helloWorld', $this->stringService->camel('hello_world'));
        $this->assertSame('helloWorld', $this->stringService->camel('hello-world'));
        $this->assertSame('helloWorld', $this->stringService->camel('HELLO_WORLD'));
        $this->assertSame('helloWorld', $this->stringService->camel('Hello_World'));
        $this->assertSame('userId', $this->stringService->camel('user_ID'));
    }

    public function testCamelHandlesEmptyAndUnicodeInput()
    {
        $this->assertSame('', $this->stringService->camel(''));
        $this->assertSame('écoleNom', $this->stringService->camel('école_nom'));
        $this->assertSame('écoleNom', $this->stringService->camel('École_Nom'));
    }

    public function testCamelOfAMigrationStyleName()
    {
        // Migrator::getMigrationClass() derives the class name this way.
        $this->assertSame('20240101CreateUsersTable', $this->stringService->camel('2024_01_01_create_users_table'));
        $this->assertSame('20240101CreateUsersTable', $this->stringService->camel('2024_01_01_CreateUsersTable'));
    }

    public function testCamelAndSnakeRoundTripForPlainNames()
    {
        foreach (['hello_world', 'user_profile_image', 'order_item'] as $name) {
            $this->assertSame($name, $this->stringService->snake($this->stringService->camel($name)));
        }
    }

    // ---- snake digits -----------------------------------------------------

    public function testSnakeStartsANewWordAfterADigit()
    {
        $this->assertSame('user1_name', $this->stringService->snake('user1Name'));
        $this->assertSame('address2_line', $this->stringService->snake('address2Line'));
        $this->assertSame('hello_world2_go', $this->stringService->snake('HelloWorld2Go'));
        $this->assertSame('level10_boss', $this->stringService->snake('level10Boss'));
    }

    public function testSnakeLeavesTrailingAndLeadingDigitsAlone()
    {
        $this->assertSame('post2', $this->stringService->snake('Post2'));
        $this->assertSame('version2', $this->stringService->snake('version2'));
        $this->assertSame('2fa_code', $this->stringService->snake('2faCode'));
        $this->assertSame('user_2', $this->stringService->snake('user_2'));
    }

    public function testSnakeIsIdempotent()
    {
        foreach (['userName', 'address2Line', 'HelloWorld2Go', 'école_nom'] as $input) {
            $once = $this->stringService->snake($input);

            $this->assertSame($once, $this->stringService->snake($once), "snake of '{$input}'");
        }
    }

    public function testSnakeKeepsTheNamesTheFrameworkRelyOn()
    {
        // Table names and computed-property keys come from snake().
        $this->assertSame('blog_post', $this->stringService->snake('BlogPost'));
        $this->assertSame('full_name', $this->stringService->snake('fullName'));
        $this->assertSame('post_tags', $this->stringService->snake('PostTags'));
        $this->assertSame('success', $this->stringService->snake('success'));
    }

    // ---- between ----------------------------------------------------------

    public function testBetweenReturnsNestedDelimitersUpToTheLastClosingMatch()
    {
        $this->assertSame('a[b]c', $this->stringService->between('[a[b]c]', '[', ']'));
        $this->assertSame('x(y)z', $this->stringService->between('f(x(y)z)', '(', ')'));
    }

    public function testBetweenWithASingleDelimitedValue()
    {
        $this->assertSame('b', $this->stringService->between('a [b] c', '[', ']'));
        $this->assertSame('大阪', $this->stringService->between('東京-大阪-名古屋', '東京-', '-名古屋'));
    }

    public function testBetweenSpansEverythingUpToTheLastClosingDelimiter()
    {
        $this->assertSame('b] c [d', $this->stringService->between('a [b] c [d] e', '[', ']'));
    }

    public function testBetweenWithMultiCharacterDelimiters()
    {
        $this->assertSame('x', $this->stringService->between('<<x>>', '<<', '>>'));
        $this->assertSame('a>>b', $this->stringService->between('<<a>>b>>', '<<', '>>'));
    }

    public function testBetweenWithAMissingClosingDelimiterReturnsTheRest()
    {
        $this->assertSame('b', $this->stringService->between('a [b', '[', ']'));
    }

    public function testBetweenWithAMissingOpeningDelimiterStartsAtTheBeginning()
    {
        $this->assertSame('abc', $this->stringService->between('abc]def', '[', ']'));
    }

    public function testBetweenWithNeitherDelimiterReturnsTheSubject()
    {
        $this->assertSame('no-delims', $this->stringService->between('no-delims', '[', ']'));
    }

    public function testBetweenWithAnEmptyDelimiterReturnsTheSubject()
    {
        $this->assertSame('abc', $this->stringService->between('abc', '', ']'));
        $this->assertSame('abc', $this->stringService->between('abc', '[', ''));
        $this->assertSame('', $this->stringService->between('', '[', ']'));
    }

    public function testBetweenWithAdjacentDelimitersIsEmpty()
    {
        $this->assertSame('', $this->stringService->between('a[]b', '[', ']'));
    }

    public function testBetweenWithIdenticalOpeningAndClosingDelimiters()
    {
        $this->assertSame('b', $this->stringService->between('a"b"c', '"', '"'));
    }

    // ---- countWord numbers ------------------------------------------------

    public function testCountWordCountsNumbers()
    {
        $this->assertSame(4, $this->stringService->countWord('I have 2 cats'));
        $this->assertSame(1, $this->stringService->countWord('2024'));
        $this->assertSame(3, $this->stringService->countWord('1 2 3'));
    }

    public function testCountWordKeepsDecimalsAndThousandsTogether()
    {
        $this->assertSame(3, $this->stringService->countWord('pi is 3.14.'));
        $this->assertSame(2, $this->stringService->countWord('1,000,000 users'));
        $this->assertSame(2, $this->stringService->countWord('version v2.0'));
    }

    public function testCountWordKeepsLettersAndDigitsInOneWord()
    {
        $this->assertSame(2, $this->stringService->countWord('2nd place'));
        $this->assertSame(1, $this->stringService->countWord('abc123'));
        $this->assertSame(2, $this->stringService->countWord('covid-19 cases'));
    }

    public function testCountWordStillSplitsLettersOnPunctuation()
    {
        $this->assertSame(2, $this->stringService->countWord('a.b'));
        $this->assertSame(3, $this->stringService->countWord('a,b,c'));
        $this->assertSame(2, $this->stringService->countWord('Hello, World!'));
    }

    public function testCountWordKeepsApostrophesAndUnicode()
    {
        $this->assertSame(2, $this->stringService->countWord("don't stop"));
        $this->assertSame(2, $this->stringService->countWord('привет мир'));
        $this->assertSame(1, $this->stringService->countWord('héllo'));
    }

    public function testCountWordOfNothingIsZero()
    {
        $this->assertSame(0, $this->stringService->countWord(''));
        $this->assertSame(0, $this->stringService->countWord('   '));
        $this->assertSame(0, $this->stringService->countWord('!!! ... ---'));
    }

    public function testCountWordIgnoresATrailingSeparatorAfterANumber()
    {
        $this->assertSame(1, $this->stringService->countWord('100.'));
        $this->assertSame(1, $this->stringService->countWord('100,'));
        $this->assertSame(2, $this->stringService->countWord('100.,5'));
    }

    public function testStringServiceConformsToTheContract()
    {
        $this->assertInstanceOf(StringServiceInterface::class, $this->stringService);
    }

    public function testApplicationBindsTheStringContractAndLegacyServiceNameToTheSameSingleton()
    {
        $app = $this->getMockBuilder(Application::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        (new ReflectionClass(Container::class))->getConstructor()->invoke($app);
        $previous = Container::getInstance();
        Container::setInstance($app);
        $originalAppUrl = getenv('APP_URL');

        try {
            putenv('APP_URL=http://localhost');
            (new FacadeLauncher($app))->register();

            $contract = $app->get(StringServiceInterface::class);

            $this->assertInstanceOf(StringService::class, $contract);
            $this->assertSame($contract, app('str'));
            $this->assertSame($contract, str());
        } finally {
            Container::setInstance($previous);
            $originalAppUrl === false ? putenv('APP_URL') : putenv("APP_URL={$originalAppUrl}");
        }
    }
}
