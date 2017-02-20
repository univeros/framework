<?php
namespace Altair\Sanitation\Filter;

class AlphaFilter extends AbstractFilter
{
    public function parse($value)
    {
        return preg_replace('/[^\p{L}]/u', '', $value);
    }
}
