<?php

namespace Tests\Unit\Support\Validation;

use Phaseolies\Translation\Translator;
use Phaseolies\Translation\FileLoader;
use Phaseolies\Support\Validation\Sanitizer;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;

class RequestValidationTest extends TestCase
{
    private Sanitizer $sanitizer;

    protected function setUp(): void
    {
        $container = new Container();
        $container->bind('translator', function(){
            return new Translator(new FileLoader('/'),'en');
        });
    }

    public function testConstructorInitializesProperties()
    {
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        $rules = ['name' => 'required|string', 'email' => 'required|email'];

        $sanitizer = new Sanitizer($data, $rules);

        $reflection = new \ReflectionClass($sanitizer);

        $dataProperty = $reflection->getProperty('data');
        $dataProperty->setAccessible(true);

        $rulesProperty = $reflection->getProperty('rules');
        $rulesProperty->setAccessible(true);

        $this->assertEquals($data, $dataProperty->getValue($sanitizer));
        $this->assertEquals($rules, $rulesProperty->getValue($sanitizer));
    }

    public function testRequestStaticFactoryMethod()
    {
        $data = ['name' => 'John'];
        $rules = ['name' => 'required'];

        $sanitizer = (new Sanitizer($data, $rules))->request($data, $rules);

        $this->assertInstanceOf(Sanitizer::class, $sanitizer);
    }

    public function testValidateReturnsTrueForValidData()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => '25'
        ];

        $rules = [
            'name' => 'required|string',
            'email' => 'required|email',
            'age' => 'required|numeric'
        ];

        $sanitizer = new Sanitizer($data, $rules);

        $this->assertEmpty($sanitizer->errors()['errors']);
    }

    public function testAddErrorAccumulatesMultipleErrorsForSameField()
    {
        $sanitizer = new Sanitizer([], []);

        $reflection = new \ReflectionClass($sanitizer);
        $method = $reflection->getMethod('addError');
        $method->setAccessible(true);

        $method->invoke($sanitizer, 'email', 'Email is required');
        $method->invoke($sanitizer, 'email', 'Email must be valid');

        $errors = $sanitizer->errors()['errors'];

        $this->assertArrayHasKey('email', $errors);
        $this->assertCount(2, $errors['email']);
        $this->assertEquals('Email is required', $errors['email'][0]);
        $this->assertEquals('Email must be valid', $errors['email'][1]);
    }

    public function testEmptyRulesArray()
    {
        $data = ['field' => 'value'];
        $rules = [];

        $sanitizer = new Sanitizer($data, $rules);

        $this->assertTrue($sanitizer->validate());
        $this->assertEmpty($sanitizer->errors()['errors']);
        $this->assertEmpty($sanitizer->passed());
    }
}
