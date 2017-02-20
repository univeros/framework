<?php
namespace Altair\Sanitation\Filter;

class MaxFilter extends AbstractFilter
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
    public function parse($value)
    {
        if (!is_scalar($value)) {
            return null;
        }

        return $value > $this->max ? $this->max : $value;
    }

}
