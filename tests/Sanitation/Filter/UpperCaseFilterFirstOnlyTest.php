<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\UpperCaseFilter;

class UpperCaseFilterFirstOnlyTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            ['AbcDe', 'AbcDe'],
            ['1234', '1234'],
            ['anToniO', 'AnToniO'],
            [[], null],
            ['US', 'US'],
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new UpperCaseFilter(true);
    }
}
