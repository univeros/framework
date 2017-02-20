<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\UpperCaseFilter;

class UpperCaseFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            ['AbcDe', 'ABCDE'],
            ['1234', '1234'],
            ['Antonio', 'ANTONIO'],
            [[], null],
            ['US', 'US'],
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new UpperCaseFilter();
    }
}
