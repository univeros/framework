<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\LowerCaseFilter;

class LowerCaseFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            ['AbcDe', 'abcde'],
            ['1234', '1234'],
            ['Antonio', 'antonio'],
            [[], null],
            ['US', 'us'],
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new LowerCaseFilter();
    }
}
