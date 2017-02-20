<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\TrimFilter;

class TrimFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            [' antonio ', 'antonio'],
            ["\t12 3", '12 3'],
            [[], null],
            ['antonio ramirez', 'antonio ramirez'],
            [" \t\n\r\0\x0B", ''],
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new TrimFilter();
    }
}
