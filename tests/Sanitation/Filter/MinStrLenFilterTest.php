<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\MinStrLengthFilter;

class MinStrLenFilterTest extends AbstractFilterTest
{
    #[\Override]
    public static function dataProvider(): array
    {
        return [
            ['antonio', 'antonio'],
            ['123', '123'],
            ['', '   '],
            [[], null],
            ['US', 'US '],
        ];
    }

    #[\Override]
    protected function buildFilter(): FilterInterface
    {
        return new MinStrLengthFilter(3);
    }
}
