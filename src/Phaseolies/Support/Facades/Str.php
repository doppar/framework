<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\StringService substr(string $input, int $start, ?int $length = null): string
 * @method static \Phaseolies\Support\StringService len(string $input): int
 * @method static \Phaseolies\Support\StringService count_word(string $string): int
 * @method static \Phaseolies\Support\StringService is_palindrome(string $string): bool
 * @method static \Phaseolies\Support\StringService random(int $length = 10): string
 * @method static \Phaseolies\Support\StringService camel(string $input): string
 * @method static \Phaseolies\Support\StringService mask(string $string,int $visibleFromStart = 1,int $visibleFromEnd = 1,string $maskCharacter = '*' ): string
 * @method static \Phaseolies\Support\StringService truncate(string $string, int $maxLength, string $suffix = '...'): string
 * @method static \Phaseolies\Support\StringService snake(string $input): string
 * @method static \Phaseolies\Support\StringService title(string $input): string
 * @method static \Phaseolies\Support\StringService slug(string $input, string $separator = '-'): string
 * @method static \Phaseolies\Support\StringService contains(string $haystack, string $needle): bool
 * @method static \Phaseolies\Support\StringService limit_words(string $string, int $words, string $end = '...'): string
 * @method static \Phaseolies\Support\StringService remove_white_space(string $input): string
 * @method static \Phaseolies\Support\StringService uuid(): string
 * @method static \Phaseolies\Support\StringService starts_with(string $haystack, string $needle): bool
 * @method static \Phaseolies\Support\StringService ends_with(string $haystack, string $needle): bool
 * @method static \Phaseolies\Support\StringService studly(string $input): string
 * @method static \Phaseolies\Support\StringService reverse(string $input): string
 * @method static \Phaseolies\Support\StringService extract_numbers(string $input): string
 * @method static \Phaseolies\Support\StringService longest_common_Substring(string $str1, string $str2): string
 * @method static \Phaseolies\Support\StringService leet_speak(string $input): string
 * @method static \Phaseolies\Support\StringService extract_emails(string $input): array
 * @method static \Phaseolies\Support\StringService highlight_keyword(string $input, string $keyword, string $tag = 'strong'): string
 * @method static \Phaseolies\Support\StringService suffixAppend(string $input, string $suffix): string
 * @method static \Phaseolies\Support\StringService remove_suffix(string $input, string $suffix): string
 * @see \Phaseolies\Support\StringService
 */

use Phaseolies\Facade\BaseFacade;

class Str extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'str';
    }
}
