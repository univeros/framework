<?php
namespace Altair\Sanitation\Filter;


class BetweenFilter extends AbstractFilter
{
    /**
     * @var mixed the minimum value
     */
    protected $min;
    /**
     * @var mixed the maximum value
     */
    protected $max;

    /**
     * BetweenFilter constructor.
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
    public function parse($value)
    {
        if ($value < $this->min) {
            return $this->min;
        }
        if ($value > $this->max) {
            return $this->max;
        }

        return $value;
    }

}
