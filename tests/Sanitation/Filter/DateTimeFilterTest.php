<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\DateTimeFilter;

class DateTimeFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            ['Nov 7, 1979, 12:34pm', '1979-11-07'],
            ['0001-01-01 00:00:00','0001-01-01'],
            ['1970-08-08 12:34:56', '1970-08-08'],
            ['2004-02-29 12:00:00', '2004-02-29'],
            ['0000-01-01T12:34:56', '0000-01-01'],
            ['1979-11-07T12:34', '1979-11-07'],
            ['1970-08-08t12:34:56', '1970-08-08'],
            ['9999-12-31', '9999-12-31'],
            ['  ', null],
            ['', null],
            [[], null],
            ['0000-00-00T00:00:00', null],
            ['0010-20-40T12:34:56', null],
            ['9999.12:31 ab:cd:ef', null],
            ['1979-02-29', null],
            [null, null]
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new DateTimeFilter('Y-m-d');
    }
}
