<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\MinStrLengthFilter;

class MinStrLenFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            ['antonio', 'antonio'],
            ['123', '123'],
            ['', '   '],
            [[], null],
            ['US', 'US '],
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new MinStrLengthFilter(3);
    }
}
