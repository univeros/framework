<?php
namespace Validation\Rule;

use Altair\Validation\Rule\AbstractRule;

class MaxRule extends AbstractRule
{
    /**
     * @var mixed the maximum valid value
     */
    protected $max;

    /**
     * MaxRule constructor.
     *
     * @param $max
     */
    public function __construct($max)
    {
        $this->max = $max;
    }

    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return $value <= $this->max;
    }

    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is bigger than "%s".', $value, $this->max);
    }
}
