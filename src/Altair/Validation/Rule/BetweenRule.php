<?php
namespace Validation\Rule;

use Altair\Validation\Rule\AbstractRule;

class BetweenRule extends AbstractRule
{
    /**
     * @var
     */
    protected $min;
    /**
     * @var
     */
    protected $max;

    /**
     * BetweenRule constructor.
     *
     * @param mixed $min
     * @param mixed $max
     */
    public function __construct($min, $max)
    {
        $this->min = $min;
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

        return $value >= $this->min && $value <= $this->max;
    }

    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not between "%s" and "%s"', $value, $this->min, $this->max);
    }
}
