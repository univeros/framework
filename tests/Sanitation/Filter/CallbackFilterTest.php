<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\CallbackFilter;

class CallbackFilterTest extends AbstractFilterTest
{
    public function dataProvider()
    {
        return [
            [true, true],
            ['on', true],
            ['On', true],
            ['ON', true],
            ['yes', true],
            ['Yes', true],
            ['YeS', true],
            ['true', true],
            ['True', true],
            ['TrUe', true],
            [1, true],
            ['1', true],
            [false, true],
            ['off', true],
            ['Off', true],
            ['OfF', true],
            ['no', true],
            ['No', true],
            ['NO', true],
            ['false', true],
            ['False', true],
            ['FaLsE', true],
            [0, true],
            ['0', true],
        ];
    }


    protected function buildFilter(): FilterInterface
    {
        $callback = function ($value) {
            // lets mimic boolean Filter
            // it can be anything though
            return is_bool(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
        };
        return new CallbackFilter($callback);
    }
}
