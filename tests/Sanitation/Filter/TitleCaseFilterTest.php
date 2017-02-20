<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\TitleCaseFilter;

class TitleCaseFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            ['antonio', 'Antonio'],
            ['123', '123'],
            [[], null],
            ['antonio ramirez', 'Antonio Ramirez'],
            ['US', 'US'],
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new TitleCaseFilter();
    }
}
