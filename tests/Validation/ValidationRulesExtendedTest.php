<?php

namespace Tests\Validation;

use Tests\Support\MockContainer;
use Phaseolies\Translation\Translator;
use Phaseolies\Translation\FileLoader;
use Phaseolies\Support\Validation\Sanitizer;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;

class ValidationRulesExtendedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Container::setInstance(new MockContainer());
        $container = new Container();
        $container->bind('translator', function () {
            $loader = $this->createMock(FileLoader::class);
            return new Translator($loader, 'en');
        });
    }

    private function passes(array $data, array $rules): bool
    {
        return (new Sanitizer($data, $rules))->validate();
    }

    private function fails(array $data, array $rules): bool
    {
        return (new Sanitizer($data, $rules))->fails();
    }

    // -------------------------------------------------------------------------
    // alpha
    // -------------------------------------------------------------------------

    public function testAlphaPassesForLettersOnly(): void
    {
        $this->assertTrue($this->passes(['name' => 'John'], ['name' => 'alpha']));
        $this->assertTrue($this->passes(['name' => 'UPPERCASE'], ['name' => 'alpha']));
        $this->assertTrue($this->passes(['name' => 'mixedCase'], ['name' => 'alpha']));
    }

    public function testAlphaFailsWhenContainsNumbers(): void
    {
        $this->assertTrue($this->fails(['name' => 'John123'], ['name' => 'alpha']));
    }

    public function testAlphaFailsWhenContainsSpecialChars(): void
    {
        $this->assertTrue($this->fails(['name' => 'John_Doe'], ['name' => 'alpha']));
        $this->assertTrue($this->fails(['name' => 'John Doe'], ['name' => 'alpha']));
        $this->assertTrue($this->fails(['name' => 'John-Doe'], ['name' => 'alpha']));
    }

    // -------------------------------------------------------------------------
    // alpha_num
    // -------------------------------------------------------------------------

    public function testAlphaNumPassesForLettersAndNumbers(): void
    {
        $this->assertTrue($this->passes(['username' => 'John99'], ['username' => 'alpha_num']));
        $this->assertTrue($this->passes(['username' => 'abc123'], ['username' => 'alpha_num']));
        $this->assertTrue($this->passes(['username' => 'ABC'], ['username' => 'alpha_num']));
        $this->assertTrue($this->passes(['username' => '12345'], ['username' => 'alpha_num']));
    }

    public function testAlphaNumFailsWhenContainsSpecialChars(): void
    {
        $this->assertTrue($this->fails(['username' => 'john_99'], ['username' => 'alpha_num']));
        $this->assertTrue($this->fails(['username' => 'john-99'], ['username' => 'alpha_num']));
        $this->assertTrue($this->fails(['username' => 'john 99'], ['username' => 'alpha_num']));
        $this->assertTrue($this->fails(['username' => 'john@99'], ['username' => 'alpha_num']));
    }

    // -------------------------------------------------------------------------
    // alpha_dash
    // -------------------------------------------------------------------------

    public function testAlphaDashPassesForLettersNumbersDashesUnderscores(): void
    {
        $this->assertTrue($this->passes(['handle' => 'john_doe-99'], ['handle' => 'alpha_dash']));
        $this->assertTrue($this->passes(['handle' => 'john-doe'], ['handle' => 'alpha_dash']));
        $this->assertTrue($this->passes(['handle' => 'john_doe'], ['handle' => 'alpha_dash']));
        $this->assertTrue($this->passes(['handle' => 'JohnDoe123'], ['handle' => 'alpha_dash']));
    }

    public function testAlphaDashFailsWhenContainsSpacesOrSpecialChars(): void
    {
        $this->assertTrue($this->fails(['handle' => 'john doe'], ['handle' => 'alpha_dash']));
        $this->assertTrue($this->fails(['handle' => 'john@doe'], ['handle' => 'alpha_dash']));
        $this->assertTrue($this->fails(['handle' => 'john.doe'], ['handle' => 'alpha_dash']));
    }

    // -------------------------------------------------------------------------
    // numeric
    // -------------------------------------------------------------------------

    public function testNumericPassesForIntegerAndFloat(): void
    {
        $this->assertTrue($this->passes(['price' => '100'], ['price' => 'numeric']));
        $this->assertTrue($this->passes(['price' => '19.99'], ['price' => 'numeric']));
        $this->assertTrue($this->passes(['price' => '-5'], ['price' => 'numeric']));
        $this->assertTrue($this->passes(['price' => '0'], ['price' => 'numeric']));
    }

    public function testNumericFailsForNonNumericValues(): void
    {
        $this->assertTrue($this->fails(['price' => 'abc'],      ['price' => 'numeric']));
        $this->assertTrue($this->fails(['price' => '19.99abc'], ['price' => 'numeric']));
        // empty string is nullable (skipped by itself); require + numeric catches empty inputs
        $this->assertTrue($this->fails(['price' => ''], ['price' => 'required|numeric']));
    }

    // -------------------------------------------------------------------------
    // url
    // -------------------------------------------------------------------------

    public function testUrlPassesForValidUrls(): void
    {
        $this->assertTrue($this->passes(['site' => 'https://example.com'], ['site' => 'url']));
        $this->assertTrue($this->passes(['site' => 'http://example.com/path?q=1'], ['site' => 'url']));
        $this->assertTrue($this->passes(['site' => 'ftp://files.example.com'], ['site' => 'url']));
    }

    public function testUrlFailsForInvalidUrls(): void
    {
        $this->assertTrue($this->fails(['site' => 'example.com'], ['site' => 'url']));
        $this->assertTrue($this->fails(['site' => 'not a url'], ['site' => 'url']));
        $this->assertTrue($this->fails(['site' => 'htp:/bad'], ['site' => 'url']));
    }

    // -------------------------------------------------------------------------
    // ip
    // -------------------------------------------------------------------------

    public function testIpPassesForValidIpAddresses(): void
    {
        $this->assertTrue($this->passes(['addr' => '192.168.1.1'], ['addr' => 'ip']));
        $this->assertTrue($this->passes(['addr' => '::1'], ['addr' => 'ip']));
        $this->assertTrue($this->passes(['addr' => '2001:db8::1'], ['addr' => 'ip']));
    }

    public function testIpFailsForInvalidIpAddresses(): void
    {
        $this->assertTrue($this->fails(['addr' => '999.0.0.1'], ['addr' => 'ip']));
        $this->assertTrue($this->fails(['addr' => 'not.an.ip'], ['addr' => 'ip']));
        $this->assertTrue($this->fails(['addr' => '256.256.256.256'], ['addr' => 'ip']));
    }

    // -------------------------------------------------------------------------
    // ipv4
    // -------------------------------------------------------------------------

    public function testIpv4PassesForValidIpv4(): void
    {
        $this->assertTrue($this->passes(['addr' => '192.168.1.1'], ['addr' => 'ipv4']));
        $this->assertTrue($this->passes(['addr' => '127.0.0.1'], ['addr' => 'ipv4']));
        $this->assertTrue($this->passes(['addr' => '0.0.0.0'], ['addr' => 'ipv4']));
    }

    public function testIpv4FailsForIpv6AndInvalid(): void
    {
        $this->assertTrue($this->fails(['addr' => '::1'], ['addr' => 'ipv4']));
        $this->assertTrue($this->fails(['addr' => '2001:db8::1'], ['addr' => 'ipv4']));
        $this->assertTrue($this->fails(['addr' => '999.0.0.1'], ['addr' => 'ipv4']));
    }

    // -------------------------------------------------------------------------
    // ipv6
    // -------------------------------------------------------------------------

    public function testIpv6PassesForValidIpv6(): void
    {
        $this->assertTrue($this->passes(['addr' => '::1'], ['addr' => 'ipv6']));
        $this->assertTrue($this->passes(['addr' => '2001:db8::1'], ['addr' => 'ipv6']));
        $this->assertTrue($this->passes(['addr' => 'fe80::1'], ['addr' => 'ipv6']));
    }

    public function testIpv6FailsForIpv4AndInvalid(): void
    {
        $this->assertTrue($this->fails(['addr' => '192.168.1.1'], ['addr' => 'ipv6']));
        $this->assertTrue($this->fails(['addr' => 'not-an-ip'], ['addr' => 'ipv6']));
    }

    // -------------------------------------------------------------------------
    // json
    // -------------------------------------------------------------------------

    public function testJsonPassesForValidJsonString(): void
    {
        $this->assertTrue($this->passes(['meta' => '{"key":"value"}'], ['meta' => 'json']));
        $this->assertTrue($this->passes(['meta' => '[1,2,3]'], ['meta' => 'json']));
        $this->assertTrue($this->passes(['meta' => '"simple string"'], ['meta' => 'json']));
        $this->assertTrue($this->passes(['meta' => 'true'], ['meta' => 'json']));
        $this->assertTrue($this->passes(['meta' => '42'], ['meta' => 'json']));
    }

    public function testJsonFailsForInvalidJsonString(): void
    {
        $this->assertTrue($this->fails(['meta' => '{key:value}'], ['meta' => 'json']));
        $this->assertTrue($this->fails(['meta' => 'not json'], ['meta' => 'json']));
        $this->assertTrue($this->fails(['meta' => '{unclosed'], ['meta' => 'json']));
    }

    // -------------------------------------------------------------------------
    // boolean
    // -------------------------------------------------------------------------

    public function testBooleanPassesForBooleanLikeValues(): void
    {
        $this->assertTrue($this->passes(['flag' => true],    ['flag' => 'boolean']));
        $this->assertTrue($this->passes(['flag' => false],   ['flag' => 'boolean']));
        $this->assertTrue($this->passes(['flag' => 1],       ['flag' => 'boolean']));
        $this->assertTrue($this->passes(['flag' => 0],       ['flag' => 'boolean']));
        $this->assertTrue($this->passes(['flag' => '1'],     ['flag' => 'boolean']));
        $this->assertTrue($this->passes(['flag' => '0'],     ['flag' => 'boolean']));
        $this->assertTrue($this->passes(['flag' => 'true'],  ['flag' => 'boolean']));
        $this->assertTrue($this->passes(['flag' => 'false'], ['flag' => 'boolean']));
    }

    public function testBooleanFailsForNonBooleanValues(): void
    {
        $this->assertTrue($this->fails(['flag' => 'yes'],  ['flag' => 'boolean']));
        $this->assertTrue($this->fails(['flag' => 'no'],   ['flag' => 'boolean']));
        $this->assertTrue($this->fails(['flag' => '2'],    ['flag' => 'boolean']));
        $this->assertTrue($this->fails(['flag' => 'on'],   ['flag' => 'boolean']));
    }

    // -------------------------------------------------------------------------
    // confirmed
    // -------------------------------------------------------------------------

    public function testConfirmedPassesWhenConfirmationMatches(): void
    {
        $this->assertTrue($this->passes(
            ['password' => 'secret123', 'password_confirmation' => 'secret123'],
            ['password' => 'confirmed']
        ));
    }

    public function testConfirmedFailsWhenConfirmationMissing(): void
    {
        $this->assertTrue($this->fails(
            ['password' => 'secret123'],
            ['password' => 'confirmed']
        ));
    }

    public function testConfirmedFailsWhenConfirmationDiffers(): void
    {
        $this->assertTrue($this->fails(
            ['password' => 'secret123', 'password_confirmation' => 'different'],
            ['password' => 'confirmed']
        ));
    }

    // -------------------------------------------------------------------------
    // different
    // -------------------------------------------------------------------------

    public function testDifferentPassesWhenValuesAreDifferent(): void
    {
        $this->assertTrue($this->passes(
            ['new_password' => 'newpass', 'old_password' => 'oldpass'],
            ['new_password' => 'different:old_password']
        ));
    }

    public function testDifferentFailsWhenValuesAreTheSame(): void
    {
        $this->assertTrue($this->fails(
            ['new_password' => 'samepass', 'old_password' => 'samepass'],
            ['new_password' => 'different:old_password']
        ));
    }

    // -------------------------------------------------------------------------
    // in
    // -------------------------------------------------------------------------

    public function testInPassesWhenValueIsInList(): void
    {
        $this->assertTrue($this->passes(['status' => 'active'],   ['status' => 'in:active,inactive,pending']));
        $this->assertTrue($this->passes(['status' => 'inactive'], ['status' => 'in:active,inactive,pending']));
        $this->assertTrue($this->passes(['role'   => 'admin'],    ['role'   => 'in:admin,user,moderator']));
    }

    public function testInFailsWhenValueIsNotInList(): void
    {
        $this->assertTrue($this->fails(['status' => 'deleted'],  ['status' => 'in:active,inactive,pending']));
        $this->assertTrue($this->fails(['status' => 'ACTIVE'],   ['status' => 'in:active,inactive,pending']));
        $this->assertTrue($this->fails(['role'   => 'superuser'], ['role'   => 'in:admin,user,moderator']));
    }

    // -------------------------------------------------------------------------
    // not_in
    // -------------------------------------------------------------------------

    public function testNotInPassesWhenValueIsNotInList(): void
    {
        $this->assertTrue($this->passes(['username' => 'johndoe'],  ['username' => 'not_in:admin,root,superuser']));
        $this->assertTrue($this->passes(['username' => 'alice'],    ['username' => 'not_in:admin,root,superuser']));
    }

    public function testNotInFailsWhenValueIsInList(): void
    {
        $this->assertTrue($this->fails(['username' => 'admin'],     ['username' => 'not_in:admin,root,superuser']));
        $this->assertTrue($this->fails(['username' => 'root'],      ['username' => 'not_in:admin,root,superuser']));
        $this->assertTrue($this->fails(['username' => 'superuser'], ['username' => 'not_in:admin,root,superuser']));
    }

    // -------------------------------------------------------------------------
    // regex
    // -------------------------------------------------------------------------

    public function testRegexPassesWhenValueMatchesPattern(): void
    {
        $this->assertTrue($this->passes(['code'  => '1234'],    ['code'  => 'regex:/^\d{4,6}$/']));
        $this->assertTrue($this->passes(['color' => '#FF5733'], ['color' => 'regex:/^#[0-9a-fA-F]{6}$/']));
    }

    public function testRegexFailsWhenValueDoesNotMatchPattern(): void
    {
        $this->assertTrue($this->fails(['code'  => 'abc'],    ['code'  => 'regex:/^\d{4,6}$/']));
        $this->assertTrue($this->fails(['color' => '#GGG'],   ['color' => 'regex:/^#[0-9a-fA-F]{6}$/']));
        $this->assertTrue($this->fails(['code'  => '12'],     ['code'  => 'regex:/^\d{4,6}$/']));
    }

    // -------------------------------------------------------------------------
    // phone
    // -------------------------------------------------------------------------

    public function testPhonePassesForValidPhoneNumbers(): void
    {
        $this->assertTrue($this->passes(['phone' => '+8801712345678'], ['phone' => 'phone']));
        $this->assertTrue($this->passes(['phone' => '01712345678'],    ['phone' => 'phone']));
        $this->assertTrue($this->passes(['phone' => '+1 800 555 1234'], ['phone' => 'phone']));
        $this->assertTrue($this->passes(['phone' => '(555) 867-5309'], ['phone' => 'phone']));
    }

    public function testPhoneFailsForInvalidPhoneNumbers(): void
    {
        $this->assertTrue($this->fails(['phone' => 'abc-def'],   ['phone' => 'phone']));
        $this->assertTrue($this->fails(['phone' => '123'],       ['phone' => 'phone']));
        $this->assertTrue($this->fails(['phone' => 'not-phone'], ['phone' => 'phone']));
    }

    // -------------------------------------------------------------------------
    // digits
    // -------------------------------------------------------------------------

    public function testDigitsPassesForExactDigitCount(): void
    {
        $this->assertTrue($this->passes(['pin'  => '1234'],   ['pin'  => 'digits:4']));
        $this->assertTrue($this->passes(['otp'  => '123456'], ['otp'  => 'digits:6']));
        $this->assertTrue($this->passes(['code' => '00000'],  ['code' => 'digits:5']));
    }

    public function testDigitsFailsForWrongDigitCount(): void
    {
        $this->assertTrue($this->fails(['pin' => '123'],    ['pin' => 'digits:4']));
        $this->assertTrue($this->fails(['pin' => '12345'],  ['pin' => 'digits:4']));
        $this->assertTrue($this->fails(['pin' => 'abcd'],   ['pin' => 'digits:4']));
        $this->assertTrue($this->fails(['pin' => '12.34'],  ['pin' => 'digits:4']));
    }

    // -------------------------------------------------------------------------
    // min_digits
    // -------------------------------------------------------------------------

    public function testMinDigitsPassesWhenDigitCountMeetsMinimum(): void
    {
        $this->assertTrue($this->passes(['number' => '1234567890'], ['number' => 'min_digits:10']));
        $this->assertTrue($this->passes(['number' => '12345678901'], ['number' => 'min_digits:10']));
        $this->assertTrue($this->passes(['number' => '12345'], ['number' => 'min_digits:5']));
    }

    public function testMinDigitsFailsWhenDigitCountBelowMinimum(): void
    {
        $this->assertTrue($this->fails(['number' => '123456789'], ['number' => 'min_digits:10']));
        $this->assertTrue($this->fails(['number' => '1234'],      ['number' => 'min_digits:5']));
        $this->assertTrue($this->fails(['number' => 'abc123'],    ['number' => 'min_digits:3']));
    }

    // -------------------------------------------------------------------------
    // max_digits
    // -------------------------------------------------------------------------

    public function testMaxDigitsPassesWhenDigitCountWithinMaximum(): void
    {
        $this->assertTrue($this->passes(['code' => '1234'],   ['code' => 'max_digits:6']));
        $this->assertTrue($this->passes(['code' => '123456'], ['code' => 'max_digits:6']));
        $this->assertTrue($this->passes(['code' => '1'],      ['code' => 'max_digits:6']));
    }

    public function testMaxDigitsFailsWhenDigitCountExceedsMaximum(): void
    {
        $this->assertTrue($this->fails(['code' => '1234567'], ['code' => 'max_digits:6']));
        $this->assertTrue($this->fails(['code' => '12345678'], ['code' => 'max_digits:6']));
        $this->assertTrue($this->fails(['code' => '1234abc'],  ['code' => 'max_digits:4']));
    }

    // -------------------------------------------------------------------------
    // size
    // -------------------------------------------------------------------------

    public function testSizePassesForExactStringLength(): void
    {
        $this->assertTrue($this->passes(['otp'  => '123456'], ['otp'  => 'size:6']));
        $this->assertTrue($this->passes(['code' => 'AB'],     ['code' => 'size:2']));
    }

    public function testSizePassesForExactNumericValue(): void
    {
        // size always checks character count, so '100' has 3 chars → size:3
        $this->assertTrue($this->passes(['amount' => '100'], ['amount' => 'size:3']));
        $this->assertTrue($this->passes(['amount' => '1'],   ['amount' => 'size:1']));
    }

    public function testSizeFailsForWrongLength(): void
    {
        $this->assertTrue($this->fails(['otp' => '12345'],   ['otp' => 'size:6']));
        $this->assertTrue($this->fails(['otp' => '1234567'], ['otp' => 'size:6']));
    }

    // -------------------------------------------------------------------------
    // starts_with
    // -------------------------------------------------------------------------

    public function testStartsWithPassesWhenValueStartsWithPrefix(): void
    {
        $this->assertTrue($this->passes(['code'  => 'INV-2024'],   ['code'  => 'starts_with:INV-']));
        $this->assertTrue($this->passes(['route' => '/api/users'], ['route' => 'starts_with:/api']));
    }

    public function testStartsWithFailsWhenValueDoesNotStartWithPrefix(): void
    {
        $this->assertTrue($this->fails(['code' => '2024-INV'],  ['code' => 'starts_with:INV-']));
        $this->assertTrue($this->fails(['code' => 'inv-2024'],  ['code' => 'starts_with:INV-']));
        $this->assertTrue($this->fails(['code' => 'SOMETHING'], ['code' => 'starts_with:INV-']));
    }

    // -------------------------------------------------------------------------
    // ends_with
    // -------------------------------------------------------------------------

    public function testEndsWithPassesWhenValueEndsWithSuffix(): void
    {
        $this->assertTrue($this->passes(['file' => 'report.pdf'],  ['file' => 'ends_with:.pdf']));
        $this->assertTrue($this->passes(['file' => 'image.jpg'],   ['file' => 'ends_with:.jpg']));
        $this->assertTrue($this->passes(['email' => 'user@company.com'], ['email' => 'ends_with:.com']));
    }

    public function testEndsWithFailsWhenValueDoesNotEndWithSuffix(): void
    {
        $this->assertTrue($this->fails(['file' => 'report.docx'], ['file' => 'ends_with:.pdf']));
        $this->assertTrue($this->fails(['file' => 'pdf.report'],  ['file' => 'ends_with:.pdf']));
    }

    // -------------------------------------------------------------------------
    // uuid
    // -------------------------------------------------------------------------

    public function testUuidPassesForValidUuids(): void
    {
        $this->assertTrue($this->passes(['id' => '550e8400-e29b-41d4-a716-446655440000'], ['id' => 'uuid']));
        $this->assertTrue($this->passes(['id' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8'], ['id' => 'uuid']));
        $this->assertTrue($this->passes(['id' => '6ba7b811-9dad-11d1-80b4-00c04fd430c8'], ['id' => 'uuid']));
    }

    public function testUuidFailsForInvalidUuids(): void
    {
        $this->assertTrue($this->fails(['id' => 'abc-123'],                   ['id' => 'uuid']));
        $this->assertTrue($this->fails(['id' => '550e8400-e29b-41d4-a716'],   ['id' => 'uuid']));
        $this->assertTrue($this->fails(['id' => 'not-a-uuid-at-all'],         ['id' => 'uuid']));
        $this->assertTrue($this->fails(['id' => '550e8400e29b41d4a716446655440000'], ['id' => 'uuid']));
    }

    // -------------------------------------------------------------------------
    // date_format
    // -------------------------------------------------------------------------

    public function testDateFormatPassesForCorrectFormat(): void
    {
        $this->assertTrue($this->passes(['dob' => '1995-06-15'], ['dob' => 'date_format:Y-m-d']));
        $this->assertTrue($this->passes(['dob' => '15/06/1995'], ['dob' => 'date_format:d/m/Y']));
        $this->assertTrue($this->passes(['at'  => '2024-01-31 14:30:00'], ['at' => 'date_format:Y-m-d H:i:s']));
    }

    public function testDateFormatFailsForWrongFormat(): void
    {
        $this->assertTrue($this->fails(['dob' => '15-06-1995'], ['dob' => 'date_format:Y-m-d']));
        $this->assertTrue($this->fails(['dob' => '1995/06/15'], ['dob' => 'date_format:Y-m-d']));
        $this->assertTrue($this->fails(['dob' => 'not-a-date'], ['dob' => 'date_format:Y-m-d']));
    }

    // -------------------------------------------------------------------------
    // uppercase
    // -------------------------------------------------------------------------

    public function testUppercasePassesForAllUppercaseValues(): void
    {
        $this->assertTrue($this->passes(['code' => 'USA'],    ['code' => 'uppercase']));
        $this->assertTrue($this->passes(['code' => 'BD'],     ['code' => 'uppercase']));
        $this->assertTrue($this->passes(['code' => 'HELLO'],  ['code' => 'uppercase']));
        $this->assertTrue($this->passes(['code' => 'PHP8'],   ['code' => 'uppercase']));
    }

    public function testUppercaseFailsForLowercaseOrMixedValues(): void
    {
        $this->assertTrue($this->fails(['code' => 'usa'],   ['code' => 'uppercase']));
        $this->assertTrue($this->fails(['code' => 'Usa'],   ['code' => 'uppercase']));
        $this->assertTrue($this->fails(['code' => 'uSA'],   ['code' => 'uppercase']));
    }

    // -------------------------------------------------------------------------
    // lowercase
    // -------------------------------------------------------------------------

    public function testLowercasePassesForAllLowercaseValues(): void
    {
        $this->assertTrue($this->passes(['tag' => 'php'],        ['tag' => 'lowercase']));
        $this->assertTrue($this->passes(['tag' => 'laravel'],    ['tag' => 'lowercase']));
        $this->assertTrue($this->passes(['tag' => 'hello123'],   ['tag' => 'lowercase']));
    }

    public function testLowercaseFailsForUppercaseOrMixedValues(): void
    {
        $this->assertTrue($this->fails(['tag' => 'PHP'],     ['tag' => 'lowercase']));
        $this->assertTrue($this->fails(['tag' => 'Laravel'], ['tag' => 'lowercase']));
        $this->assertTrue($this->fails(['tag' => 'heLLo'],   ['tag' => 'lowercase']));
    }

    // -------------------------------------------------------------------------
    // slug
    // -------------------------------------------------------------------------

    public function testSlugPassesForValidSlugs(): void
    {
        $this->assertTrue($this->passes(['slug' => 'my-blog-post'],    ['slug' => 'slug']));
        $this->assertTrue($this->passes(['slug' => 'hello-world'],     ['slug' => 'slug']));
        $this->assertTrue($this->passes(['slug' => 'php8'],            ['slug' => 'slug']));
        $this->assertTrue($this->passes(['slug' => 'post-123'],        ['slug' => 'slug']));
        $this->assertTrue($this->passes(['slug' => 'a'],               ['slug' => 'slug']));
    }

    public function testSlugFailsForInvalidSlugs(): void
    {
        $this->assertTrue($this->fails(['slug' => 'My Blog Post'],  ['slug' => 'slug']));
        $this->assertTrue($this->fails(['slug' => 'my_blog'],       ['slug' => 'slug']));
        $this->assertTrue($this->fails(['slug' => 'my--blog'],      ['slug' => 'slug']));
        $this->assertTrue($this->fails(['slug' => '-my-blog'],      ['slug' => 'slug']));
        $this->assertTrue($this->fails(['slug' => 'my-blog-'],      ['slug' => 'slug']));
        $this->assertTrue($this->fails(['slug' => 'My-Blog'],       ['slug' => 'slug']));
    }

    // -------------------------------------------------------------------------
    // string
    // -------------------------------------------------------------------------

    public function testStringPassesForStringValues(): void
    {
        $this->assertTrue($this->passes(['title' => 'Hello World'], ['title' => 'string']));
        $this->assertTrue($this->passes(['title' => ''],            ['title' => 'string']));
        $this->assertTrue($this->passes(['title' => '123'],         ['title' => 'string']));
    }

    public function testStringFailsForNonStringValues(): void
    {
        $this->assertTrue($this->fails(['title' => ['array']],  ['title' => 'string']));
        $this->assertTrue($this->fails(['title' => [1, 2, 3]], ['title' => 'string']));
    }

    // -------------------------------------------------------------------------
    // array
    // -------------------------------------------------------------------------

    public function testArrayPassesForArrayValues(): void
    {
        $this->assertTrue($this->passes(['tags' => [1, 2, 3]],        ['tags' => 'array']));
        $this->assertTrue($this->passes(['tags' => ['php', 'js']],    ['tags' => 'array']));
        $this->assertTrue($this->passes(['tags' => ['a' => 'b']],     ['tags' => 'array']));
    }

    public function testArrayFailsForNonArrayValues(): void
    {
        $this->assertTrue($this->fails(['tags' => '[1,2,3]'],  ['tags' => 'array']));
        $this->assertTrue($this->fails(['tags' => 'php,js'],   ['tags' => 'array']));
        $this->assertTrue($this->fails(['tags' => 'string'],   ['tags' => 'array']));
        $this->assertTrue($this->fails(['tags' => '123'],      ['tags' => 'array']));
    }

    // -------------------------------------------------------------------------
    // timezone
    // -------------------------------------------------------------------------

    public function testTimezonePassesForValidTimezones(): void
    {
        $this->assertTrue($this->passes(['tz' => 'Asia/Dhaka'],      ['tz' => 'timezone']));
        $this->assertTrue($this->passes(['tz' => 'UTC'],             ['tz' => 'timezone']));
        $this->assertTrue($this->passes(['tz' => 'America/New_York'], ['tz' => 'timezone']));
        $this->assertTrue($this->passes(['tz' => 'Europe/London'],   ['tz' => 'timezone']));
    }

    public function testTimezoneFailsForInvalidTimezones(): void
    {
        $this->assertTrue($this->fails(['tz' => 'GMT+6'],      ['tz' => 'timezone']));
        $this->assertTrue($this->fails(['tz' => 'Asia/xyz'],   ['tz' => 'timezone']));
        $this->assertTrue($this->fails(['tz' => '+06:00'],     ['tz' => 'timezone']));
        $this->assertTrue($this->fails(['tz' => 'not/valid'],  ['tz' => 'timezone']));
    }

    // -------------------------------------------------------------------------
    // required — array handling (fixed bug)
    // -------------------------------------------------------------------------

    public function testRequiredFailsForEmptyArray(): void
    {
        $this->assertTrue($this->fails(['tags' => []], ['tags' => 'required']));
    }

    public function testRequiredPassesForNonEmptyArray(): void
    {
        $this->assertTrue($this->passes(['tags' => [1, 2, 3]],     ['tags' => 'required']));
        $this->assertTrue($this->passes(['tags' => ['php', 'js']], ['tags' => 'required']));
    }

    public function testRequiredFailsForMissingField(): void
    {
        $this->assertTrue($this->fails([], ['name' => 'required']));
    }

    public function testRequiredFailsForEmptyString(): void
    {
        $this->assertTrue($this->fails(['name' => ''], ['name' => 'required']));
    }

    public function testRequiredPassesForNonEmptyString(): void
    {
        $this->assertTrue($this->passes(['name' => 'John'], ['name' => 'required']));
    }

    // -------------------------------------------------------------------------
    // combined rules — realistic scenarios
    // -------------------------------------------------------------------------

    public function testRegistrationRulesPassForValidData(): void
    {
        $data = [
            'first_name'    => 'John',
            'username'      => 'john_doe-99',
            'status'        => 'active',
            'website'       => 'https://john.com',
            'ip_address'    => '192.168.1.1',
            'pin'           => '1234',
            'timezone'      => 'Asia/Dhaka',
            'slug'          => 'my-post',
            'referral_code' => 'ABC12345',
        ];

        $rules = [
            'first_name'    => 'required|alpha',
            'username'      => 'required|alpha_dash|min:3|max:30',
            'status'        => 'required|in:active,inactive,pending',
            'website'       => 'required|url',
            'ip_address'    => 'required|ipv4',
            'pin'           => 'required|digits:4',
            'timezone'      => 'required|timezone',
            'slug'          => 'required|slug',
            'referral_code' => 'required|alpha_num|size:8',
        ];

        $this->assertTrue($this->passes($data, $rules));
    }

    public function testRegistrationRulesFailForInvalidData(): void
    {
        $data = [
            'first_name'    => 'John123',      // alpha fails
            'username'      => 'jd',           // min:3 fails
            'status'        => 'deleted',      // in fails
            'website'       => 'not-a-url',    // url fails
            'ip_address'    => '::1',          // ipv4 fails (ipv6)
            'pin'           => '12',           // digits:4 fails
            'timezone'      => 'GMT+6',        // timezone fails
            'slug'          => 'My Bad Slug',  // slug fails
            'referral_code' => 'AB',           // size:8 fails
        ];

        $rules = [
            'first_name'    => 'required|alpha',
            'username'      => 'required|alpha_dash|min:3|max:30',
            'status'        => 'required|in:active,inactive,pending',
            'website'       => 'required|url',
            'ip_address'    => 'required|ipv4',
            'pin'           => 'required|digits:4',
            'timezone'      => 'required|timezone',
            'slug'          => 'required|slug',
            'referral_code' => 'required|alpha_num|size:8',
        ];

        $sanitizer = new Sanitizer($data, $rules);
        $this->assertTrue($sanitizer->fails());
        $errors = $sanitizer->errors()['errors'];
        $this->assertCount(9, $errors);
    }

    public function testPasswordConfirmedWithMinLength(): void
    {
        // Pass: password matches confirmation and meets min length
        $this->assertTrue($this->passes(
            ['password' => 'secret12', 'password_confirmation' => 'secret12'],
            ['password' => 'required|min:8|confirmed']
        ));

        // Fail: password too short
        $this->assertTrue($this->fails(
            ['password' => 'short', 'password_confirmation' => 'short'],
            ['password' => 'required|min:8|confirmed']
        ));

        // Fail: confirmation mismatch
        $this->assertTrue($this->fails(
            ['password' => 'secret12', 'password_confirmation' => 'different'],
            ['password' => 'required|min:8|confirmed']
        ));
    }

    public function testNewPasswordMustDifferFromOld(): void
    {
        $this->assertTrue($this->passes(
            ['new_password' => 'newSecret99', 'old_password' => 'oldSecret11'],
            ['new_password' => 'required|min:8|different:old_password']
        ));

        $this->assertTrue($this->fails(
            ['new_password' => 'samePassword', 'old_password' => 'samePassword'],
            ['new_password' => 'required|min:8|different:old_password']
        ));
    }

    public function testJsonFieldWithRequiredRule(): void
    {
        $this->assertTrue($this->passes(
            ['settings' => '{"theme":"dark","lang":"en"}'],
            ['settings' => 'required|json']
        ));

        $this->assertTrue($this->fails(
            ['settings' => '{bad json}'],
            ['settings' => 'required|json']
        ));
    }

    public function testTagsArrayRequiredAndNotEmpty(): void
    {
        // Non-empty array passes
        $this->assertTrue($this->passes(
            ['tags' => ['php', 'laravel']],
            ['tags' => 'required|array']
        ));

        // Empty array fails required
        $this->assertTrue($this->fails(
            ['tags' => []],
            ['tags' => 'required|array']
        ));

        // String representation of array fails array rule
        $this->assertTrue($this->fails(
            ['tags' => '[1,2,3]'],
            ['tags' => 'required|array']
        ));
    }
}
