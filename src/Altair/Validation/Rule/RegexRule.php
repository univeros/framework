<?php
namespace Altair\Validation\Rule;

class RegexRule extends AbstractRule
{
    /**
     * @var string the regular expression to be matched with.
     */
    protected $pattern;

    /**
     * RegexRule constructor.
     *
     * @param string $pattern
     */
    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return preg_match($this->pattern, $value);
    }

    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is invalid.', $value, $this->pattern);
    }
}
