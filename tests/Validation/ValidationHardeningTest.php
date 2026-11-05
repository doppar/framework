<?php

namespace Tests\Validation;

use Phaseolies\DI\Container;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Http\Support\ValidationRules;
use Phaseolies\Http\Validation\Rule;
use Phaseolies\Support\Validation\Sanitizer;
use Phaseolies\Translation\FileLoader;
use Phaseolies\Translation\Translator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Tests\Support\MockContainer;

/**
 * Rule strings that contain "|", values that are arrays or objects, and
 * validating the same Sanitizer more than once.
 */
#[AllowMockObjectsWithoutExpectations]
class ValidationHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new MockContainer();
        Container::setInstance($container);
        $container->bind('translator', function () {
            return new Translator($this->createMock(FileLoader::class), 'en');
        });

        // The Rule trait flashes input to the session and reads the request.
        $container->bind('request', fn() => new class {
            public function except(...$keys): array
            {
                return [];
            }
        });
        $container->bind('session', fn() => new class {
            public function __call($name, $args)
            {
                return null;
            }
        });
    }

    protected function tearDown(): void
    {
        Container::forgetInstance();
        parent::tearDown();
    }

    private function sanitizer(array $data, array $rules): Sanitizer
    {
        return new Sanitizer($data, $rules);
    }

    private function fails(array $data, array $rules): bool
    {
        return $this->sanitizer($data, $rules)->fails();
    }

    /**
     * Run the form-request path (Rule::sanitize) and return its errors.
     *
     * @return array<string, array<int, string>>
     */
    private function requestErrors(array $input, array $rules): array
    {
        $request = new class ($input) {
            use Rule;

            public array $captured = [];

            public function __construct(private array $input) {}

            public function all(): array
            {
                return $this->input;
            }

            protected function setPassedData(array $data): void {}

            protected function failValidation(array $errors): never
            {
                $this->captured = $errors;

                throw new HttpResponseException($errors, 422);
            }

            public function run(array $rules): array
            {
                try {
                    $this->sanitize($rules);
                } catch (HttpResponseException) {
                }

                return $this->captured;
            }
        };

        return $request->run($rules);
    }

    private function splitter(): object
    {
        return new class {
            use ValidationRules;

            public function split(string $rules): array
            {
                return $this->splitRules($rules);
            }
        };
    }

    // ---- splitting rules on "|" ------------------------------------------

    public function testRulesAreSplitOnPipes(): void
    {
        $this->assertSame(['required', 'min:3', 'email'], $this->splitter()->split('required|min:3|email'));
    }

    public function testASingleRuleIsReturnedAsIs(): void
    {
        $this->assertSame(['required'], $this->splitter()->split('required'));
        $this->assertSame([''], $this->splitter()->split(''));
    }

    public function testARegexWithAlternationStaysInOnePiece(): void
    {
        $this->assertSame(
            ['required', 'regex:/^(cat|dog)$/', 'max:10'],
            $this->splitter()->split('required|regex:/^(cat|dog)$/|max:10')
        );
    }

    public function testARegexWithSeveralAlternationsStaysInOnePiece(): void
    {
        $this->assertSame(['regex:/^(a|b|c|d)$/'], $this->splitter()->split('regex:/^(a|b|c|d)$/'));
        $this->assertSame(['regex:/a|b|c/'], $this->splitter()->split('regex:/a|b|c/'));
    }

    public function testARegexKeepsItsFlags(): void
    {
        $this->assertSame(
            ['regex:/^(cat|dog)$/i', 'required'],
            $this->splitter()->split('regex:/^(cat|dog)$/i|required')
        );
    }

    public function testARegexWithOtherDelimiters(): void
    {
        $this->assertSame(['regex:#^(cat|dog)$#', 'min:2'], $this->splitter()->split('regex:#^(cat|dog)$#|min:2'));
        $this->assertSame(['regex:~a|b~', 'min:2'], $this->splitter()->split('regex:~a|b~|min:2'));
        $this->assertSame(['regex:{a|b}', 'min:2'], $this->splitter()->split('regex:{a|b}|min:2'));
        $this->assertSame(['regex:(a|b)', 'min:2'], $this->splitter()->split('regex:(a|b)|min:2'));
    }

    public function testARegexWithAnEscapedDelimiterInsideIt(): void
    {
        $this->assertSame(['regex:/^a\/b|c$/', 'min:2'], $this->splitter()->split('regex:/^a\/b|c$/|min:2'));
    }

    public function testARegexEndingInAnEscapedBackslashIsClosed(): void
    {
        // "\\\\/" is an escaped backslash followed by the real closing delimiter.
        $this->assertSame(['regex:/^a\\\\/', 'min:2'], $this->splitter()->split('regex:/^a\\\\/|min:2'));
    }

    public function testAClosedRegexDoesNotSwallowTheNextRule(): void
    {
        $this->assertSame(['regex:/^[a-z]+$/', 'required'], $this->splitter()->split('regex:/^[a-z]+$/|required'));
    }

    public function testAnUnterminatedRegexDoesNotSwallowTheRulesAfterIt(): void
    {
        $this->assertSame(['regex:/abc', 'min:3'], $this->splitter()->split('regex:/abc|min:3'));
    }

    public function testRegexIsOnlyTreatedSpeciallyAtTheStartOfARule(): void
    {
        $this->assertSame(['starts_with:regex:/a', 'b/'], $this->splitter()->split('starts_with:regex:/a|b/'));
    }

    // ---- regex alternation through the validators ------------------------

    public function testSanitizerAcceptsEveryAlternativeOfARegex(): void
    {
        foreach (['cat', 'dog'] as $pet) {
            $this->assertFalse($this->fails(['pet' => $pet], ['pet' => 'required|regex:/^(cat|dog)$/']), $pet);
        }
    }

    public function testSanitizerRejectsAValueOutsideTheAlternation(): void
    {
        $validator = $this->sanitizer(['pet' => 'cow'], ['pet' => 'required|regex:/^(cat|dog)$/']);

        $this->assertTrue($validator->fails());
        $this->assertSame(['pet'], array_keys($validator->errors()['errors']));
    }

    public function testSanitizerRunsTheRulesAfterAnAlternationRegex(): void
    {
        $rules = ['pet' => 'regex:/^(cat|dog)$/|max:2'];

        $this->assertTrue($this->fails(['pet' => 'cat'], $rules), 'max:2 must still run');
    }

    public function testSanitizerRulesBeforeAnAlternationRegexStillRun(): void
    {
        $this->assertTrue($this->fails(['pet' => ''], ['pet' => 'required|regex:/^(cat|dog)$/']));
    }

    public function testSanitizerMatchesAnUnanchoredAlternation(): void
    {
        $this->assertFalse($this->fails(['x' => 'b'], ['x' => 'regex:/^a|b$/']));
        $this->assertFalse($this->fails(['x' => 'a'], ['x' => 'regex:/^a|b$/']));
    }

    public function testRequestSanitizeAcceptsEveryAlternativeOfARegex(): void
    {
        foreach (['cat', 'dog'] as $pet) {
            $this->assertSame([], $this->requestErrors(['pet' => $pet], ['pet' => 'required|regex:/^(cat|dog)$/']), $pet);
        }
    }

    public function testRequestSanitizeRejectsAValueOutsideTheAlternation(): void
    {
        $errors = $this->requestErrors(['pet' => 'cow'], ['pet' => 'required|regex:/^(cat|dog)$/']);

        $this->assertSame(['pet'], array_keys($errors));
    }

    public function testRegexRulesWithoutPipesKeepWorking(): void
    {
        $this->assertFalse($this->fails(['code' => '1234'], ['code' => 'regex:/^\d{4,6}$/']));
        $this->assertTrue($this->fails(['code' => 'abc'], ['code' => 'regex:/^\d{4,6}$/']));
    }

    // ---- array and object values -----------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function scalarOnlyRules(): array
    {
        $rules = [
            'min:3', 'max:3', 'date', 'gte:today', 'lte:today', 'gt:2020-01-01', 'lt:2030-01-01',
            'alpha', 'alpha_num', 'alpha_dash', 'regex:/^[a-z]+$/', 'phone', 'uuid',
            'starts_with:ab', 'ends_with:yz', 'date_format:Y-m-d', 'uppercase', 'lowercase', 'slug',
            'digits:3', 'min_digits:2', 'max_digits:4', 'same_as:other',
        ];

        return array_combine($rules, array_map(fn($r) => [$r], $rules));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('scalarOnlyRules')]
    public function testAnArrayValueFailsInsteadOfThrowing(string $rule): void
    {
        $validator = $this->sanitizer(['f' => ['x', 'y'], 'other' => 'x'], ['f' => $rule]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('f', $validator->errors()['errors']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('scalarOnlyRules')]
    public function testANestedArrayValueFailsInsteadOfThrowing(string $rule): void
    {
        $this->assertTrue($this->fails(['f' => ['a' => ['b']], 'other' => 'x'], ['f' => $rule]));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('scalarOnlyRules')]
    public function testAnObjectValueFailsInsteadOfThrowing(string $rule): void
    {
        $this->assertTrue($this->fails(['f' => new \stdClass(), 'other' => 'x'], ['f' => $rule]));
    }

    public function testAnArrayValueIsReportedAsNotAString(): void
    {
        $validator = $this->sanitizer(['name' => ['x']], ['name' => 'required|min:3']);
        $validator->validate();

        $this->assertSame(['The Name must be a string.'], $validator->errors()['errors']['name']);
    }

    public function testAnArrayValueGivesOneErrorEvenWhenSeveralRulesRejectIt(): void
    {
        $validator = $this->sanitizer(['name' => ['x']], ['name' => 'required|string|min:3|max:50|alpha']);
        $validator->validate();

        $this->assertCount(1, $validator->errors()['errors']['name']);
    }

    public function testStringBeforeMinStillProtectsAgainstAnArray(): void
    {
        $this->assertTrue($this->fails(['name' => ['x']], ['name' => 'required|string|min:3']));
    }

    public function testRequestSanitizeFailsAnArrayInsteadOfThrowing(): void
    {
        $errors = $this->requestErrors(['name' => ['x']], ['name' => 'required|min:3|max:50']);

        $this->assertSame(['name'], array_keys($errors));
        $this->assertCount(1, $errors['name']);
    }

    public function testAnArrayStillSatisfiesRequiredAndArrayRules(): void
    {
        $this->assertFalse($this->fails(['tags' => ['a', 'b']], ['tags' => 'required|array']));
        $this->assertTrue($this->fails(['tags' => []], ['tags' => 'required']));
    }

    public function testRulesThatHandleArraysKeepTheirOwnBehaviour(): void
    {
        $this->assertTrue($this->fails(['tags' => ['a']], ['tags' => 'string']));
        $this->assertTrue($this->fails(['tags' => ['a']], ['tags' => 'email']));
        $this->assertTrue($this->fails(['tags' => ['a']], ['tags' => 'numeric']));
    }

    public function testScalarValuesAreUnaffectedByTheGuard(): void
    {
        $this->assertFalse($this->fails(['n' => 'abcd'], ['n' => 'min:3|max:10|alpha']));
        $this->assertFalse($this->fails(['n' => '2024-05-01'], ['n' => 'date|date_format:Y-m-d']));
        $this->assertFalse($this->fails(['n' => 12345], ['n' => 'digits:5']));
        $this->assertFalse($this->fails(['n' => 0], ['n' => 'digits:1']));
        $this->assertTrue($this->fails(['n' => 'ab'], ['n' => 'min:3']));
    }

    public function testANullAndAnEmptyStringAreStillTreatedAsEmpty(): void
    {
        $this->assertFalse($this->fails(['n' => ''], ['n' => 'min:3']));
        $this->assertFalse($this->fails([], ['n' => 'min:3']));
        $this->assertTrue($this->fails(['n' => null], ['n' => 'required']));
    }

    public function testAStringWithANulByteFailsDateFormatInsteadOfThrowing(): void
    {
        $this->assertTrue($this->fails(['d' => "2024-01-01\0"], ['d' => 'date_format:Y-m-d']));
        $this->assertTrue($this->fails(['d' => "a\0b"], ['d' => 'date_format:Y-m-d']));
        $this->assertFalse($this->fails(['d' => '2024-01-01'], ['d' => 'date_format:Y-m-d']));
    }

    // ---- validating more than once ---------------------------------------

    public function testValidatingTwiceDoesNotDuplicateErrors(): void
    {
        $validator = $this->sanitizer(['a' => ''], ['a' => 'required']);

        $validator->validate();
        $validator->validate();

        $this->assertCount(1, $validator->errors()['errors']['a']);
    }

    public function testCallingFailsTwiceDoesNotDuplicateErrors(): void
    {
        $validator = $this->sanitizer(['a' => ''], ['a' => 'required']);

        $validator->fails();
        $validator->fails();

        $this->assertSame(['The A field is required.'], $validator->errors()['errors']['a']);
    }

    public function testFailsThenValidateGivesTheSameResult(): void
    {
        $validator = $this->sanitizer(['a' => ''], ['a' => 'required']);

        $this->assertTrue($validator->fails());
        $this->assertFalse($validator->validate());
        $this->assertCount(1, $validator->errors()['errors']['a']);
    }

    public function testErrorsAreClearedWhenTheDataBecomesValid(): void
    {
        $validator = $this->sanitizer(['a' => ''], ['a' => 'required']);
        $validator->validate();

        $data = new \ReflectionProperty($validator, 'data');
        $data->setValue($validator, ['a' => 'now filled']);

        $this->assertTrue($validator->validate());
        $this->assertSame([], $validator->errors()['errors']);
        $this->assertNull($validator->errors()['message']);
    }

    public function testDifferentFieldsKeepTheirOwnErrors(): void
    {
        $validator = $this->sanitizer(['a' => '', 'b' => ''], ['a' => 'required', 'b' => 'required']);
        $validator->validate();
        $validator->validate();

        $this->assertSame(['a', 'b'], array_keys($validator->errors()['errors']));
        $this->assertCount(1, $validator->errors()['errors']['a']);
        $this->assertCount(1, $validator->errors()['errors']['b']);
    }

    public function testTwoDifferentMessagesForOneFieldAreBothKept(): void
    {
        $validator = $this->sanitizer(['a' => 'ab1'], ['a' => 'alpha|uppercase']);
        $validator->validate();

        $this->assertSame(
            ['The A may only contain letters.', 'The A must be uppercase.'],
            $validator->errors()['errors']['a']
        );
    }

    public function testSeparateSanitizersDoNotShareErrors(): void
    {
        $first = $this->sanitizer(['a' => ''], ['a' => 'required']);
        $second = $this->sanitizer(['a' => 'ok'], ['a' => 'required']);

        $first->validate();

        $this->assertTrue($second->validate());
        $this->assertSame([], $second->errors()['errors']);
    }
}
