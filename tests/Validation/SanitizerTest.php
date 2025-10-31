<?php

namespace Tests\Unit;

use Tests\Support\MockContainer;
use Phaseolies\Translation\Translator;
use Phaseolies\Translation\FileLoader;
use Phaseolies\Support\Validation\Sanitizer;
use Phaseolies\Http\Support\ValidationRules;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;

class SanitizerTest extends TestCase
{
    private Sanitizer $sanitizer;
    private Translator $translator;

    protected function setUp(): void
    {
        parent::setUp();
        Container::setInstance(new MockContainer());
        $container = new Container();
        $container->bind('translator', function(){
            // Mock the FileLoader dependency
            $loader = $this->createMock(FileLoader::class);

            return new Translator($loader, "en");
        });

        // Mock the translator
        $this->translator = $this->createMock(Translator::class);
        $this->translator->method('get')->willReturnCallback(function ($key, $replace = [], $default = null) {
            $messages = [
                'validation.required' => 'The :attribute field is required.',
                'validation.email' => 'The :attribute must be a valid email address.',
                'validation.min.string' => 'The :attribute must be at least :min characters.',
                'validation.max.string' => 'The :attribute may not be greater than :max characters.',
                'validation.unique' => 'The :attribute has already been taken.',
                'validation.date' => 'The :attribute is not a valid date.',
                'validation.int' => 'The :attribute must be an integer.',
                'validation.float' => 'The :attribute must be a float with :decimal decimal places.',
                'validation.between' => 'The :attribute must be between :min and :max.',
                'validation.same_as' => 'The :attribute must match :other.',
                'validation.attributes.email' => 'email address',
                'validation.attributes.password' => 'password',
            ];

            return $messages[$key] ?? $default ?? $key;
        });

        // Mock the app function
        if (!function_exists('app')) {
            function app($abstract = null)
            {
                static $container = [];
                if ($abstract === 'translator') {
                    return $container['translator'] ?? new class {
                        public function get($key, $replace = [], $default = null)
                        {
                            return $default ?? $key;
                        }
                    };
                }
                return $container[$abstract] ?? null;
            }
        }
    }

    public function testConstructorAndRequestMethod(): void
    {
        $data = ['name' => 'John'];
        $rules = ['name' => 'required'];

        $sanitizer = new Sanitizer($data, $rules);
        $this->assertInstanceOf(Sanitizer::class, $sanitizer);

        $staticSanitizer = $sanitizer->request($data, $rules);
        $this->assertInstanceOf(Sanitizer::class, $staticSanitizer);
    }

    public function testValidateWithRequiredRule(): void
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
        $this->assertNotEmpty($sanitizer->errors());
    }

    public function testFailsMethod(): void
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->fails();

        $this->assertTrue($result);
    }

    public function testErrorsMethod(): void
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];

        $sanitizer = new Sanitizer($data, $rules);
        $sanitizer->validate();
        $errors = $sanitizer->errors();

        $this->assertArrayHasKey('message', $errors);
        $this->assertArrayHasKey('errors', $errors);
        $this->assertArrayHasKey('name', $errors['errors']);
    }

    public function testPassedMethod(): void
    {
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        $rules = ['name' => 'required', 'email' => 'required'];

        $sanitizer = new Sanitizer($data, $rules);
        $passed = $sanitizer->passed();

        $this->assertEquals($data, $passed);
    }

    public function testEmailValidation(): void
    {
        $data = ['email' => 'invalid-email'];
        $rules = ['email' => 'email'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
    }

    public function testValidEmail(): void
    {
        $data = ['email' => 'valid@example.com'];
        $rules = ['email' => 'email'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertTrue($result);
    }

    public function testMinLengthValidation(): void
    {
        $data = ['password' => '123'];
        $rules = ['password' => 'min:6'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
    }

    public function testMaxLengthValidation(): void
    {
        $data = ['name' => 'ThisIsAVeryLongName'];
        $rules = ['name' => 'max:10'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
    }

    public function testIntegerValidation(): void
    {
        $data = ['age' => 'not-an-integer'];
        $rules = ['age' => 'int'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
    }

    public function testValidInteger(): void
    {
        $data = ['age' => '25'];
        $rules = ['age' => 'int'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertTrue($result);
    }

    public function testFloatValidation(): void
    {
        $data = ['price' => 'not-a-float'];
        $rules = ['price' => 'float:2'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
    }

    public function testValidFloat(): void
    {
        $data = ['price' => '19.99'];
        $rules = ['price' => 'float:2'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertTrue($result);
    }

    public function testBetweenValidation(): void
    {
        $data = ['score' => '1'];
        $rules = ['score' => 'between:5,10'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
    }

    public function testValidBetween(): void
    {
        $data = ['score' => '7'];
        $rules = ['score' => 'between:5,10'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertTrue($result);
    }

    public function testSameAsValidation(): void
    {
        $data = ['password' => '123456', 'password_confirmation' => 'different'];
        $rules = ['password' => 'same_as:password_confirmation'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
    }

    public function testValidSameAs(): void
    {
        $data = ['password' => '123456', 'password_confirmation' => '123456'];
        $rules = ['password' => 'same_as:password_confirmation'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertTrue($result);
    }

    public function testDateValidation(): void
    {
        $data = ['birth_date' => 'invalid-date'];
        $rules = ['birth_date' => 'date'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
    }

    public function testValidDate(): void
    {
        $data = ['birth_date' => '2023-12-25'];
        $rules = ['birth_date' => 'date'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertTrue($result);
    }

    public function testNullableField(): void
    {
        $data = ['optional_field' => ''];
        $rules = ['optional_field' => 'null|email'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertTrue($result);
    }

    public function testMultipleRules(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => '25'
        ];
        $rules = [
            'name' => 'required|min:2|max:50',
            'email' => 'required|email',
            'age' => 'required|int'
        ];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertTrue($result);
    }

    public function testMultipleRulesWithErrors(): void
    {
        $data = [
            'name' => 'J', // Too short
            'email' => 'invalid-email',
            'age' => 'not-a-number'
        ];
        $rules = [
            'name' => 'required|min:2|max:50',
            'email' => 'required|email',
            'age' => 'required|int'
        ];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
        $errors = $sanitizer->errors();
        $this->assertCount(3, $errors['errors']);
    }

    public function testRuleWithParameters(): void
    {
        $data = ['username' => 'ab'];
        $rules = ['username' => 'min:3|max:10'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
    }

    public function testNonExistentField(): void
    {
        $data = ['existing_field' => 'value'];
        $rules = ['non_existent_field' => 'required'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
    }

    public function testEmptyDataWithRequiredFields(): void
    {
        $data = [];
        $rules = ['name' => 'required', 'email' => 'required'];

        $sanitizer = new Sanitizer($data, $rules);
        $result = $sanitizer->validate();

        $this->assertFalse($result);
        $errors = $sanitizer->errors();
        $this->assertCount(2, $errors['errors']);
    }
}

// Test class for ValidationRules trait
class ValidationRulesTest
{
    use ValidationRules;

    public function testGetAttributeName(): void
    {
        $result = $this->getAttributeName('user_name');
        $this->assertEquals('User name', $result);
    }

    public function testRemoveUnderscore(): void
    {
        $result = $this->_removeUnderscore('user_name_test');
        $this->assertEquals('user name test', $result);
    }

    public function testRemoveRuleSuffix(): void
    {
        $result = $this->_removeRuleSuffix('min:6');
        $this->assertEquals('min', $result);
    }

    public function testGetRuleSuffix(): void
    {
        $result = $this->_getRuleSuffix('min:6');
        $this->assertEquals('6', $result);

        $result = $this->_getRuleSuffix('required');
        $this->assertNull($result);
    }

    public function testParseSizeRule(): void
    {
        $result = $this->parseSizeRule('2M');
        $this->assertEquals(2097152, $result); // 2 * 1024 * 1024

        $result = $this->parseSizeRule('100K');
        $this->assertEquals(102400, $result); // 100 * 1024

        $result = $this->parseSizeRule('500');
        $this->assertEquals(500, $result);
    }

    public function testFormatBytes(): void
    {
        $result = $this->formatBytes(1024);
        $this->assertEquals('1 KB', $result);

        $result = $this->formatBytes(2097152);
        $this->assertEquals('2 MB', $result);

        $result = $this->formatBytes(500);
        $this->assertEquals('500 B', $result);
    }

    public function testParseDimensionsRule(): void
    {
        $result = $this->parseDimensionsRule('min_width=100,min_height=200');
        $this->assertEquals(['min_width' => 100, 'min_height' => 200], $result);

        $result = $this->parseDimensionsRule('invalid');
        $this->assertNull($result);
    }
}
