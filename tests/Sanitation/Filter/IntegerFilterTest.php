<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\IntegerFilter;

class IntegerFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            ["+1234567890", 1234567890],
            [1234567890, 1234567890],
            [-123456789.0, -123456789],
            [-1234567890, -1234567890],
            ['-123', -123],
            [' ', null],
            ['', null],
            ["-abc.123", null],
            ["123.abc", null],
            ["123,456", null],
            ['0000123.456000', 123],
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new IntegerFilter();
    }
}
