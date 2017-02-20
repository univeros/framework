<?php
namespace Altair\Sanitation\Filter;

class TitleCaseFilter extends AbstractFilter
{
    /**
     * @inheritdoc
     */
    public function parse($value)
    {
        if (!is_string($value)) {
            return null;
        }
        return ucwords($value);
    }
}
