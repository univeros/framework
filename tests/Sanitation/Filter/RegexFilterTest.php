<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\RegexFilter;

class RegexFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            ['+1234567890', ''],
            [1234567890, ''],
            [-123456789.0, ''],
            [-1234567890, ''],
            ['-123', ''],
            [[], null],
            [' ', ' '],
            ['', ''],
            ['-abc.123', '-abc.123'],
            ['123.abc', '123.abc'],
            ['ABC', 'ABC'],
            ['0000123.456000', '0000123.456000'],
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new RegexFilter('/^[\+\-]?[0-9]+$/', '');
    }
}
