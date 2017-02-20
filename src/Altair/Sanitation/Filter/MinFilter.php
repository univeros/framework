<?php
namespace Altair\Sanitation\Filter;

class MinFilter extends AbstractFilter
{
    /**
     * @var mixed the minimum valid value
     */
    protected $min;

    /**
     * MinFilter constructor.
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
    public function parse($value)
    {
        if (!is_scalar($value)) {
            return null;
        }

        return $value < $this->min ? $this->min : $value;
    }

}
