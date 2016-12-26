<?php
namespace Altair\Base\Support;

class Transliterator
{
    /**
     * Shortcut for `Any-Latin; NFKD` transliteration rule. The rule is strict, letters will be transliterated with
     * the closest sound-representation chars. The result may contain any UTF-8 chars. For example:
     * `获取到 どちら Українська: ґ,є, Српска: ђ, њ, џ! ¿Español?` will be transliterated to
     * `huò qǔ dào dochira Ukraí̈nsʹka: g̀,ê, Srpska: đ, n̂, d̂! ¿Español?`
     *
     * Used in [[transliterate()]].
     * For detailed information see [unicode normalization forms](http://unicode.org/reports/tr15/#Normalization_Forms_Table)
     * @see http://unicode.org/reports/tr15/#Normalization_Forms_Table
     * @see transliterate()
     */
    const TRANSLITERATE_STRICT = 'Any-Latin; NFKD';
    /**
     * Shortcut for `Any-Latin; Latin-ASCII` transliteration rule. The rule is medium, letters will be
     * transliterated to characters of Latin-1 (ISO 8859-1) ASCII table. For example:
     * `获取到 どちら Українська: ґ,є, Српска: ђ, њ, џ! ¿Español?` will be transliterated to
     * `huo qu dao dochira Ukrainsʹka: g,e, Srpska: d, n, d! ¿Espanol?`
     *
     * Used in [[transliterate()]].
     * For detailed information see [unicode normalization forms](http://unicode.org/reports/tr15/#Normalization_Forms_Table)
     * @see http://unicode.org/reports/tr15/#Normalization_Forms_Table
     * @see transliterate()
     */
    const TRANSLITERATE_MEDIUM = 'Any-Latin; Latin-ASCII';
    /**
     * Shortcut for `Any-Latin; Latin-ASCII; [\u0080-\uffff] remove` transliteration rule. The rule is loose,
     * letters will be transliterated with the characters of Basic Latin Unicode Block.
     * For example:
     * `获取到 どちら Українська: ґ,є, Српска: ђ, њ, џ! ¿Español?` will be transliterated to
     * `huo qu dao dochira Ukrainska: g,e, Srpska: d, n, d! Espanol?`
     *
     * Used in [[transliterate()]].
     * For detailed information see [unicode normalization forms](http://unicode.org/reports/tr15/#Normalization_Forms_Table)
     * @see http://unicode.org/reports/tr15/#Normalization_Forms_Table
     * @see transliterate()
     */
    const TRANSLITERATE_LOOSE = 'Any-Latin; Latin-ASCII; [\u0080-\uffff] remove';
    /**
     * @var mixed Either a [[\Transliterator]], or a string from which a [[\Transliterator]] can be built
     * for transliteration. Used by [[transliterate()]] when intl is available. Defaults to [[TRANSLITERATE_LOOSE]]
     * @see http://php.net/manual/en/transliterator.transliterate.php
     */
    protected $transliterator = self::TRANSLITERATE_LOOSE;
    /**
     * @var array fallback map for transliteration used by [[transliterate()]] when intl isn't available.
     */
    protected $transliteration = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
        'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
        'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
        'ÿ' => 'y',
    ];

    /**
     * Sets the transliterator when intl extension is available.
     *
     * @param string $transliterator
     * @return Transliterator
     */
    public function setTransliterator(string $transliterator): Transliterator
    {
        $this->transliterator = $transliterator;
        return $this;
    }

    /**
     * Merges a transliteration map into the default one. The map is used when there is no
     * intl extension available.
     *
     * @param array $transliteration
     * @return Transliterator
     */
    public function merge(array $transliteration): Transliterator
    {
        $this->transliteration = array_merge($this->transliteration, $transliteration);
        return $this;
    }

    /**
     * Returns a transliterated version of a string.
     *
     * If intl extension isn't available uses fallback that converts latin characters only and removes the rest.
     *
     * @param string $value the input string
     * @param string|\Transliterator|null $transliterator either a \Transliterator or a string from which a
     * \Transliterator can be built.
     * @return string
     */
    public function transliterate(string $value, $transliterator = null)
    {
        $transliterator = $transliterator?? $this->transliterator;
        return extension_loaded('intl')
            ? transliterator_transliterate($transliterator, $value)
            : strtr($value, $this->transliteration);
    }
}
