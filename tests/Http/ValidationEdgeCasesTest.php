<?php

namespace Tests\Unit\Http;

require_once __DIR__ . '/../Support/MockContainer.php';

use Phaseolies\DI\Container;
use Phaseolies\Http\Request;
use Phaseolies\Support\StringService;
use Phaseolies\Support\Validation\Sanitizer;
use Phaseolies\Translation\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\MockContainer;

class ValidationEdgeCasesTest extends TestCase
{
    protected function setUp(): void
    {
        $container = new MockContainer();
        Container::setInstance($container);
        $container->bind('str', StringService::class);
        $container->bind('translator', fn() => new class extends Translator {
            public function __construct()
            {
            }

            public function get($key, $replace = [], $locale = null): mixed
            {
                return $key;
            }

            public function trans($key, $replace = [], $locale = null): string
            {
                return $key;
            }
        });

        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/', 'HTTP_HOST' => 'example.com', 'REMOTE_ADDR' => '127.0.0.1', 'SCRIPT_NAME' => '/index.php', 'SERVER_NAME' => 'example.com', 'SERVER_PORT' => '80'];
        $_GET = $_POST = $_COOKIE = $_FILES = [];

        $request = Request::createFromGlobals();
        $container->bind('request', fn() => $request);
    }

    protected function tearDown(): void
    {
        Container::forgetInstance();
    }

    public static function ruleProvider(): array
    {
        return [
            'min counts characters' => ['min:3', 'আম', false],
            'min accepts enough characters' => ['min:3', 'আমি', true],
            'max counts characters' => ['max:3', 'আমি', true],
            'max rejects too many characters' => ['max:3', 'abcd', false],
            'size counts characters' => ['size:3', 'আমি', true],
            'date rejects February 30' => ['date', '2026-02-30', false],
            'date accepts a real date' => ['date', '2026-02-28', true],
            'date accepts a date and time' => ['date', '2026-02-28 10:30:00', true],
            'date accepts a relative date' => ['date', 'next monday', true],
            'in accepts a JSON number' => ['in:1,2', 1, true],
            'in rejects another JSON number' => ['in:1,2', 3, false],
            'in accepts a string' => ['in:1,2', '1', true],
            'not_in rejects a JSON number' => ['not_in:1,2', 1, false],
            'not_in accepts another JSON number' => ['not_in:1,2', 3, true],
            'alpha rejects a trailing newline' => ['alpha', "abc\n", false],
            'alpha_num rejects a trailing newline' => ['alpha_num', "abc1\n", false],
            'alpha_dash rejects a trailing newline' => ['alpha_dash', "a-b\n", false],
            'slug rejects a trailing newline' => ['slug', "a-b\n", false],
            'digits rejects a trailing newline' => ['digits:3', "123\n", false],
            'min_digits rejects a trailing newline' => ['min_digits:2', "123\n", false],
            'max_digits rejects a trailing newline' => ['max_digits:5', "123\n", false],
            'phone rejects a trailing newline' => ['phone', "+8801711111111\n", false],
            'uuid rejects a trailing newline' => ['uuid', "550e8400-e29b-41d4-a716-446655440000\n", false],
            'int rejects a trailing newline' => ['int', "12\n", false],
            'int accepts a number' => ['int', '12', true],
            'int accepts a JSON number' => ['int', 12, true],
            'numeric rejects a trailing newline' => ['numeric', "12\n", false],
            'numeric accepts a JSON number' => ['numeric', 1.5, true],
            'alpha still accepts letters' => ['alpha', 'abc', true],
            'required rejects whitespace' => ['required', " \t\n", false],
            'required accepts zero' => ['required', '0', true],
            'required accepts false' => ['required', false, true],
        ];
    }

    #[DataProvider('ruleProvider')]
    public function testRule(string $rule, mixed $value, bool $passes): void
    {
        $this->assertSame($passes, (new Sanitizer(['field' => $value], ['field' => $rule]))->validate());
    }
}
