<?php
namespace Altair\Sanitation\Filter;


class IntegerFilter extends AbstractFilter
{
    /**
     * @param mixed $value
     *
     * @return int|mixed|null
     */
    public function parse($value)
    {
        if (!is_scalar($value)) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return (int)$value;
        }
        if (is_numeric($value)) {
            // double case to honor scientific notation
            // (int) 1E5 == 15, but (int) (float) 1E5 == 100000
            return (int)((float)$value);
        }

        return null;
    }

}
