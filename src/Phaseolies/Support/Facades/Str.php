<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\StringService substr(string $input, int $start, ?int $length = null): string
 * @method static \Phaseolies\Support\StringService len(string $input): int
 * @method static \Phaseolies\Support\StringService count_word(string $string): int
 * @method static \Phaseolies\Support\StringService isPalindrome(string $string): bool
 * @method static \Phaseolies\Support\StringService random(int $length = 10): string
 * @method static \Phaseolies\Support\StringService camel(string $input): string
 * @method static \Phaseolies\Support\StringService mask(string $string,int $visibleFromStart = 1,int $visibleFromEnd = 1,string $maskCharacter = '*' ): string
 * @method static \Phaseolies\Support\StringService truncate(string $string, int $maxLength, string $suffix = '...'): string
 * @method static \Phaseolies\Support\StringService snake(string $input): string
 * @method static \Phaseolies\Support\StringService title(string $input): string
 * @method static \Phaseolies\Support\StringService slug(string $input, string $separator = '-'): string
 * @method static \Phaseolies\Support\StringService contains(string $haystack, string $needle): bool
 * @method static \Phaseolies\Support\StringService limitWords(string $string, int $words, string $end = '...'): string
 * @method static \Phaseolies\Support\StringService remove_white_space(string $input): string
 * @method static \Phaseolies\Support\StringService uuid(): string
 * @method static \Phaseolies\Support\StringService startsWith(string $haystack, string $needle): bool
 * @method static \Phaseolies\Support\StringService endsWith(string $haystack, string $needle): bool
 * @method static \Phaseolies\Support\StringService studly(string $input): string
 * @method static \Phaseolies\Support\StringService reverse(string $input): string
 * @method static \Phaseolies\Support\StringService extractNumbers(string $input): string
 * @method static \Phaseolies\Support\StringService longestCommonSubstring(string $str1, string $str2): string
 * @method static \Phaseolies\Support\StringService leetSpeak(string $input): string
 * @method static \Phaseolies\Support\StringService extractEmails(string $input): array
 * @method static \Phaseolies\Support\StringService highlightKeyword(string $input, string $keyword, string $tag = 'strong'): string
 * @method static \Phaseolies\Support\StringService suffixAppend(string $input, string $suffix): string
 * @method static \Phaseolies\Support\StringService removeSuffix(string $input, string $suffix): string
 * @method static \Phaseolies\Support\StringService after(string $subject, string $search): string
 * @method static \Phaseolies\Support\StringService before(string $subject, string $search): string
 * @method static \Phaseolies\Support\StringService between(string $subject, string $search): string
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
