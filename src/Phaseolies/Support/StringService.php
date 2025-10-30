<?php

namespace Phaseolies\Support;

class StringService
{
    /**
     * Extract a substring from the given input string.
     *
     * @param string $input
     * @param int $start
     * @param int|null $length (Optional)
     * @return string
     */
    public function substr(string $input, int $start, ?int $length = null): string
    {
        return mb_substr($input, $start, $length, 'UTF-8');
    }

    /**
     * Compute the length of the given string.
     *
     * @param string $input
     * @return int
     */
    public function len(string $input): int
    {
        return mb_strlen($input, 'UTF-8');
    }

    /**
     * Count the number of words in a string.
     *
     * @param string $string
     * @return int
     */
    public function countWord(string $string): int
    {
        preg_match_all('/\p{L}[\p{L}\p{Mn}\p{Pd}\'’]*/u', $string, $matches);

        return count($matches[0]);
    }

    /**
     * Check if a given string is a palindrome.
     *
     * @param string $string
     * @return bool
     */
    public function isPalindrome(string $string): bool
    {
        $cleaned = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $string));

        return $cleaned === strrev($cleaned);
    }

    /**
     * Generate a random alphanumeric string of a given length.
     *
     * @param int $length
     * @return string
     */
    public function random(int $length = 10): string
    {
        return substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', $length)), 0, $length);
    }

    /**
     * Convert a snake_case or kebab-case string to camelCase
     *
     * @param string $input
     * @return string
     */
    function camel(string $input): string
    {
        $input = str_replace(['-', '_'], ' ', $input);
        $input = mb_convert_case($input, MB_CASE_TITLE, 'UTF-8');
        $input = str_replace(' ', '', $input);

        return lcfirst($input);
    }

    /**
     * Mask a string with a specified number of visible characters at the start and end.
     *
     * @param string $string
     * @param int $visibleFromStart
     * @param int $visibleFromEnd
     * @param string
     */
    public function mask(string $string, int $visibleFromStart = 1, int $visibleFromEnd = 1, string $maskCharacter = '*'): string
    {
        $length = strlen($string);

        if ($length <= $visibleFromStart + $visibleFromEnd) {
            return $string;
        }

        $startPart  = substr($string, 0, $visibleFromStart);
        $endPart    = substr($string, -$visibleFromEnd);
        $middlePart = str_repeat($maskCharacter, $length - $visibleFromStart - $visibleFromEnd);

        return $startPart . $middlePart . $endPart;
    }

    /**
     * Truncate a string to a specific length and append a suffix if truncated.
     *
     * @param string $string
     * @param int $maxLength
     * @param string $suffix
     * @return string
     */
    public function truncate(string $string, int $maxLength, string $suffix = '...'): string
    {
        return (strlen($string) > $maxLength) ? substr($string, 0, $maxLength) . $suffix : $string;
    }

    /**
     * Convert a camelCase string to snake_case.
     *
     * @param string $input
     * @return string
     */
    public function snake(string $input): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
    }

    /**
     * Convert a string to title case (each word capitalized).
     *
     * @param string $input
     * @return string
     */
    public function title(string $input): string
    {
        return mb_convert_case($input, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Generate a URL-friendly slug from a string.
     *
     * @param string $input
     * @param string $separator
     * @return string
     */
    public function slug(string $input, string $separator = '-'): string
    {
        $slug = mb_strtolower($input, 'UTF-8');
        $slug = preg_replace('/[\s,\.;\'"!\?]+/u', $separator, $slug);
        $slug = preg_replace('/[^\p{L}\p{Nd}' . preg_quote($separator, '/') . ']+/u', '', $slug);
        $slug = preg_replace('/' . preg_quote($separator, '/') . '{2,}/u', $separator, $slug);

        return trim($slug, $separator);
    }

    /**
     * Check if a string contains another string (case-insensitive or sensitive).
     *
     * @param string $haystack
     * @param string|array $needles
     * @param bool $ignoreCase
     * @return bool
     */
    public function contains(string $haystack, string | array $needles, bool $ignoreCase = true): bool
    {
        if (is_array($needles)) {
            foreach ($needles as $needle) {
                if ($this->contains($haystack, $needle, $ignoreCase)) {
                    return true;
                }
            }
            return false;
        }

        if ($ignoreCase) {
            return mb_stripos($haystack, $needles, 0, 'UTF-8') !== false;
        }

        return mb_strpos($haystack, $needles, 0, 'UTF-8') !== false;
    }

    /**
     * Limit the number of words in a string, handling Unicode and preserving
     * natural word boundaries (splitting on whitespace).
     *
     * @param string $string
     * @param int $words
     * @param string $end
     * @param string $encoding
     * @return string
     */
    function limitWords(string $string, int $words, string $end = '...', string $encoding = 'UTF-8'): string
    {
        $trimmed = trim($string);

        if ($trimmed === '') {
            return '';
        }

        $wordArray = preg_split('/\s+/u', $trimmed);

        // If the total number of words is less than or equal to $words, return original.
        if (count($wordArray) <= $words) {
            return $string;
        }

        $limited = implode(' ', array_slice($wordArray, 0, $words));

        return $limited . $end;
    }

    /**
     * Determine if a string matches a given pattern.
     *
     * @param string $pattern
     * @param string $value
     * @return bool
     */
    public function is(string $pattern, string $value): bool
    {
        if ($pattern === $value) {
            return true;
        }

        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('#^' . $pattern . '\z#u', $value);
    }

    /**
     * Remove all whitespace from a string.
     *
     * @param string $input
     * @return string
     */
    public function removeWhiteSpace(string $input): string
    {
        // Use a Unicode‐aware regex to strip any sequence of whitespace characters.
        // The 'u' modifier ensures proper handling of multibyte (UTF‐8) input.
        return preg_replace('/\s+/u', '', $input) ?? '';
    }

    /**
     * Generate a UUID v4 string.
     *
     * @return string The generated UUID.
     */
    public function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Check if a string starts with one or more given needles (case-sensitive).
     *
     * @param string $haystack
     * @param string|array $needles
     * @return bool
     */
    public function startsWith(string $haystack, string | array $needles): bool
    {
        // Cast $needles to an array so we can handle a single string or multiple needles uniformly.
        foreach ((array) $needles as $needle) {
            // Skip empty needles (""), since every string technically starts with an empty string.
            if ($needle === '') {
                continue;
            }

            // mb_strpos returns the position of the first occurrence of $needle in $haystack.
            // If that position is exactly 0, $haystack begins with $needle.
            if (mb_strpos($haystack, $needle, 0, 'UTF-8') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a string ends with one or more given needles (case‐sensitive).
     * This version is Unicode‐safe (works with any UTF‐8 text).
     *
     * @param string $haystack
     * @param string|array $needles
     * @param string $encoding
     * @return bool
     */
    public function endsWith(string $haystack, string | array $needles, string $encoding = 'UTF-8'): bool
    {
        $haystackLength = mb_strlen($haystack, $encoding);

        // Cast needles to array so we can loop uniformly.
        foreach ((array) $needles as $needle) {
            // Skip empty needle—every string “ends” with an empty string, but we usually don’t want to count that.
            if ($needle === '') {
                continue;
            }

            $needleLength = mb_strlen($needle, $encoding);

            if ($needleLength > $haystackLength) {
                continue;
            }

            $endingSegment = mb_substr($haystack, $haystackLength - $needleLength, $needleLength, $encoding);

            if ($endingSegment === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a string to StudlyCase (each “word” capitalized, no separators).
     *
     * @param string $input
     * @param string $encoding
     * @return string
     */
    public function studly(string $input, string $encoding = 'UTF-8'): string
    {
        $spaced = str_replace(['-', '_'], ' ', $input);

        $titled = mb_convert_case($spaced, MB_CASE_TITLE, $encoding);

        $studly = str_replace(' ', '', $titled);

        return $studly;
    }

    /**
     * Reverse a UTF-8 string while preserving multi-byte characters.
     *
     * @param string $input
     * @param string $encoding
     * @return string
     */
    public function reverse(string $input, string $encoding = 'UTF-8'): string
    {
        if (function_exists('mb_str_split')) {
            $chars = mb_str_split($input, 1, $encoding);
        } else {
            $chars = preg_split('/(?<!^)(?!$)/u', $input);
            if ($chars === false) {
                return $input;
            }
        }

        return implode('', array_reverse($chars));
    }

    /**
     * Extract all numeric digits from a string.
     *
     * @param string $input
     * @return string
     */
    public function extractNumbers(string $input): string
    {
        return preg_replace('/\D/', '', $input);
    }

    /**
     * Find the longest common substring between two strings.
     *
     * @param string $str1
     * @param string $str2
     * @return string
     */
    public function longestCommonSubstring(string $str1, string $str2): string
    {
        $matrix    = array_fill(0, strlen($str1) + 1, array_fill(0, strlen($str2) + 1, 0));
        $maxLength = 0;
        $endIndex  = 0;

        for ($i = 1; $i <= strlen($str1); $i++) {
            for ($j = 1; $j <= strlen($str2); $j++) {
                if ($str1[$i - 1] === $str2[$j - 1]) {
                    $matrix[$i][$j] = $matrix[$i - 1][$j - 1] + 1;
                    if ($matrix[$i][$j] > $maxLength) {
                        $maxLength = $matrix[$i][$j];
                        $endIndex  = $i;
                    }
                }
            }
        }

        return substr($str1, $endIndex - $maxLength, $maxLength);
    }

    /**
     * Convert a string to leetspeak (1337).
     *
     * @param string $input
     * @return string
     */
    public function leetSpeak(string $input): string
    {
        $map = [
            'a' => '4',
            'e' => '3',
            'i' => '1',
            'o' => '0',
            's' => '5',
            't' => '7'
        ];

        return strtr(strtolower($input), $map);
    }

    /**
     * Extract all email addresses from a string.
     *
     * @param string $input
     * @return array
     */
    public function extractEmails(string $input): array
    {
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $input, $matches);

        return $matches[0] ?? [];
    }

    /**
     * Highlight all occurrences of a keyword in a string using HTML tags.
     *
     * @param string $input
     * @param string $keyword
     * @param string $tag
     * @return string
     */
    public function highlightKeyword(string $input, string $keyword, string $tag = 'strong'): string
    {
        if (empty($keyword)) {
            return $input;
        }

        return preg_replace(
            '/(' . preg_quote($keyword, '/') . ')/i',
            "<$tag>$1</$tag>",
            $input
        );
    }

    /**
     * Convert string to uppercase
     *
     * @param string $input
     * @return string
     */
    public function toUpper(string $input): string
    {
        return strtoupper($input);
    }

    /**
     * Append a suffix to a string if it doesn't already end with it.
     *
     * @param string $input
     * @param string $suffix
     * @return string
     */
    public function suffixAppend(string $input, string $suffix): string
    {
        if (str()->endsWith($input, $suffix)) {
            return $input;
        }

        return strtoupper($input[0]) . substr($input, 1) . $suffix;
    }

    /**
     * Remove a suffix from a string.
     *
     * @param string $input
     * @param string $suffix
     * @return string
     */
    public function removeSuffix(string $input, string $suffix): string
    {
        return substr($input, 0, -strlen($suffix));
    }

    /**
     * Get the portion of $subject after the first occurrence of $search.
     * 
     * @param string $subject
     * @param string $search
     * @return string
     */
    public function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strpos($subject, $search, 0, 'UTF-8');

        return $pos === false ? $subject : mb_substr($subject, $pos + mb_strlen($search, 'UTF-8'), null, 'UTF-8');
    }

    /**
     * Get the portion of $subject before the first occurrence of $search.
     * 
     * @param string $subject
     * @param string $search
     * @return string
     */
    public function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strpos($subject, $search, 0, 'UTF-8');

        return $pos === false ? $subject : mb_substr($subject, 0, $pos, 'UTF-8');
    }

    /**
     * Get the portion between two values.
     * 
     * @param string $subject
     * @param string $from
     * @param string $to
     * @return string
     */
    public function between(string $subject, string $from, string $to): string
    {
        $start = $this->after($subject, $from);
        return $this->before($start, $to);
    }

    /**
     * Determine if a given string is valid JSON.
     *
     * @param string $value
     * @return bool
     */
    public function isJson(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    public function urlHarmonize(string $url): string
    {
        return str_replace("\\", "/", $url);
    }
}
