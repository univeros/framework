<?php
namespace Altair\Base\Support;

class Str
{
    /**
     * Returns the number of bytes in the given string.
     * This method ensures the string is treated as a byte array by using `mb_strlen()`.
     *
     * @param string $value the string being measured for length
     * @param string $encoding the encoding to be used
     * @return int
     */
    public function byteLength(string $value, string $encoding = '8bit'): int
    {
        return mb_strlen($value, $encoding);
    }

    /**
     * Returns the portion of string specified by the start and length parameters.
     * This method ensures the string is treated as a byte array by using `mb_substr()`.
     *
     * @param string $value the input string. Must be one character or longer.
     * @param int $start the starting position.
     * @param int|null $length the desired portion length. If none specified, there will be no limit on length.
     * @param string $encoding
     * @return string
     */
    public function byteSubString(string $value, int $start, int $length = null, string $encoding = '8bit'): string
    {
        return mb_substr($value, $start, $length === null ? mb_strlen($value, $encoding) : $length, $encoding);
    }

    /**
     * Truncates a string to the number of characters specified.
     *
     * @param string $value the string to truncate
     * @param int $length the number of characters to include into the truncated string
     * @param string $suffix the string to append at the end of the truncated string
     * @param string $encoding the charset to use.
     * @return string
     */
    public function truncate(string $value, int $length, string $suffix = '...', string $encoding = '8bit'): string
    {
        return mb_strlen($value, $encoding) > $length
            ? rtrim(mb_substr($value, 0, $length, $encoding)) . $suffix
            : $value;
    }

    /**
     * Truncates a string to the number of words specified.
     *
     * @param string $value the string to truncate
     * @param int $count how many words from original string to include into the truncated string
     * @param string $suffix the string to append to the end of truncated string
     * @return string
     */
    public function truncateWords(string $value, int $count, string $suffix = '...'): string
    {
        $words = preg_split('/(\s+)/u', trim($value), null, PREG_SPLIT_DELIM_CAPTURE);

        return count($words) / 2 > $count
            ? implode('', array_slice($words, 0, ($count * 2) - 1)) . $suffix
            : $value;
    }

    /**
     * Checks if a given string starts with specified substring. Binary and multibyte safe.
     *
     * @param string $haystack the input string
     * @param string $needle the part to search inside the $haystack
     * @param bool $caseSensitive whether is case sensitive search. Defaults to true.
     * @param string $encoding the encoding to use.
     * @return bool
     */
    public function startsWith(
        string $haystack,
        string $needle,
        bool $caseSensitive = true,
        string $encoding = '8bit'
    ): bool {
        if (!$bytes = $this->byteLength($needle)) {
            return true;
        }
        return $caseSensitive
            ? strncmp($haystack, $needle, $bytes) === 0
            : mb_strtolower(mb_substr($haystack, 0, $bytes, '8bit'), $encoding) === mb_strtolower($needle, $encoding);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @param bool $caseSensitive
     * @param string $encoding
     * @return bool
     */
    public function endsWith(
        string $haystack,
        string $needle,
        bool $caseSensitive = true,
        string $encoding = '8bit'
    ): bool {
        if (!$bytes = static::byteLength($needle)) {
            return true;
        }
        if ($caseSensitive) {
            if ($this->byteLength($haystack) < $bytes) {
                return false;
            }
            return substr_compare($haystack, $needle, -$bytes, $bytes) === 0;
        } else {
            return mb_strtolower(mb_substr($haystack, -$bytes, mb_strlen($haystack, '8bit'), '8bit'),
                    $encoding) === mb_strtolower($needle, $encoding);
        }
    }

    /**
     * Counts the words of a string.
     *
     * @param string $value the string to count words
     * @return int
     */
    public function countWords(string $value): int
    {
        return count(preg_split('/\s+/u', $value, null, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * Replace the first occurrence of a given value in the string.
     *
     * @param string $search the string to search for.
     * @param string $replace the string used to replace .
     * @param string $subject the string to search and replace.
     * @return string
     */
    public function replaceFirst(string $search, string $replace, string $subject): string
    {
        $position = strpos($subject, $search);
        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }
        return $subject;
    }

    /**
     * Replace the last occurrence of a given value in the string.
     *
     * @param string $search the string to search for.
     * @param string $replace the string to replace.
     * @param string $subject the string to search and replace.
     * @return string
     */
    public function replaceLast(string $search, string $replace, string $subject): string
    {
        $position = strrpos($subject, $search);
        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }
        return $subject;
    }
}
