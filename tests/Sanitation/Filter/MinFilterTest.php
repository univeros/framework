<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\MinFilter;

class MinFilterTest extends AbstractFilterTest
{
    #[\Override]
    public static function dataProvider(): array
    {
        return [
            [4, 4],
            [5, 5],
            [6, 6],
            [[], null],
            [1, 4],
            [2, 4],
            [3, 4],
        ];
    }

    #[\Override]
    protected function buildFilter(): FilterInterface
    {
        return new MinFilter(4);
    }
}
