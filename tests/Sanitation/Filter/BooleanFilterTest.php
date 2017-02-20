<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\BooleanFilter;

class BooleanFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            [true, true],
            ['on', true],
            ['On', true],
            ['ON', true],
            ['yes', true],
            ['Yes' , true],
            ['YeS', true],
            ['true', true],
            ['True', true],
            ['TrUe', true],
            [1, true],
            ['1', true],
            [false, false],
            ['off', false],
            ['Off', false],
            ['OfF', false],
            ['no', false],
            ['No', false],
            ['NO', false],
            ['false', false],
            ['False', false],
            ['FaLsE', false],
            [0, false],
            ['0', false],
            ['nothing', true],
            [123, true],
            [[1, 2, 3], null]
        ];
    }

    protected function buildFilter(): FilterInterface
    {
        return new BooleanFilter();
    }
}
