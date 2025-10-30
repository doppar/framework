<?php

namespace Tests\Unit\Translator;

use Phaseolies\Translation\Translator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Phaseolies\Translation\FileLoader;
use Tests\Support\MockContainer;
use Phaseolies\DI\Container;
use Phaseolies\Config\Config;

class TranslatorTest extends TestCase
{
    /** @var MockObject&FileLoader */
    private $loader;

    private Translator $translator;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());

        // Mock the FileLoader dependency
        $this->loader = $this->createMock(FileLoader::class);
        $this->translator = new Translator($this->loader, "en");
        $this->translator->setFallback("fr");
    }

    /* -------------------------------------------------------------
     |  Basic Locale and Fallback Behavior
     | ------------------------------------------------------------ */

    public function testSetAndGetLocale(): void
    {
        $this->translator->setLocale("es");
        $this->assertSame("es", $this->translator->getLocale());
    }

    public function testSetAndGetFallback(): void
    {
        $this->translator->setFallback("de");
        $this->assertSame("de", $this->translator->getFallback());
    }

    /* -------------------------------------------------------------
     |  Key Parsing Logic
     | ------------------------------------------------------------ */

    public function testParseKeyForNamespacedKey(): void
    {
        $method = new \ReflectionClass($this->translator)->getMethod("parseKey");
        $method->setAccessible(true);

        $this->assertSame(
            ["auth", "messages", "welcome"],
            $method->invoke($this->translator, "auth::messages.welcome")
        );
        $this->assertSame(
            ["auth", "*", "plain"],
            $method->invoke($this->translator, "auth::plain")
        );
        $this->assertSame(
            [null, "messages", "welcome"],
            $method->invoke($this->translator, "messages.welcome")
        );
        $this->assertSame(
            [null, "*", "simple"],
            $method->invoke($this->translator, "simple")
        );
    }

    public function testParseNamespacedKeyMethod(): void
    {
        $method = new \ReflectionClass($this->translator)->getMethod("parseNamespacedKey");
        $method->setAccessible(true);

        $this->assertSame(
            ["auth", "messages", "welcome"],
            $method->invoke($this->translator, "auth::messages.welcome")
        );
        $this->assertSame(
            ["auth", null, "plain"],
            $method->invoke($this->translator, "auth::plain")
        );
        $this->assertSame(
            [null, "*", "basic"],
            $method->invoke($this->translator, "basic")
        );
    }

    /* -------------------------------------------------------------
     |  Translation Loading and Retrieval
     | ------------------------------------------------------------ */

    public function testGetReturnsTranslatedLine(): void
    {
        $this->loader
            ->expects($this->once())
            ->method("load")
            ->with("en", "messages", null)
            ->willReturn(["welcome" => "Hello :name!"]);

        $result = $this->translator->get("messages.welcome", [
            "name" => "John",
        ]);
        $this->assertSame("Hello John!", $result);
    }

    public function testTransIsAliasForGet(): void
    {
        $this->loader
            ->expects($this->once())
            ->method("load")
            ->willReturn(["bye" => "Goodbye"]);

        $this->assertSame("Goodbye", $this->translator->trans("messages.bye"));
    }

    public function testFallbackLocaleIsUsedWhenMissingInPrimary(): void
    {
        $this->loader
            ->expects($this->exactly(2))
            ->method("load")
            ->willReturnMap([
                ["en", "messages", null, ["welcome" => null]], // missing in en
                ["fr", "messages", null, ["welcome" => "Bonjour"]]
            ]);

        $this->translator->setFallback("fr");

        $this->assertSame(
            "Bonjour",
            $this->translator->get("messages.welcome")
        );
    }

    public function testReturnsKeyWhenNoTranslationFound(): void
    {
        $this->loader
            ->expects($this->exactly(2))
            ->method("load")
            ->willReturn([]);

        $result = $this->translator->get("unknown.key");
        $this->assertSame("unknown.key", $result);
    }

    public function testGetHandlesNamespacedKeys(): void
    {
        $this->loader
            ->expects($this->once())
            ->method("load")
            ->with("en", "messages", "auth")
            ->willReturn(["welcome" => "Hi there"]);

        $result = $this->translator->get("auth::messages.welcome");
        $this->assertSame("Hi there", $result);
    }

    public function testGetReturnsKeyIfGroupIsWildcard(): void
    {
        $this->assertSame("simple", $this->translator->get("simple"));
    }

    /* -------------------------------------------------------------
     |  Replacement Behavior
     | ------------------------------------------------------------ */

    public function testMakeReplacementsHandlesCaseVariants(): void
    {
        $line = "Hello :name, :NAME, and :Name!";
        $result = $this->translator->makeReplacements($line, [
            "name" => "john"
        ]);
        $this->assertSame("Hello john, JOHN, and John!", $result);
    }

    /* -------------------------------------------------------------
     |  getLine() Edge Cases
     | ------------------------------------------------------------ */

    public function testGetLineReturnsArrayWhenNested(): void
    {
        $this->loader->method("load")->willReturn([
            "menu" => ["file" => ["new" => "New File"]],
        ]);

        // Load once
        $this->translator->get("messages.menu.file.new"); // preload group
        // Access getLine directly
        $method = new \ReflectionClass($this->translator)->getMethod("getLine");
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->translator,
            null,
            "messages",
            "en",
            "menu.file",
            []
        );
        $this->assertIsArray($result);
        $this->assertArrayHasKey("new", $result);
    }

    public function testGetLineReturnsNullWhenNotFound(): void
    {
        $this->loader->method("load")->willReturn(["menu" => ["file" => []]]);
        $method = new \ReflectionClass($this->translator)->getMethod("getLine");
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->translator,
            null,
            "messages",
            "en",
            "missing.item",
            []
        );
        $this->assertNull($result);
    }

    /* -------------------------------------------------------------
     |  Load and Caching
     | ------------------------------------------------------------ */

    public function testLoadTranslationsIsCached(): void
    {
        $this->loader
            ->expects($this->once())
            ->method("load")
            ->willReturn(["key" => "value"]);

        // First call loads
        $this->translator->loadTranslations(null, "messages", "en");
        // Second call should hit cache (no new load)
        $this->translator->loadTranslations(null, "messages", "en");

        $this->assertTrue(true); // no exception → cached successfully
    }

    public function testIsLoadedDetectsAlreadyLoadedGroups(): void
    {
        $ref = new \ReflectionClass($this->translator);
        $prop = $ref->getProperty("loaded");
        $prop->setAccessible(true);
        $prop->setValue($this->translator, [
            null => ["messages" => ["en" => ["welcome" => "hi"]]],
        ]);

        $method = $ref->getMethod("isLoaded");
        $method->setAccessible(true);

        $this->assertTrue(
            $method->invoke($this->translator, null, "messages", "en")
        );
        $this->assertFalse(
            $method->invoke($this->translator, null, "other", "en")
        );
    }

    public function testConstructorUsesConfigFallbackLocale(): void
    {
        // Set config fallback locale
        Config::set('app.fallback_locale', 'es');

        $loader = $this->createMock(FileLoader::class);
        $translator = new Translator($loader, 'en');

        $this->assertEquals('es', $translator->getFallback());

        // Clean up
        Config::set('app.fallback_locale', null);
    }

    public function testGetWithNestedKeys(): void
    {
        $this->loader->method('load')
            ->willReturn([
                'user' => [
                    'profile' => [
                        'name' => 'User Name',
                        'email' => 'User Email'
                    ]
                ]
            ]);

        $result = $this->translator->get('messages.user.profile.name');

        $this->assertEquals('User Name', $result);
    }

    public function testGetWithMultipleReplacements(): void
    {
        $this->loader->method('load')
            ->willReturn([
                'welcome' => 'Welcome, :name!',
                'age_message' => 'You are :age years old.',
                'full_message' => 'Hello :name, you are :age years old.'
            ]);

        $result1 = $this->translator->get('messages.welcome', ['name' => 'John']);
        $result2 = $this->translator->get('messages.age_message', ['age' => '25']);
        $result3 = $this->translator->get('messages.full_message', ['name' => 'Jane', 'age' => '30']);

        $this->assertEquals('Welcome, John!', $result1);
        $this->assertEquals('You are 25 years old.', $result2);
        $this->assertEquals('Hello Jane, you are 30 years old.', $result3);
    }

    public function testGetWithCaseReplacements(): void
    {
        $this->loader->method('load')
            ->willReturn([
                'welcome' => 'Welcome, :Name! Your role is :ROLE.'
            ]);

        $result = $this->translator->get('messages.welcome', ['name' => 'john', 'role' => 'admin']);

        $this->assertEquals('Welcome, John! Your role is ADMIN.', $result);
    }

    public function testGetWithDifferentLocaleParameter(): void
    {
        $this->loader->method('load')
            ->willReturnCallback(function ($locale, $group, $namespace) {
                if ($locale === 'es') {
                    return ['welcome' => '¡Bienvenido!'];
                }
                return ['welcome' => 'Welcome!'];
            });

        $result = $this->translator->get('messages.welcome', [], 'es');

        $this->assertEquals('¡Bienvenido!', $result);
    }

    public function testGetReturnsKeyWhenNoTranslationFoundAnywhere(): void
    {
        $this->loader->method('load')
            ->willReturn([]);

        $result = $this->translator->get('messages.nonexistent');

        $this->assertEquals('messages.nonexistent', $result);
    }

    public function testParseKeyWithNamespacedSingleWord(): void
    {
        $method = new \ReflectionClass($this->translator)->getMethod('parseKey');
        $method->setAccessible(true);

        $result = $method->invoke($this->translator, 'package::welcome');

        $this->assertEquals(['package', '*', 'welcome'], $result);
    }

    public function testParseNamespacedKeyWithInvalidFormat(): void
    {
        $method = new \ReflectionClass($this->translator)->getMethod('parseNamespacedKey');
        $method->setAccessible(true);

        $result = $method->invoke($this->translator, 'invalidkey');

        $this->assertEquals([null, '*', 'invalidkey'], $result);
    }

    public function testGetLineReturnsArrayForNestedTranslation(): void
    {
        $this->loader->method('load')
            ->willReturn([
                'user' => [
                    'name' => 'User Name',
                    'email' => 'user@example.com'
                ]
            ]);

        $method = new \ReflectionClass($this->translator)->getMethod('getLine');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->translator,
            null,
            'messages',
            'en',
            'user',
            []
        );

        $this->assertIsArray($result);
        $this->assertEquals('User Name', $result['name']);
        $this->assertEquals('user@example.com', $result['email']);
    }

    public function testGetLineReturnsNullForNonExistentItem(): void
    {
        $this->loader->method('load')
            ->willReturn(['welcome' => 'Welcome!']);

        $method = new \ReflectionClass($this->translator)->getMethod('getLine');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->translator,
            null,
            'messages',
            'en',
            'nonexistent',
            []
        );

        $this->assertNull($result);
    }

    public function testMakeReplacements(): void
    {
        $line = 'Hello :name, you have :count messages.';
        $replace = ['name' => 'John', 'count' => '5'];

        $result = $this->translator->makeReplacements($line, $replace);

        $this->assertEquals('Hello John, you have 5 messages.', $result);
    }

    public function testLoadTranslationsOnlyOnce(): void
    {
        $this->loader->expects($this->once())
            ->method('load')
            ->with('en', 'messages', null)
            ->willReturn(['welcome' => 'Welcome!']);

        // Call multiple times - should only load once
        $this->translator->loadTranslations(null, 'messages', 'en');
        $this->translator->loadTranslations(null, 'messages', 'en');
        $this->translator->loadTranslations(null, 'messages', 'en');

        // Verify it's loaded
        $reflection = new \ReflectionClass($this->translator);
        $loadedProperty = $reflection->getProperty('loaded');
        $loadedProperty->setAccessible(true);
        $loaded = $loadedProperty->getValue($this->translator);

        $this->assertArrayHasKey(null, $loaded);
        $this->assertArrayHasKey('messages', $loaded[null]);
        $this->assertArrayHasKey('en', $loaded[null]['messages']);
    }

    public function testGetWithDeeplyNestedKeys(): void
    {
        $this->loader->method('load')
            ->willReturn([
                'level1' => [
                    'level2' => [
                        'level3' => [
                            'level4' => 'Deep Value'
                        ]
                    ]
                ]
            ]);

        $result = $this->translator->get('messages.level1.level2.level3.level4');

        $this->assertEquals('Deep Value', $result);
    }

    public function testGetReturnsEmptyStringForEmptyTranslation(): void
    {
        $this->loader->method('load')
            ->willReturn(['empty' => '']);

        $result = $this->translator->get('messages.empty');

        $this->assertEquals('', $result);
    }

    public function testGetWithSpecialCharactersInReplacements(): void
    {
        $this->loader->method('load')
            ->willReturn([
                'message' => 'Hello :name! Your email is :email.'
            ]);

        $result = $this->translator->get('messages.message', [
            'name' => 'John & Jane',
            'email' => 'test@example.com'
        ]);

        $this->assertEquals('Hello John & Jane! Your email is test@example.com.', $result);
    }

    public function testGetWithNoFallbackWhenSameAsLocale(): void
    {
        $this->loader->method('load')
            ->willReturn([]); // No translations

        $this->translator->setLocale('en');
        $this->translator->setFallback('en'); // Same as locale

        $result = $this->translator->get('messages.nonexistent');

        $this->assertEquals('messages.nonexistent', $result);
    }

    public function testGetWithNullLocaleUsesCurrent(): void
    {
        $this->loader->method('load')
            ->with('fr', 'messages', null)
            ->willReturn(['welcome' => 'Bienvenue!']);

        $this->translator->setLocale('fr');
        $result = $this->translator->get('messages.welcome', [], null);

        $this->assertEquals('Bienvenue!', $result);
    }

    public function testMultipleGroupsAreLoadedSeparately(): void
    {
        $this->loader->expects($this->exactly(2))
            ->method('load')
            ->willReturnMap([
                ['en', 'messages', null, ['welcome' => 'Welcome!']],
                ['en', 'validation', null, ['required' => 'This field is required.']]
            ]);

        $result1 = $this->translator->get('messages.welcome');
        $result2 = $this->translator->get('validation.required');

        $this->assertEquals('Welcome!', $result1);
        $this->assertEquals('This field is required.', $result2);
    }

    public function testGetWithNamespace(): void
    {
        $this->loader->method('load')
            ->with('en', 'messages', 'package')
            ->willReturn(['welcome' => 'Package Welcome!']);

        $result = $this->translator->get('package::messages.welcome');

        $this->assertEquals('Package Welcome!', $result);
    }
}
