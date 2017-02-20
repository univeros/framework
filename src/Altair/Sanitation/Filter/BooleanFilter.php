<?php
namespace Altair\Sanitation\Filter;

class BooleanFilter extends AbstractFilter
{
    /**
     * @inheritdoc
     */
    public function parse($value)
    {
        if (!is_scalar($value)) {
            return null;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($filtered)
            ? (bool)$filtered
            : (bool)$value;
    }
}
