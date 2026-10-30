<?php

namespace Phaseolies\Support\Contracts;

interface StringServiceInterface
{
    /**
     * Extract a substring from the given input string.
     *
     * @param string $input
     * @param int $start
     * @param int|null $length (Optional)
     * @return string
     */
    public function substr(string $input, int $start, ?int $length = null): string;

    /**
     * Compute the length of the given string.
     *
     * @param string $input
     * @return int
     */
    public function len(string $input): int;

    /**
     * Count the number of words in a string.
     *
     * @param string $string
     * @return int
     */
    public function countWord(string $string): int;

    /**
     * Check if a given string is a palindrome.
     *
     * @param string $string
     * @return bool
     */
    public function isPalindrome(string $string): bool;

    /**
     * Generate a random alphanumeric string of a given length.
     *
     * @param int $length
     * @return string
     */
    public function random(int $length = 10): string;

    /**
     * Convert a snake_case or kebab-case string to camelCase
     *
     * @param string $input
     * @return string
     */
    public function camel(string $input): string;

    /**
     * Mask a string with a specified number of visible characters at the start and end.
     *
     * @param string $string
     * @param int $visibleFromStart
     * @param int $visibleFromEnd
     * @param string $maskCharacter
     * @return string
     */
    public function mask(string $string, int $visibleFromStart = 1, int $visibleFromEnd = 1, string $maskCharacter = '*'): string;

    /**
     * Truncate a string to a specific length and append a suffix if truncated.
     *
     * @param string $string
     * @param int $maxLength
     * @param string $suffix
     * @return string
     */
    public function truncate(string $string, int $maxLength, string $suffix = '...'): string;

    /**
     * Convert a camelCase string to snake_case.
     *
     * @param string $input
     * @return string
     */
    public function snake(string $input): string;

    /**
     * Convert a string to title case (each word capitalized).
     *
     * @param string $input
     * @return string
     */
    public function title(string $input): string;

    /**
     * Generate a URL-friendly slug from a string.
     *
     * @param string $input
     * @param string $separator
     * @return string
     */
    public function slug(string $input, string $separator = '-'): string;

    /**
     * Check if a string contains another string (case-insensitive or sensitive).
     *
     * @param string $haystack
     * @param string|array $needles
     * @param bool $ignoreCase
     * @return bool
     */
    public function contains(string $haystack, string | array $needles, bool $ignoreCase = true): bool;

    /**
     * Limit the number of words in a string, handling Unicode and preserving  natural word boundaries
     *
     * @param string $string
     * @param int $words
     * @param string $end
     * @param string $encoding
     * @return string
     */
    public function limitWords(string $string, int $words, string $end = '...', string $encoding = 'UTF-8'): string;

    /**
     * Determine if a string matches a given pattern.
     *
     * @param string $pattern
     * @param string $value
     * @return bool
     */
    public function is(string $pattern, string $value): bool;

    /**
     * Remove all whitespace from a string.
     *
     * @param string $input
     * @return string
     */
    public function removeWhiteSpace(string $input): string;

    /**
     * Generate a UUID v4 string.
     *
     * @return string The generated UUID.
     */
    public function uuid(): string;

    /**
     * Check if a string starts with one or more given needles (case-sensitive).
     *
     * @param string $haystack
     * @param string|array $needles
     * @return bool
     */
    public function startsWith(string $haystack, string | array $needles): bool;

    /**
     * Check if a string ends with one or more given needles (case-sensitive).
     *
     * @param string $haystack
     * @param string|array $needles
     * @param string $encoding
     * @return bool
     */
    public function endsWith(string $haystack, string | array $needles, string $encoding = 'UTF-8'): bool;

    /**
     * Convert a string to StudlyCase (each “word” capitalized, no separators).
     *
     * @param string $input
     * @param string $encoding
     * @return string
     */
    public function studly(string $input, string $encoding = 'UTF-8'): string;

    /**
     * Reverse a UTF-8 string while preserving multi-byte characters.
     *
     * @param string $input
     * @param string $encoding
     * @return string
     */
    public function reverse(string $input, string $encoding = 'UTF-8'): string;

    /**
     * Extract all numeric digits from a string.
     *
     * @param string $input
     * @return string
     */
    public function extractNumbers(string $input): string;

    /**
     * Find the longest common substring between two strings.
     *
     * @param string $str1
     * @param string $str2
     * @return string
     */
    public function longestCommonSubstring(string $str1, string $str2): string;

    /**
     * Convert a string to leetspeak (1337).
     *
     * @param string $input
     * @return string
     */
    public function leetSpeak(string $input): string;

    /**
     * Extract all email addresses from a string.
     *
     * @param string $input
     * @return array
     */
    public function extractEmails(string $input): array;

    /**
     * Highlight all occurrences of a keyword in a string using HTML tags.
     *
     * @param string $input
     * @param string $keyword
     * @param string $tag
     * @return string
     */
    public function highlightKeyword(string $input, string $keyword, string $tag = 'strong'): string;

    /**
     * Convert string to uppercase
     *
     * @param string $input
     * @return string
     */
    public function toUpper(string $input): string;

    /**
     * Append a suffix to a string if it doesn't already end with it.
     *
     * @param string $input
     * @param string $suffix
     * @return string
     */
    public function suffixAppend(string $input, string $suffix): string;

    /**
     * Remove a suffix from a string.
     *
     * @param string $input
     * @param string $suffix
     * @return string
     */
    public function removeSuffix(string $input, string $suffix): string;

    /**
     * Get the portion of $subject after the first occurrence of $search.
     * 
     * @param string $subject
     * @param string $search
     * @return string
     */
    public function after(string $subject, string $search): string;

    /**
     * Get the portion of $subject before the first occurrence of $search.
     * 
     * @param string $subject
     * @param string $search
     * @return string
     */
    public function before(string $subject, string $search): string;

    /**
     * Get the portion between two values.
     * 
     * @param string $subject
     * @param string $from
     * @param string $to
     * @return string
     */
    public function between(string $subject, string $from, string $to): string;

    /**
     * Determine if a given string is valid JSON.
     *
     * @param string $value
     * @return bool
     */
    public function isJson(string $value): bool;

    /**
     * Harmonize the given URL
     *
     * @param string $url
     * @return string
     */
    public function urlHarmonize(string $url): string;
}
