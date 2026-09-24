<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static string substr(string $input, int $start, ?int $length = null)
 * @method static int len(string $input)
 * @method static int countWord(string $string)
 * @method static bool isPalindrome(string $string)
 * @method static string random(int $length = 10)
 * @method static string camel(string $input)
 * @method static string mask(string $string,int $visibleFromStart = 1,int $visibleFromEnd = 1,string $maskCharacter = '*' )
 * @method static string truncate(string $string, int $maxLength, string $suffix = '...')
 * @method static string snake(string $input)
 * @method static string title(string $input)
 * @method static string slug(string $input, string $separator = '-')
 * @method static bool contains(string $haystack, string|array $needles, bool $ignoreCase = true)
 * @method static string limitWords(string $string, int $words, string $end = '...')
 * @method static string removeWhiteSpace(string $input)
 * @method static string uuid()
 * @method static bool startsWith(string $haystack, string|array $needles)
 * @method static bool endsWith(string $haystack, string|array $needles, string $encoding = 'UTF-8')
 * @method static string studly(string $input)
 * @method static string reverse(string $input)
 * @method static string extractNumbers(string $input)
 * @method static string longestCommonSubstring(string $str1, string $str2)
 * @method static string leetSpeak(string $input)
 * @method static array extractEmails(string $input)
 * @method static string highlightKeyword(string $input, string $keyword, string $tag = 'strong')
 * @method static string suffixAppend(string $input, string $suffix)
 * @method static string removeSuffix(string $input, string $suffix)
 * @method static string after(string $subject, string $search)
 * @method static string before(string $subject, string $search)
 * @method static string between(string $subject, string $from, string $to)
 * @method static bool isJson(string $value)
 * @method static string urlHarmonize(string $url)
 * @see \Phaseolies\Support\StringService
 */
class Str extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'str';
    }
}
