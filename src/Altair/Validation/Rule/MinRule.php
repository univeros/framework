<?php
namespace Altair\Validation\Rule;

class MinRule extends AbstractRule
{
    /**
     * @var mixed the minimum valid value
     */
    protected $min;

    /**
     * MaxRule constructor.
     *
     * @param $min
     */
    public function __construct($min)
    {
        $this->min = $min;
    }

    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return $value >= $this->min;
    }

    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is smaller than "%s".', $value, $this->min);
    }
}
