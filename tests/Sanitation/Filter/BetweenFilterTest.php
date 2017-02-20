<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\BetweenFilter;

class BetweenFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            [2, 2],
            [3, 3],
            [4, 4],
            [5, 5],
            [6, 6],
            [-1, 2],
            [1, 2],
            [7, 6]
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new BetweenFilter(2, 6);
    }
}
