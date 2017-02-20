<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\LowerCaseFilter;

class LowerCaseFilterFirstOnlyTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            ['AbcDe', 'abcDe'],
            ['1234', '1234'],
            ['AnToniO', 'anToniO'],
            [[], null],
            ['US', 'uS'],
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new LowerCaseFilter(true);
    }
}
