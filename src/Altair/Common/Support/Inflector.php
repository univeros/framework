<?php
namespace Altair\Common\Support;

class Inflector
{
    /**
     * @var Transliterator
     */
    protected $transliterator;
    /**
     * @var Pluralizer
     */
    protected $pluralizer;

    /**
     * Inflector constructor.
     *
     * @param Transliterator $transliterator
     * @param Pluralizer $pluralizer
     */
    public function __construct(Transliterator $transliterator, Pluralizer $pluralizer)
    {
        $this->transliterator = $transliterator;
        $this->pluralizer = $pluralizer;
    }

    /**
     * Returns a string with all spaces converted to given replacement,
     * non word characters removed and the rest of characters transliterated.
     *
     * If intl extension isn't available uses fallback that converts latin characters only
     * and removes the rest. You may customize characters map via $transliteration property
     * of the helper.
     *
     * @param string $value the string to convert
     * @param string $replacement the replacement to use for spaces
     * @param bool $lowercase whether to return the string in lowercase or not. Defaults to true.
     *
     * @return string
     */
    public function slug(string $value, string $replacement = '-', $lowercase = true): string
    {
        $value = $this->transliterator->transliterate($value);
        $value = preg_replace('/[^a-zA-Z0-9=\s—–-]+/u', '', $value);
        $value = preg_replace('/[=\s—–-]+/u', $replacement, $value);
        $value = trim($value, $replacement);

        return $lowercase ? strtolower($value) : $value;
    }

    /**
     * Converts an ID into a CamelCase name.
     * Words in the ID separated by `$separator` (defaults to '-') will be concatenated into a CamelCase name.
     * For example, 'post-tag' is converted to 'PostTag'.
     *
     * @param string $id the id to be converted
     * @param string $separator the character used to separate the words in the id
     * @return string
     */
    public function idToCamel(string $id, string $separator = '-'): string
    {
        return str_replace(' ', '', ucwords(implode(' ', explode($separator, $id))));
    }

    /**
     * Converts a CamelCase name into an ID in lowercase.
     * Words in the ID may be concatenated using the specified character (defaults to '-').
     * For example, 'PostTag' will be converted to 'post-tag'.
     *
     * @param string $name the string to be converted
     * @param string $separator the character used to concatenate the words in the id
     * @param bool $strict whether to insert a separator between two consecutive uppercase chars, defaults to false
     * @return string
     */
    public function camelToId(string $name, string $separator = '-', bool $strict = false): string
    {
        $regex = $strict ? '/[A-Z]/' : '/(?<![A-Z])[A-Z]/';
        if ($separator === '_') {
            return trim(strtolower(preg_replace($regex, '_\0', $name)), '_');
        }
        return trim(
            strtolower(str_replace('_', $separator, preg_replace($regex, $separator . '\0', $name))),
                $separator
        );
    }

    /**
     * @param $name
     * @param bool $uppercase
     * @return string
     */
    public function camelToWords(string $name, bool $uppercase = true): string
    {
        $label = trim(strtolower(str_replace([
            '-',
            '_',
            '.',
        ], ' ', preg_replace('/(?<![A-Z])[A-Z]/', ' \0', $name))));
        return $uppercase ? ucwords($label) : $label;
    }

    /**
     * Returns a human-readable string from $word
     *
     * @param string $value the string to humanize
     * @param bool $upper whether to set all words to uppercase or not
     * @return string
     */
    public function humanize(string $value, bool $upper = false): string
    {
        $value = str_replace('_', ' ', preg_replace('/_id$/', '', $value));
        return $upper ? ucwords($value) : ucfirst($value);
    }

    /**
     * Converts any "CamelCased" into an "underscored_word".
     *
     * @param string $value
     * @return string
     */
    public function underscore(string $value): string
    {
        return strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $value));
    }

    /**
     * Returns given word as CamelCased. Converts a word like "send_email" to "SendEmail". It will remove non
     * alphanumeric character from the word, so "who's online" will be converted to "WhoSOnline"
     *
     * @param string $value the word to CamelCase
     * @return string
     */
    public function camel(string $value): string
    {
        return str_replace(' ', '', ucwords(preg_replace('/[^A-Za-z0-9]+/', ' ', $value)));
    }

    /**
     * Converts an underscored or CamelCase word into a English sentence.
     *
     * @param string $value the words to convert into an English sentence
     * @param bool $uppercase whether to convert all words to uppercase
     * @return string
     */
    public function title(string $value, bool $uppercase = false): string
    {
        $value = $this->humanize($this->underscore($value), $uppercase);
        return $uppercase ? ucwords($value) : ucfirst($value);
    }

    /**
     * Converts number to its ordinal English form. For example, converts 13 to 13th, 2 to 2nd ...
     *
     * @param int $number the number to get its ordinal value
     * @return string
     */
    public function ordinal(int $number): string
    {
        if (in_array($number % 100, range(11, 13))) {
            return $number . 'th';
        }
        switch ($number % 10) {
            case 1:
                return $number . 'st';
            case 2:
                return $number . 'nd';
            case 3:
                return $number . 'rd';
            default:
                return $number . 'th';
        }
    }

    /**
     * Converts a table name to its class name. For example, converts "people" to "Person"
     *
     * @param string $value the table name
     * @return string
     */
    public function classify(string $value): string
    {
        return $this->camel($this->pluralizer->singular($value));
    }

    /**
     * Converts a class name to its table name (pluralized) naming conventions.
     * For example, converts "Person" to "people"
     *
     * @param string $value the class name for getting related table name
     * @return string
     */
    public function table(string $value): string
    {
        return $this->pluralizer->pluralize($this->underscore($value));
    }

    /**
     * Same as camelize but first char is in lowercase. Converts a word like "send_email" to "sendEmail". It will
     * remove non alphanumeric character from the word, so "who's online" will be converted to "whoSOnline"
     *
     * @param string $value to lowerCamelCase
     * @return string
     */
    public function variable(string $value): string
    {
        $value = $this->camel($value);

        return strtolower($value[0]) . substr($value, 1);
    }
}
