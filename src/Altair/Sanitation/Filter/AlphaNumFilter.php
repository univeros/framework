<?php
namespace Altair\Sanitation\Filter;

class AlphaNumFilter extends AbstractFilter
{
    public function parse($value)
    {
        return preg_replace('/[^\p{L}\p{Nd}]/u', '', $value);
    }
}
