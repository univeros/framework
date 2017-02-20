<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\MaxFilter;

class MaxFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            [1, 1],
            [2, 2],
            [3, 3],
            [[], null],
            [4, 3],
            [5, 3],
            [6, 3],
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new MaxFilter(3);
    }
}
