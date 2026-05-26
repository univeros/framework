<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\MaxStrLengthFilter;

class MaxStrLenFilterTest extends AbstractFilterTest
{
    #[\Override]
    public static function dataProvider(): array
    {
        return [
            ['abcde', 'abc'],
            ['1234', '123'],
            ['antonio', 'ant'],
            [[], null],
            ['US', 'US'],
        ];
    }

    #[\Override]
    protected function buildFilter(): FilterInterface
    {
        return new MaxStrLengthFilter(3);
    }
}
