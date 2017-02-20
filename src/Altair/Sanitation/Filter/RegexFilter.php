<?php
namespace Altair\Sanitation\Filter;


class RegexFilter extends AbstractFilter
{
    /**
     * @var string the regular expression to be matched with.
     */
    protected $pattern;
    /**
     * @var string
     */
    protected $replace;

    /**
     * RegexRule constructor.
     *
     * @param string $pattern
     * @param string $replace
     */
    public function __construct(string $pattern, string $replace)
    {
        $this->pattern = $pattern;
        $this->replace = $replace;
    }

    /**
     * @inheritdoc
     */
    public function parse($value)
    {
        if (!is_scalar($value)) {
            return null;
        }

        return preg_replace($this->pattern, $this->replace, $value);
    }
}
