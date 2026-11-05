<?php

namespace Tests\Unit\Validation;

use Mockery;
use Phaseolies\Translation\Translator;
use Phaseolies\Validation\Attributes\Between;
use Phaseolies\Validation\Attributes\Integer;
use Phaseolies\Validation\Attributes\Length;
use Phaseolies\Validation\Attributes\NotBlank;
use Phaseolies\Validation\Attributes\StringType;
use Phaseolies\Validation\DtoValidator;
use Phaseolies\Validation\MessageResolver;
use PHPUnit\Framework\TestCase;

final class DtoValidationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testValidDtoHasNoErrorsAndNumericValuesCanBeNormalized(): void
    {
        $translator = Mockery::mock(Translator::class);
        $validator = new DtoValidator(new MessageResolver($translator));
        $dto = new BookPayload();

        $errors = $validator->errors($dto, [
            'title' => 'The Odyssey',
            'pages' => '320',
            'author' => 'Homer',
            'rating' => '4',
        ]);

        $normalized = $validator->normalize($dto, [
            'title' => 'The Odyssey',
            'pages' => '320',
            'author' => 'Homer',
            'rating' => '4',
        ]);

        $this->assertSame([], $errors);
        $this->assertSame(320, $normalized['pages']);
        $this->assertSame(4, $normalized['rating']);
    }

    public function testDtoErrorsUseValidationTranslationsAndAttributeNames(): void
    {
        $translator = Mockery::mock(Translator::class);
        $translator->shouldReceive('get')
            ->with('validation.attributes.pages', [], null)
            ->andReturn('number of pages');
        $translator->shouldReceive('get')
            ->with('validation.between', Mockery::on(function (array $replacements): bool {
                return $replacements[':attribute'] === 'number of pages'
                    && $replacements[':min'] === 1
                    && $replacements[':max'] === 10000;
            }))
            ->andReturn('The number of pages must be between 1 and 10000.');

        $validator = new DtoValidator(new MessageResolver($translator));
        $errors = $validator->errors(new BookPayload(), [
            'title' => 'The Odyssey',
            'pages' => '0',
            'author' => 'Homer',
            'rating' => '4',
        ]);

        $this->assertSame([
            'pages' => ['The number of pages must be between 1 and 10000.'],
        ], $errors);
    }

    public function testDtoErrorsAccumulateMultipleConstraints(): void
    {
        $translator = Mockery::mock(Translator::class);
        $translator->shouldReceive('get')->andReturnUsing(function (string $key, array $replace = [], ?string $locale = null): string {
            return $key;
        });

        $validator = new DtoValidator(new MessageResolver($translator));
        $errors = $validator->errors(new BookPayload(), [
            'pages' => 'not-an-integer',
            'rating' => '0',
        ]);

        $this->assertArrayHasKey('title', $errors);
        $this->assertArrayHasKey('author', $errors);
        $this->assertArrayHasKey('pages', $errors);
        $this->assertArrayHasKey('rating', $errors);
    }
}

final class BookPayload
{
    #[NotBlank]
    #[StringType]
    #[Length(max: 255)]
    public string $title;

    #[NotBlank]
    #[Integer]
    #[Between(min: 1, max: 10000)]
    public int $pages;

    #[NotBlank]
    #[StringType]
    #[Length(max: 255)]
    public string $author;

    #[NotBlank]
    #[Integer]
    #[Between(min: 1, max: 5)]
    public int $rating;
}
